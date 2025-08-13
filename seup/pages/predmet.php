<?php

/**
 * Plaćena licenca
 * (c) 2025 Tomislav Galić <tomislav@8core.hr>
 * Suradnik: Marko Šimunović <marko@8core.hr>
 * Web: https://8core.hr
 * Kontakt: info@8core.hr | Tel: +385 099 851 0717
 * Sva prava pridržana. Ovaj softver je vlasnički i zabranjeno ga je
 * distribuirati ili mijenjati bez izričitog dopuštenia autora.
 */
/**
 *	\file       seup/predmet.php
 *	\ingroup    seup
 *	\brief      Predmet page
 */

// Učitaj Dolibarr okruženje
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
    $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"] . "/main.inc.php";
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
    $i--;
    $j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)) . "/main.inc.php")) {
    $res = @include substr($tmp, 0, ($i + 1)) . "/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php")) {
    $res = @include dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php";
}
if (!$res && file_exists("../main.inc.php")) {
    $res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
    $res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
    $res = @include "../../../main.inc.php";
}
if (!$res) {
    die("Include of main fails");
}
// Libraries
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/usergroups.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT . '/ecm/class/ecmfiles.class.php';

// Lokalne klase
require_once __DIR__ . '/../class/predmet_helper.class.php';
require_once __DIR__ . '/../class/request_handler.class.php';

// Postavljanje debug logova
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Učitaj datoteke prijevoda
$langs->loadLangs(array("seup@seup"));

$action = GETPOST('action', 'aZ09');
$now = dol_now();
$max = getDolGlobalInt('MAIN_SIZE_SHORTLIST_LIMIT', 5);
// Sigurnosna provjera
$socid = GETPOST('socid', 'int');
if (isset($user->socid) && $user->socid > 0) {
    $action = '';
    $socid = $user->socid;
}


// Hvatanje ID predmeta iz GET zahtjeva
// Ako je ID predmeta postavljen, dohvatit ćemo detalje predmeta
$caseId = GETPOST('id', 'int');
dol_syslog("Dohvaćanje ID predmeta: $caseId", LOG_DEBUG);
if (empty($caseId)) {
    header('Location: ' . dol_buildpath('/custom/seup/pages/predmeti.php', 1));
    exit;
}

// Definiranje direktorija za učitavanje dokumenata
$upload_base_dir = DOL_DATA_ROOT . '/ecm/';
$upload_dir = $upload_base_dir . 'SEUP/predmet_' . $caseId . '/';
// Create directory if not exists
if (!is_dir($upload_dir)) {
    dol_mkdir($upload_dir);
}

dol_syslog("Accessing case details for ID: $caseId", LOG_DEBUG);
$caseDetails = null;

if ($caseId) {
    // Fetch case details
    $sql = "SELECT 
                p.ID_predmeta,
                CONCAT(p.klasa_br, '-', p.sadrzaj, '/', p.godina, '-', p.dosje_broj, '/', p.predmet_rbr) as klasa,
                p.naziv_predmeta,
                DATE_FORMAT(p.tstamp_created, '%d.%m.%Y') as datum_otvaranja,
                u.name_ustanova,
                k.ime_prezime,
                ko.opis_klasifikacijske_oznake
            FROM " . MAIN_DB_PREFIX . "a_predmet p
            LEFT JOIN " . MAIN_DB_PREFIX . "a_oznaka_ustanove u ON p.ID_ustanove = u.ID_ustanove
            LEFT JOIN " . MAIN_DB_PREFIX . "a_interna_oznaka_korisnika k ON p.ID_interna_oznaka_korisnika = k.ID
            LEFT JOIN " . MAIN_DB_PREFIX . "a_klasifikacijska_oznaka ko ON p.ID_klasifikacijske_oznake = ko.ID_klasifikacijske_oznake
            WHERE p.ID_predmeta = " . (int)$caseId;

    $resql = $db->query($sql);
    if ($resql && $db->num_rows($resql) > 0) {
        $caseDetails = $db->fetch_object($resql);
    }
}


// definiranje direktorija za privremene datoteke
define('TEMP_DIR_RELATIVE', '/temp/');
define('TEMP_DIR_FULL', DOL_DATA_ROOT . TEMP_DIR_RELATIVE);
define('TEMP_DIR_WEB', DOL_URL_ROOT . '/documents' . TEMP_DIR_RELATIVE);

// Ensure temp directory exists
if (!file_exists(TEMP_DIR_FULL)) {
    dol_mkdir(TEMP_DIR_FULL);
}

$form = new Form($db);
$formfile = new FormFile($db);

llxHeader("", "", '', '', 0, 0, '', '', '', 'mod-seup page-index');

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    dol_syslog('POST request', LOG_INFO);

    // Handle document upload
    if (isset($_POST['action']) && GETPOST('action') === 'upload_document') {
        Request_Handler::handleUploadDocument($db, $upload_dir, $langs, $conf, $user);
        exit;
    }

    // File existence check
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && GETPOST('action') === 'check_file_exists') {
        ob_end_clean();
        $file_path = GETPOST('file', 'alphanohtml');
        if (strpos($file_path, TEMP_DIR_RELATIVE) !== 0) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid file path']);
            exit;
        }
        $full_path = DOL_DATA_ROOT . $file_path;
        $exists = file_exists($full_path);
        header('Content-Type: application/json');
        echo json_encode(['exists' => $exists, 'path' => $full_path]);
        exit;
    }
}

// Prikaz dokumenata na tabu 2
$documentTableHTML = '';
Predmet_helper::fetchUploadedDocuments($db, $conf, $documentTableHTML, $langs, $caseId);

// === BOOTSTRAP CDN ===
print '<meta name="viewport" content="width=device-width, initial-scale=1">';
print '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet">';
print '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">';
print '<link href="/custom/seup/css/style.css" rel="stylesheet">';

print '<div class="container mt-5 shadow-sm p-3 mb-5 bg-body rounded">';

// Tabovi
print '
<ul class="nav nav-tabs mb-4" id="myTab" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="tab1-tab" data-bs-toggle="tab" data-bs-target="#tab1" type="button" role="tab" aria-controls="tab1" aria-selected="true">
      <i class="fas fa-home me-2"></i>Predmet
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="tab2-tab" data-bs-toggle="tab" data-bs-target="#tab2" type="button" role="tab" aria-controls="tab2" aria-selected="false">
      <i class="fas fa-file-alt me-2"></i>Dokumenti u prilozima
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="tab3-tab" data-bs-toggle="tab" data-bs-target="#tab3" type="button" role="tab" aria-controls="tab3" aria-selected="false">
      <i class="fas fa-search"></i>Predpregled
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="tab4-tab" data-bs-toggle="tab" data-bs-target="#tab4" type="button" role="tab" aria-controls="tab4" aria-selected="false">
      <i class="fas fa-chart-bar me-2"></i>Šta god
    </button>
  </li>
</ul>

<div class="tab-content" id="myTabContent">';

// Tab 1 - Case Details or Welcome
print '<div class="tab-pane fade show active" id="tab1" role="tabpanel" aria-labelledby="tab1-tab">';
if ($caseDetails) {
    print '
    <div class="p-3 border rounded">
        <h4 class="mb-4">Detalji predmeta #' . $caseDetails->ID_predmeta . '</h4>
        
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="fw-bold">Klasa:</label>
                    <div class="badge bg-primary fs-6">' . $caseDetails->klasa . '</div>
                </div>
                
                <div class="mb-3">
                    <label class="fw-bold">Naziv predmeta:</label>
                    <p>' . $caseDetails->naziv_predmeta . '</p>
                </div>
                
                <div class="mb-3">
                    <label class="fw-bold">Ustanova:</label>
                    <p>' . $caseDetails->name_ustanova . '</p>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="fw-bold">Zaposlenik:</label>
                    <p>' . $caseDetails->ime_prezime . '</p>
                </div>
                
                <div class="mb-3">
                    <label class="fw-bold">Datum otvaranja:</label>
                    <p>' . $caseDetails->datum_otvaranja . '</p>
                </div>
                
                <div class="mb-3">
                    <label class="fw-bold">Status:</label>
                    <span class="badge bg-success">Aktivan</span>
                </div>
            </div>
        </div>
    </div>';
} else {
    print '
    <div class="p-3 border rounded">
        <div class="text-center py-5">
            <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
            <h4 class="mb-3">Dobrodošli</h4>
            <p class="text-muted">Ovo je početna stranica. Za pregled predmeta posjetite stranicu Predmeti.</p>
            <a href="predmeti.php" class="btn btn-primary mt-2">
                <i class="fas fa-external-link-alt me-1"></i> Otvori Predmete
            </a>
        </div>
    </div>';
}
print '</div>

  <!-- Tab 2 - Documents -->
  <div class="tab-pane fade" id="tab2" role="tabpanel" aria-labelledby="tab2-tab">
  <div class="p-3 border rounded">
    <h4 class="mb-3">Akti i prilozi</h4>
    <p>Pregled dodanih priloga sa datumom kreiranja i kreatorom</p>
    ' . $documentTableHTML . '
    <div class="mt-3 d-flex gap-2">
      <!-- Add these 2 lines -->
      <button type="button" id="uploadTrigger" class="btn btn-primary btn-sm">
        <i class="fas fa-upload me-1"></i> Dodaj dokument
      </button>
      <input type="file" id="documentInput" style="display: none;">
      
      <!-- Keep your other buttons -->
      <button type="button" class="btn btn-secondary btn-sm">Dugme 2</button>
      <button type="button" class="btn btn-success btn-sm">Dugme 3</button>
    </div>
  </div>
</div>

  <!-- Tab 3 - Preview -->
  <div class="tab-pane fade" id="tab3" role="tabpanel" aria-labelledby="tab3-tab">
    <div class="p-3 border rounded">
      <h4 class="mb-3">Predpregled omota sposa sa listom priloga</h4>
      <p>Bumo vidli kako</p>
      <div class="mt-3 d-flex gap-2">
        <button type="button" class="btn btn-primary btn-sm" data-action="generate_pdf">Kreiraj PDF</button>
        <button type="button" class="btn btn-secondary btn-sm">Dugme 2</button>
        <button type="button" class="btn btn-success btn-sm">Dugme 3</button>
      </div>
    </div>
  </div>

  <!-- Tab 4 - Stats -->
  <div class="tab-pane fade" id="tab4" role="tabpanel" aria-labelledby="tab4-tab">
    <div class="p-3 border rounded">
      <h4 class="mb-3">Statistički podaci</h4>
      <p>Možda evidencije logiranja i provedenog vremena</p>
      <div class="mt-3 d-flex gap-2">
        <button type="button" class="btn btn-primary btn-sm">Dugme 1</button>
        <button type="button" class="btn btn-secondary btn-sm">Dugme 2</button>
        <button type="button" class="btn btn-success btn-sm">Dugme 3</button>
      </div>
    </div>
  </div>
</div>
</div>';

// Bootstrap JS
print '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>';

?>

<input type="hidden" name="token" value="<?php echo newToken(); ?>">

<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Get elements safely
        const uploadTrigger = document.getElementById("uploadTrigger");
        const documentInput = document.getElementById("documentInput");
        const pdfButton = document.querySelector("[data-action='generate_pdf']");

        // Only add event listeners if elements exist
        if (uploadTrigger && documentInput) {
            // Upload trigger
            uploadTrigger.addEventListener("click", function() {
                documentInput.click();
            });

            // File selection handler
            documentInput.addEventListener("change", function(e) {
                const allowedTypes = [
                    "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
                    "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
                    "application/msword",
                    "application/vnd.ms-excel",
                    "application/octet-stream",
                    "application/zip",
                    "application/pdf",
                    "image/jpeg",
                    "image/png"
                ];

                const allowedExtensions = [
                    ".docx", ".xlsx", ".doc", ".xls",
                    ".pdf", ".jpg", ".jpeg", ".png", ".zip"
                ];

                if (this.files.length > 0) {
                    const file = this.files[0];
                    const extension = "." + file.name.split(".").pop().toLowerCase();

                    const formData = new FormData();
                    formData.append("document", file);
                    formData.append("token", document.querySelector("input[name='token']").value);
                    formData.append("action", "upload_document");
                    formData.append("case_id", <?php echo $caseId; ?>);

                    if (!allowedTypes.includes(file.type) && !allowedExtensions.includes(extension)) {
                        alert("<?php echo $langs->transnoentities('ErrorInvalidFileTypeJS'); ?>\nAllowed formats: " + allowedExtensions.join(", "));
                        this.value = "";
                        return;
                    }

                    if (file.size > 10 * 1024 * 1024) {
                        alert("<?php echo $langs->transnoentities('ErrorFileTooLarge'); ?>");
                        this.value = "";
                        return;
                    }

                    fetch("", {
                        method: "POST",
                        body: formData
                    }).then(response => {
                        if (response.ok) {
                            document.getElementById("documentInput").value = "";
                            window.location.reload();
                        }
                    }).catch(error => {
                        console.error("Upload error:", error);
                    });
                }
            });
        } else {
            console.warn("Upload elements not found");
        }

        // PDF generation
        if (pdfButton) {
            pdfButton.addEventListener("click", function() {
                const generatePdfUrl = "<?php echo DOL_URL_ROOT . '/custom/seup/class/generate_pdf.php'; ?>";
                fetch(generatePdfUrl, {
                        method: "POST"
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.file) {
                            window.open(data.file, "_blank");
                        } else {
                            throw new Error(data.error || "PDF generation failed.");
                        }
                    })
                    .catch(error => {
                        console.error("PDF generation error:", error);
                        alert("PDF generation failed: " + error.message);
                    });
            });
        } else {
            console.warn("PDF button not found");
        }
    });
</script>

<?php

llxFooter();
$db->close();
