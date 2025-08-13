<?php

/**
 * Plaćena licenca
 * (c) 2025 Tomislav Galić <tomislav@8core.hr>
 * Suradnik: Marko Šimunović <marko@8core.hr>
 * Web: https://8core.hr
 * Kontakt: info@8core.hr | Tel: +385 099 851 0717
 * Sva prava pridržana. Ovaj softver je vlasnički i zabranjeno ga je
 * distribuirati ili mijenjati bez izričitog dopuštenja autora.
 */
/**
 *	\file       seup/novi_predmet.php
 *	\ingroup    seup
 *	\brief      Creation page for new predmet
 */


// Učitaj Dolibarr okruženje
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
  $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"] . "/main.inc.php";
}
// Pokušaj učitati main.inc.php iz korijenskog direktorija weba, koji je određen na temelju vrijednosti SCRIPT_FILENAME.
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
// Pokušaj učitati main.inc.php koristeći relativnu putanju

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

// Pokretanje buffera - potrebno za flush emitiranih podataka (fokusiranje na json format)
ob_start();

// Libraries
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/usergroups.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT . '/ecm/class/ecmfiles.class.php'; // ECM klasa - za baratanje dokumentima


// Lokalne klase
require_once __DIR__ . '/../class/predmet_helper.class.php';
require_once __DIR__ . '/../class/request_handler.class.php';

// Postavljanje debug logova
error_reporting(E_ALL);
ini_set('display_errors', 1);


// Učitaj datoteke prijevoda potrebne za stranicu
$langs->loadLangs(array("seup@seup"));

$action = GETPOST('action', 'aZ09');

$now = dol_now();
$max = getDolGlobalInt('MAIN_SIZE_SHORTLIST_LIMIT', 5);

// Sigurnosna provjera – zaštita ako je korisnik eksterni
$socid = GETPOST('socid', 'int');
if (isset($user->socid) && $user->socid > 0) {
  $action = '';
  $socid = $user->socid;
}


// definiranje direktorija za privremene datoteke
define('TEMP_DIR_RELATIVE', '/temp/'); // Relative to DOL_DATA_ROOT
define('TEMP_DIR_FULL', DOL_DATA_ROOT . TEMP_DIR_RELATIVE);
define('TEMP_DIR_WEB', DOL_URL_ROOT . '/documents' . TEMP_DIR_RELATIVE);

// Ensure temp directory exists
if (!file_exists(TEMP_DIR_FULL)) {
  dol_mkdir(TEMP_DIR_FULL);
}


/*
 * View
 */

$form = new Form($db);
$formfile = new FormFile($db);

llxHeader("", "", '', '', 0, 0, '', '', '', 'mod-seup page-index');



/************************************
 ******** POST REQUESTOVI ************
 *************************************
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  dol_syslog('POST request', LOG_INFO);

  // OTVORI PREDMET
  if (isset($_POST['action']) && $_POST['action'] === 'otvori_predmet') {
    Request_Handler::handleOtvoriPredmet($db);
    exit;
  }
}

// Registriranje requestova za autocomplete i dinamicko popunjavanje vrijednosti Sadrzaja
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
  Request_Handler::handleCheckPredmetExists($db);
  exit;
}

if (isset($_GET['ajax']) && $_GET['ajax'] == 'autocomplete_stranka') {
  Request_Handler::handleStrankaAutocomplete($db);
  exit;
}


// Dohvat tagova iz baze 
$tags = array();
$sql = "SELECT rowid, tag FROM " . MAIN_DB_PREFIX . "a_tagovi WHERE entity = " . $conf->entity . " ORDER BY tag ASC";
$resql = $db->query($sql);
if ($resql) {
  while ($obj = $db->fetch_object($resql)) {
    $tags[] = $obj;
    dol_syslog("Tag: " . $obj->tag, LOG_DEBUG);
  }
}

$availableTagsHTML = '';
foreach ($tags as $tag) {
  $availableTagsHTML .= '<button type="button" class="btn btn-sm btn-outline-primary tag-option" 
                          data-tag-id="' . $tag->rowid . '">';
  $availableTagsHTML .= htmlspecialchars($tag->tag);
  $availableTagsHTML .= '</button>';
}

// Potrebno za kreiranje klase predmeta
// Inicijalno punjenje podataka za potrebe klase
$klasaOptions = '';
$zaposlenikOptions = '';
$code_ustanova = '';

$klasa_text = 'KLASA: OZN-SAD/GOD-DOS/RBR';
$klasaMapJson = '';

Predmet_helper::fetchDropdownData($db, $langs, $klasaOptions, $klasaMapJson, $zaposlenikOptions);


// === BOOTSTRAP CDN DODAVANJE ===
// Meta tag za responzivnost
print '<meta name="viewport" content="width=device-width, initial-scale=1">';
// Bootstrap CSS
print '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-SgOJa3DmI69IUzQ2PVdRZhwQ+dy64/BUtbMJw1MZ8t5HZApcHrRKUc4W0kG879m7" crossorigin="anonymous">';
// Add flatpickr CDN links
print '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">';
print '<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>';
print '<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/hr.js"></script>';

// Create single input fields for dates
$strankaDateHTML = '<input type="text" class="form-control flatpickr-date" name="strankaDatumOtvaranja" placeholder="Odaberi datum">';
$datumOtvaranjaHTML = '<input type="text" class="form-control flatpickr-date" name="datumOtvaranja" placeholder="Odaberi datum">';


// Custom style 8Core
print '<link href="/custom/seup/css/style.css" rel="stylesheet">';

print '<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />';
print '<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>';

//Kec ispod ovoga ti je sve što ti treba //

print '<div class="container mt-5 shadow-sm p-3 mb-5 bg-body rounded">';


$htmlContent = <<<HTML
<div class="container mt-5 shadow-sm p-3 mb-5 bg-body rounded">
    <h4 class="mb-3">Klasa</h4>
    <p id="klasa-value">$klasa_text</p>
    
    <div class="row g-3 mt-3">
      <div class="col-md-6">
        <div class="p-3 border rounded h-100">
          <h5 class="mb-3">Odabir parametara klase</h5>
          
          <div class="mb-3">
            <label for="klasa_br">{$langs->trans("Klasa broj")}:</label>
            <select name="klasa_br" id="klasa_br" class="form-select">
              $klasaOptions
            </select>
          </div>

          <div class="mb-3">
            <label for="sadrzaj">{$langs->trans("Sadrzaj")}:</label>
            <select name="sadrzaj" id="sadrzaj" class="form-select" data-placeholder="{$langs->trans("Odaberi Sadrzaj")}">
              <option value="">{$langs->trans("Odaberi Sadrzaj")}</option>
            </select>
          </div>

          <div class="mb-3">
            <label for="dosjeBroj">{$langs->trans("Dosje Broj")}:</label>
            <select name="dosjeBroj" id="dosjeBroj" class="form-select" data-placeholder="{$langs->trans("Odaberi Dosje Broj")}">
              <option value="">{$langs->trans("Odaberi Dosje Broj")}</option>
            </select>
          </div>
          
          <div class="mb-3">
            <label for="zaposlenik" class="form-label">{$langs->trans("Zaposlenik")}</label>
            <select class="form-select text-black" id="zaposlenik" name="zaposlenik" required>
              $zaposlenikOptions
            </select>
          </div>

          <div class="mb-3">
            <label for="stranka" class="form-label">{$langs->trans("Stranka")}</label>
            <div class="d-flex align-items-center gap-2">
                <select class="form-select" id="stranka" name="stranka" disabled style="flex:4;"></select>
                <div class="d-flex align-items-center match-height" style="flex: 1;">
                    <input type="checkbox" class="btn-check" id="strankaCheck" autocomplete="off">
                    <label class="btn btn-outline-secondary w-100 text-nowrap d-flex align-items-center justify-content-center" 
                          for="strankaCheck" id="strankaCheckLabel" 
                          style="padding: 0.25rem 0.5rem; font-size: 0.875rem;">
                        Otvorila stranka?
                    </label>
                </div>
            </div>
            <div id="strankaDatumContainer" class="mt-2" style="display:none;">
              <label for="strankaDatumOtvaranja" class="form-label">Datum otvaranja predmeta od strane stranke</label>
              <div class="mb-1">
              $strankaDateHTML
              </div>
              <div id="strankaDateError" class="invalid-feedback" style="display: none;">
                  Odaberite datum otvaranja predmeta!
              </div>
            </div>
            <div id="strankaError" class="invalid-feedback" style="display: none;">
                Odaberite stranku! (Required when checkbox is checked)
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-md-6">
        <div class="p-3 border rounded h-100 bg-light">
          <label for="naziv" class="form-label h5">Naziv Predmeta</label>
          <textarea class="form-control" id="naziv" name="naziv" rows="8" maxlength="500" placeholder="Unesite naziv predmeta (maksimalno 500 znakova)" style="resize: none;"></textarea>
          <div class="mt-3">
            <label for="datumOtvaranja" class="form-label">Datum Otvaranja Predmeta</label>
            <div class="mb-1">
              $datumOtvaranjaHTML
            </div>
            <small class="form-text text-muted">Ostavite prazno za današnji datum</small>
          </div>
          <div class="mt-3">
              <label class="form-label">{$langs->trans('Oznake')}</label>
              <div class="input-group mb-2">
                  <button class="btn btn-outline-secondary dropdown-toggle w-100 text-start" 
                          type="button" 
                          id="tagsDropdown" 
                          data-bs-toggle="dropdown" 
                          aria-expanded="false">
                      Odaberi oznake
                  </button>
                  <div class="dropdown-menu p-2" aria-labelledby="tagsDropdown" style="width: 100%">
                      <div class="d-flex flex-wrap gap-1" id="available-tags">
                          {$availableTagsHTML}
                      </div>
                  </div>
                  <button class="btn btn-outline-primary" type="button" id="add-tag-btn">
                      Dodaj
                  </button>
              </div>
              <div class="d-flex flex-wrap gap-1 mt-2" id="selected-tags"></div>
          </div>
        </div> <!-- end p-3 border -->
      </div> <!-- end col-md-6 -->
    </div> <!-- end row -->
    
    <div class="mt-3 d-flex gap-2">
      <div class="mt-3">
          <button type="button" class="btn btn-primary btn-sm" id="otvoriPredmetBtn">Otvori Predmet</button>
      </div>
    </div>
</div>
HTML;

// Print the HTML content
print $htmlContent;


// Ne diraj dalje ispod ništa ne mjenjaj dole je samo bootstrap cdn java scripta i dolibarr footer postavke kao što vidiš//

// Bootstrap JS bundle (uključuje Popper)
print '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js" integrity="sha384-k6d4wzSIapyDyv1kpU366/PK5hCdSbCRGRCMv+eplOQJWyd1fbcAu9OCUj5zNLiq" crossorigin="anonymous"></script>';
// End of page
llxFooter();
$db->close();
// TODO add Tagovi polje nakon implementacije
?>

<script type="text/javascript">
  // override da se u dropdownu prikazuje hrvatski jezik za placeholder text
  jQuery.fn.select2.defaults.set('language', {
    inputTooShort: function(args) {
      return "Unesite barem 2 znaka za pretraživanje";
    }
  });

  document.addEventListener("DOMContentLoaded", function() {
    // Get the select elements and klasa value element
    const dataHolder = document.getElementById("phpDataHolder");
    const klasaMap = JSON.parse('<?php echo $klasaMapJson; ?>');
    console.log("KlasaMap loaded:", klasaMap); // For debugging
    var klasaSelect = document.getElementById("klasa_br");
    var sadrzajSelect = document.getElementById("sadrzaj");
    const dosjeSelect = document.getElementById("dosjeBroj");
    var zaposlenikSelect = document.getElementById("zaposlenik");
    var klasaValue = document.getElementById("klasa-value");
    const otvoriPredmetBtn = document.getElementById("otvoriPredmetBtn");

    // Stranka autocomplete functionality
    const strankaInput = document.getElementById('stranka');
    const strankaResults = document.getElementById('stranka-results');
    let lastSearchTerm = '';

    jQuery(document).ready(function() {
      // Initialize flatpickr
      flatpickr('.flatpickr-date', {
        dateFormat: "d.m.Y",
        locale: "hr",
        allowInput: true,
        static: true
      });
    });


    /****************************************/
    /* Stranka autocomplete funkcionalnost  */
    /****************************************/
    document.getElementById('strankaCheck').addEventListener('change', function() {
      const selectField = document.getElementById('stranka');
      const label = document.getElementById('strankaCheckLabel');
      const errorDiv = document.getElementById('strankaError');
      const container = document.getElementById('strankaDatumContainer');

      if (this.checked) {
        // Enable field and make it required
        selectField.disabled = false;
        selectField.required = true;

        // Initialize Select2 if not already initialized
        if (!selectField.hasAttribute('data-select2-id')) {
          jQuery(selectField).select2({
            placeholder: "OIB ili naziv stranke",
            allowClear: true,
            ajax: {
              url: 'novi_predmet.php?ajax=autocomplete_stranka',
              dataType: 'json',
              delay: 300,
              data: function(params) {
                return {
                  term: params.term
                };
              },
              processResults: function(data) {
                return {
                  results: data.map(item => ({
                    id: item.label,
                    text: item.label + (item.vat ? ' (' + item.vat + ')' : '')
                  }))
                };
              },
              cache: true
            },
            minimumInputLength: 2
          });
        }

        // Update button style
        label.classList.remove('btn-outline-secondary');
        label.classList.add('btn-primary');

        // Clear any previous errors
        errorDiv.style.display = 'none';
        selectField.classList.remove('is-invalid');

        // Show date container
        container.style.display = 'block';

        // Focus the field
        selectField.focus();
      } else {
        // Destroy Select2 and disable
        if (selectField.hasAttribute('data-select2-id')) {
          $(selectField).select2('destroy');
        }
        selectField.disabled = true;
        selectField.required = false;
        selectField.innerHTML = '';

        // Revert button style
        label.classList.remove('btn-primary');
        label.classList.add('btn-outline-secondary');

        // Clear any errors
        errorDiv.style.display = 'none';
        selectField.classList.remove('is-invalid');

        // Hide date container
        container.style.display = 'none';

        // Clear date inputs
        jQuery('input[name="strankaDatumOtvaranja_day"]').val('');
        jQuery('input[name="strankaDatumOtvaranja_month"]').val('');
        jQuery('input[name="strankaDatumOtvaranja_year"]').val('');
      }
    });

    /****************************************/
    /* KRAJ Stranka autocomplete funkcionalnost  */
    /****************************************/

    const placeholderText = "<?php echo $langs->trans('Odaberi Sadrzaj'); ?>";
    // Check if elements are present
    if (!klasaSelect || !sadrzajSelect || !zaposlenikSelect || !klasaValue) {
      if (!klasaSelect) {
        console.error("Klasa select element not found in DOM.");
      }
      if (!sadrzajSelect) {
        console.error("Sadrzaj select element not found in DOM.");
      }
      if (!dosjeSelect) {
        console.error("Dosje select element not found in DOM.");
      }
      if (!zaposlenikSelect) {
        console.error("Zaposlenik select element not found in DOM.");
      }
      if (!klasaValue) {
        console.error("Klasa value element not found in DOM.");
      }
      console.error("Klasa, Sadrzaj, Zaposlenik, or Klasa Value element not found in DOM.");
      return;
    }
    console.log("DOMContentLoaded");

    var klasaText = <?php echo json_encode($klasa_text); ?>;

    // State for keeping track of current values
    var currentValues = {
      klasa: "",
      sadrzaj: "",
      dosje: "",
      rbr: "1"
    };

    let year = new Date().getFullYear();
    year = year.toString().slice(-2);

    function updateKlasaValue() {
      const klasa = currentValues.klasa || "OZN";
      const sadrzaj = currentValues.sadrzaj || "SAD";
      const selectedDosje = dosjeSelect.value || "DOS";
      const rbr = currentValues.rbr || "1";

      // Build the string using template literals
      const updatedText = `KLASA: ${klasa}-${sadrzaj}/${year}-${selectedDosje}/${rbr}`;
      klasaValue.textContent = updatedText;
    }

    function checkIfPredmetExists() {
      var klasa = klasaSelect.value || "OZN";
      var sadrzaj = sadrzajSelect.value || "SAD";
      var dosje_br = dosjeSelect.value || "DOS";
      console.log("gledam jel postoji predmet");
      if (klasa !== "OZN" && sadrzaj !== "SAD" && dosje_br !== "DOS") {
        fetch(
            "novi_predmet.php?ajax=1&" +
            "klasa_br=" + encodeURIComponent(klasa) +
            "&sadrzaj=" + encodeURIComponent(sadrzaj) +
            "&dosje_br=" + encodeURIComponent(dosje_br) +
            "&god=" + encodeURIComponent(year), {
              headers: {
                "Accept": "application/json"
              }
            }
          )
          .then(response => {
            return response.json();
          })
          .then(data => {
            if (data.status === "exists" || data.status === "inserted") {
              // Update the RBR part of the klasa text
              currentValues.rbr = data.next_rbr;

              // Refresh the klasa text on screen
              updateKlasaValue();

              if (data.status === "exists") {
                console.log("Ovakav predmet postoji. Generiram sljedeci redni broj predmeta.");
              }
            } else {
              console.log("Predmet does not exist, ready to create new one." + data.status);
            }
          })
          .catch(error => console.error("Error checking predmet:", error));
      }
    }

    function resetKlasaDisplay() {
      currentValues = {
        klasa: "",
        sadrzaj: "",
        dosje: "",
        rbr: "1",
        zaposlenik: ""
      };
      klasaSelect.value = "";
      sadrzajSelect.innerHTML = `<option value="">${sadrzajSelect.dataset.placeholder}</option>`;
      dosjeSelect.innerHTML = `<option value="">${dosjeSelect.dataset.placeholder}</option>`;
      zaposlenikSelect.value = "";

      updateKlasaValue();
    }

    // Update on klasa change
    if (klasaSelect) {
      klasaSelect.addEventListener("change", function() {
        console.log("Selected klasa:", this.value);
        console.log("Available sadrzaj:", klasaMap[this.value]);
        currentValues.klasa = this.value || "";
        currentValues.dosje = "";

        // Reset sadrzaj dropdown
        sadrzajSelect.innerHTML = `<option value="">${sadrzajSelect.dataset.placeholder}</option>`;


        dosjeSelect.innerHTML = `<option value="">${dosjeSelect.dataset.placeholder}</option>`;

        // Populate new options based on selected klasa
        // Populate Sadrzaj if klasa selected
        if (this.value && klasaMap[this.value]) {
          const sadrzajValues = Object.keys(klasaMap[this.value]);
          sadrzajValues.forEach(sadrzaj => {
            const option = new Option(sadrzaj, sadrzaj);
            sadrzajSelect.appendChild(option);
          });
        }

        // Update the klasa text
        updateKlasaValue();
        checkIfPredmetExists();
      });
    }

    // Update on sadrzaj change
    if (sadrzajSelect) {
      sadrzajSelect.addEventListener("change", function() {
        console.log("Selected klasa:", klasaSelect.value);
        console.log("Selected sadrzaj:", this.value);
        console.log("Available dosje:", klasaMap[klasaSelect.value]?.[this.value]);
        dosjeSelect.innerHTML = `<option value="">${dosjeSelect.dataset.placeholder}</option>`;

        currentValues.sadrzaj = this.value || "SAD";
        currentValues.dosje = "";

        const klasa = klasaSelect.value;
        const sadrzaj = this.value;
        // Populate Dosje Broj if values exist
        if (klasa && sadrzaj && klasaMap[klasa] && klasaMap[klasa][sadrzaj]) {
          klasaMap[klasa][sadrzaj].forEach(dosje => {
            const option = new Option(dosje, dosje);
            dosjeSelect.appendChild(option);
          });
        }
        updateKlasaValue();
        checkIfPredmetExists();
      });
    }

    if (dosjeSelect)
      dosjeSelect.addEventListener("change", function() {
        currentValues.dosje = this.value || "";
        updateKlasaValue();
        checkIfPredmetExists();
      });

    otvoriPredmetBtn.addEventListener("click", function() {
      const klasa = klasaSelect.value;
      const sadrzaj = sadrzajSelect.value;
      const dosje = dosjeSelect.value;
      const zaposlenik = zaposlenikSelect.value;
      const naziv = document.getElementById("naziv").value;

      // Get elements related to Stranka field
      const strankaCheckbox = document.getElementById('strankaCheck');
      const strankaField = document.getElementById('stranka');
      const strankaError = document.getElementById('strankaError');

      // Reset any previous error states
      strankaField.classList.remove('is-invalid');
      strankaError.style.display = 'none';

      // 1. VALIDATION FOR ALL REQUIRED FIELDS
      let isValid = true;
      const missingFields = [];

      // Check each required field
      if (!klasa) missingFields.push("Klasa broj");
      if (!sadrzaj) missingFields.push("Sadržaj");
      if (!dosje) missingFields.push("Dosje broj");
      if (!zaposlenik) missingFields.push("Zaposlenik");
      if (!naziv.trim()) missingFields.push("Naziv predmeta");

      const strankaDateError = document.getElementById('strankaDateError');
      if (strankaDateError) {
        strankaDateError.style.display = 'none';
      }

      // 2. SPECIAL VALIDATION FOR STRANKA FIELD
      if (strankaCheckbox.checked) {
        if (!strankaField.value) {
          isValid = false;
          strankaField.classList.add('is-invalid');
          strankaError.style.display = 'block';
          strankaField.focus();
        }

        // Validate date for Stranka
        const strankaDateInput = document.querySelector('input[name="strankaDatumOtvaranja"]');
        if (!strankaDateInput || !strankaDateInput.value) {
          isValid = false;
          // Show date error
          if (strankaDateError) {
            strankaDateError.style.display = 'block';
          } else {
            // Create error element if it doesn't exist
            const errorDiv = document.createElement('div');
            errorDiv.id = 'strankaDateError';
            errorDiv.className = 'invalid-feedback';
            errorDiv.textContent = 'Odaberite datum otvaranja predmeta!';
            errorDiv.style.display = 'block';
            document.querySelector('#strankaDatumContainer').appendChild(errorDiv);
          }
        }
      }

      // 3. CHECK IF ANY REQUIRED FIELDS ARE MISSING
      if (missingFields.length > 0) {
        isValid = false;
        // Create alert message listing all missing fields
        const errorMessage = "Molimo vas da popunite sva obavezna polja:\n\n" +
          missingFields.map(field => `- ${field}`).join("\n");
        alert(errorMessage);
      }

      // 4. STOP IF VALIDATION FAILED
      if (!isValid) {
        return;
      }
      const formData = new FormData();
      formData.append("action", "otvori_predmet");
      formData.append("klasa_br", klasa);
      formData.append("sadrzaj", sadrzaj);
      formData.append("dosje_broj", dosje);
      formData.append("zaposlenik", zaposlenik);
      formData.append("god", year);
      formData.append("naziv", naziv);

      // Add Stranka value if checkbox is checked
      if (strankaCheckbox.checked) {
        formData.append("stranka", strankaField.value.trim());
        const strankaDateInput = document.querySelector('input[name="strankaDatumOtvaranja"]');

        if (strankaDateInput && strankaDateInput.value) {
          // Parse the date from DD.MM.YYYY to YYYY-MM-DD
          const [day, month, year] = strankaDateInput.value.split('.');
          const formattedDate = `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`;
          formData.append("strankaDatumOtvaranja", formattedDate);
        }
      }


      // Get date value and convert to timestamp
      const datumInput = document.querySelector('input[name="datumOtvaranja"]');
      let datumOtvaranjaTimestamp = null;

      if (datumInput && datumInput.value) {
        // Parse the date from DD.MM.YYYY to YYYY-MM-DD
        const [day, month, year] = datumInput.value.split('.');
        const now = new Date();
        datumOtvaranjaTimestamp =
          `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')} ` +
          `${now.getHours().toString().padStart(2, '0')}:` +
          `${now.getMinutes().toString().padStart(2, '0')}:` +
          `${now.getSeconds().toString().padStart(2, '0')}`;
      } else {
        const now = new Date();
        datumOtvaranjaTimestamp =
          `${now.getFullYear()}-` +
          `${(now.getMonth() + 1).toString().padStart(2, '0')}-` +
          `${now.getDate().toString().padStart(2, '0')} ` +
          `${now.getHours().toString().padStart(2, '0')}:` +
          `${now.getMinutes().toString().padStart(2, '0')}:` +
          `${now.getSeconds().toString().padStart(2, '0')}`;
      }

      formData.append("datumOtvaranja", datumOtvaranjaTimestamp);

      // Add selected tags
      selectedTags.forEach(tagId => {
        formData.append("tags[]", tagId);
      });

      fetch("novi_predmet.php", {
          method: "POST",
          body: formData
        })
        .then(async response => {
          const responseText = await response.text(); // First get raw text

          try {
            // Try to parse as JSON
            return JSON.parse(responseText);
          } catch (e) {
            // If parsing fails, throw custom error with server response
            throw new Error(`Invalid JSON response: ${responseText.substring(0, 100)}...`);
          }
        })
        .then(data => {
          if (data.success) {
            alert("Predmet je uspješno otvoren.");

            // Reset klasa display (preserves your klasa/sadrzaj functionality)
            resetKlasaDisplay();

            // Clear main date inputs
            const mainDateInput = document.querySelector('input[name="datumOtvaranja"]');
            if (mainDateInput) {
              mainDateInput.value = '';
              mainDateInput.dispatchEvent(new Event('change')); // Trigger Flatpickr update
            }

            // Clear customer date inputs
            const strankaDateInput = document.querySelector('input[name="strankaDatumOtvaranja"]');
            if (strankaDateInput) {
              strankaDateInput.value = '';
              strankaDateInput.dispatchEvent(new Event('change')); // Trigger Flatpickr update
            }

            // Reset Stranka section
            const strankaCheckbox = document.getElementById('strankaCheck');
            const strankaField = document.getElementById('stranka');
            const strankaError = document.getElementById('strankaError');

            if (strankaCheckbox && strankaField && strankaError) {
              strankaCheckbox.checked = false;

              // Reset Select2 if it exists
              if (strankaField.hasAttribute('data-select2-id')) {
                $(strankaField).val(null).trigger('change');
              } else {
                strankaField.value = '';
              }

              strankaField.disabled = true;
              strankaField.classList.remove('is-invalid');
              strankaError.style.display = 'none';

              // Update button styles
              const strankaCheckLabel = document.getElementById('strankaCheckLabel');
              if (strankaCheckLabel) {
                strankaCheckLabel.classList.remove('btn-primary');
                strankaCheckLabel.classList.add('btn-outline-secondary');
              }

              // Hide date container
              const container = document.getElementById('strankaDatumContainer');
              if (container) container.style.display = 'none';
            }

            // Clear case title
            document.getElementById("naziv").value = "";
          } else {
            console.error("Error otvaranje predmeta NOVI_PREDMET:", data.error);
            alert("Greška pri otvaranju predmeta: NOVI_PREDMET " + data.error);
          }
        })
        .catch(error => {
          console.error("CATCH otvaranje predmeta:NOVI_PREDMET", error);
          alert("Došlo je do greške: " + error.message);
        });
    });

    // Update on zaposlenik change
    if (zaposlenikSelect) {
      zaposlenikSelect.addEventListener("change", function() {
        currentValues.zaposlenik = this.value || "DOS";
        updateKlasaValue();
        checkIfPredmetExists();
      });
    }


    // Initial update to set the default state
    updateKlasaValue();
  });


  // Tag selection functionality
  const tagsDropdown = document.getElementById("tagsDropdown");
  const availableTags = document.getElementById("available-tags");
  const addTagBtn = document.getElementById("add-tag-btn");
  const selectedTagsContainer = document.getElementById("selected-tags");
  const selectedTags = new Set();

  // Track selected option
  let selectedOption = null;

  // Make tag options selectable
  availableTags.addEventListener("click", function(e) {
    if (e.target.classList.contains("tag-option")) {
      // Remove active class from all options
      document.querySelectorAll('.tag-option').forEach(btn => {
        btn.classList.remove('active');
      });

      // Set active class on clicked option
      e.target.classList.add('active');
      selectedOption = e.target;

      // Update dropdown button text
      tagsDropdown.textContent = e.target.textContent;
    }
  });


  // Add tag to selection
  addTagBtn.addEventListener("click", function() {
    if (!selectedOption) return;

    const tagId = selectedOption.dataset.tagId;
    const tagName = selectedOption.textContent;

    if (!selectedTags.has(tagId)) {
      selectedTags.add(tagId);

      // Create selected tag badge
      const tagElement = document.createElement("span");
      tagElement.className = "badge bg-primary rounded-pill p-2 d-flex align-items-center";
      tagElement.dataset.tagId = tagId;
      tagElement.innerHTML = `
            ${tagName}
            <button type="button" class="btn-close btn-close-white ms-2" aria-label="Remove"></button>
        `;

      selectedTagsContainer.appendChild(tagElement);

      // Reset selection
      selectedOption.classList.remove('active');
      selectedOption = null;
      tagsDropdown.textContent = "Odaberi oznake";
    }
  });

  // Remove tag from selection
  selectedTagsContainer.addEventListener("click", function(e) {
    if (e.target.classList.contains("btn-close")) {
      const tagElement = e.target.closest(".badge");
      const tagId = tagElement.dataset.tagId;

      selectedTags.delete(tagId);
      tagElement.remove();
    }
  });
  //TODO CHECK if documents in db actually exist



  // document.querySelector('[data-action="generate_pdf"]').addEventListener('click', function() { // TODO ostavi za kasnije (( RADI ))
  //   const generatePdfUrl = '< ?php echo DOL_URL_ROOT; ?>/custom/seup/class/generate_pdf.php';

  //   console.log("Sending request to: " + generatePdfUrl);

  //   fetch(generatePdfUrl, {
  //       method: 'POST'
  //     })
  //     .then(response => response.json())
  //     .then(data => {
  //       console.log("Response data:", data);
  //       if (data.success && data.file) {
  //         const url = new URL(data.file, window.location.origin);
  //         const filename = url.searchParams.get('file');
  //         // Open the generated PDF in a new tab
  //         /*const downloadUrl = '< ?php echo DOL_URL_ROOT; ?>/custom/seup/download_temp_pdf.php?file=' + encodeURIComponent(filename); */
  //         window.open(data.file, '_blank'); // just open the `document.php?modulepart=temp&file=...` URL directly
  //       } else {
  //         throw new Error(data.error || 'PDF generation failed.');
  //       }
  //     })
  //     .catch(error => {
  //       console.error('PDF generation error:', error);
  //       alert('PDF generation failed: ' + error.message);
  //     });
  // });
</script>