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
 *	\file       seup/klasifikacijske_oznake.php
 *	\ingroup    seup
 *	\brief      List of classification marks
 */

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
    $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"] . "/main.inc.php";
}
// ... [rest of environment setup remains unchanged] ...

// Libraries
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';

// Local classes
require_once __DIR__ . '/../class/predmet_helper.class.php';

// Load translation files
$langs->loadLangs(array("seup@seup"));

// Security check
$socid = GETPOST('socid', 'int');
if (isset($user->socid) && $user->socid > 0) {
    $action = '';
    $socid = $user->socid;
}

// Fetch sorting parameters
$sortField = GETPOST('sort', 'aZ09') ?: 'ID_klasifikacijske_oznake';
$sortOrder = GETPOST('order', 'aZ09') ?: 'ASC';

// Validate sort fields
$allowedSortFields = [
    'ID_klasifikacijske_oznake',
    'klasa_broj',
    'sadrzaj',
    'dosje_broj',
    'vrijeme_cuvanja',
    'opis_klasifikacijske_oznake'
];

if (!in_array($sortField, $allowedSortFields)) {
    $sortField = 'ID_klasifikacijske_oznake';
}
$sortOrder = ($sortOrder === 'ASC') ? 'ASC' : 'DESC';

// Use specialized helper for classification marks
$orderByClause = Predmet_helper::buildKlasifikacijaOrderBy($sortField, $sortOrder, 'ko');

// Fetch all classification marks // TODO definiraj kriterij selecta (user ustanova.....)
$sql = "SELECT 
            ko.ID_klasifikacijske_oznake,
            ko.klasa_broj,
            ko.sadrzaj,
            ko.dosje_broj,
            ko.vrijeme_cuvanja,
            ko.opis_klasifikacijske_oznake
        FROM " . MAIN_DB_PREFIX . "a_klasifikacijska_oznaka ko
        {$orderByClause}";

$resql = $db->query($sql);
$oznake = [];
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        $oznake[] = $obj;
    }
}

// Generate HTML table
$tableHTML = '<div class="table-responsive">';
$tableHTML .= '<table class="table table-hover table-striped">';
$tableHTML .= '<thead class="table-light">';
$tableHTML .= '<tr>';

// Function to generate sortable header
function sortableHeader($field, $label, $currentSort, $currentOrder)
{
    $newOrder = ($currentSort === $field && $currentOrder === 'DESC') ? 'ASC' : 'DESC';
    $icon = '';

    if ($currentSort === $field) {
        $icon = ($currentOrder === 'ASC')
            ? ' <i class="fas fa-arrow-up"></i>'
            : ' <i class="fas fa-arrow-down"></i>';
    }

    return '<th class="sortable-header">' .
        '<a href="?sort=' . $field . '&order=' . $newOrder . '">' .
        $label . $icon .
        '</a></th>';
}

// Generate sortable headers
$tableHTML .= sortableHeader('ID_klasifikacijske_oznake', $langs->trans('ID'), $sortField, $sortOrder);
$tableHTML .= sortableHeader('klasa_broj', $langs->trans('klasaBr'), $sortField, $sortOrder);
$tableHTML .= sortableHeader('sadrzaj', $langs->trans('Sadrzaj'), $sortField, $sortOrder);
$tableHTML .= sortableHeader('dosje_broj', $langs->trans('dosjeBroj'), $sortField, $sortOrder);
$tableHTML .= sortableHeader('vrijeme_cuvanja', $langs->trans('vrijemeCuvanja'), $sortField, $sortOrder);
$tableHTML .= sortableHeader('opis_klasifikacijske_oznake', $langs->trans('Opis'), $sortField, $sortOrder);
$tableHTML .= '<th>' . $langs->trans('Actions') . '</th>';
$tableHTML .= '</tr>';
$tableHTML .= '</thead>';
$tableHTML .= '<tbody>';

if (count($oznake)) {
    foreach ($oznake as $oznaka) {
        $tableHTML .= '<tr>';
        $tableHTML .= '<td>' . $oznaka->ID_klasifikacijske_oznake . '</td>';
        $tableHTML .= '<td>' . $oznaka->klasa_broj . '</td>';
        $tableHTML .= '<td>' . $oznaka->sadrzaj . '</td>';
        $tableHTML .= '<td>' . $oznaka->dosje_broj . '</td>';

        // Handle retention period display
        $retentionDisplay = '';
        if ($oznaka->vrijeme_cuvanja == 0) {
            $retentionDisplay = 'Trajno';
        } else {
            $yearsText = ($oznaka->vrijeme_cuvanja == 1) ?
                $langs->trans('Year') :
                $langs->trans('Years');
            $retentionDisplay = $oznaka->vrijeme_cuvanja . ' ' . $yearsText;
        }
        $tableHTML .= '<td>' . $retentionDisplay . '</td>';

        $tableHTML .= '<td>' . dol_trunc($oznaka->opis_klasifikacijske_oznake, 40) . '</td>';

        // Action buttons
        $tableHTML .= '<td>';
        $tableHTML .= '<div class="btn-group btn-group-sm">';
        $tableHTML .= '<a href="edit_oznaka.php?id=' . $oznaka->ID_klasifikacijske_oznake . '" class="btn btn-outline-secondary" title="' . $langs->trans('Edit') . '">';
        $tableHTML .= '<i class="fas fa-edit"></i>';
        $tableHTML .= '</a>';
        $tableHTML .= '<a href="#" class="btn btn-outline-danger" title="' . $langs->trans('Delete') . '">';
        $tableHTML .= '<i class="fas fa-trash"></i>';
        $tableHTML .= '</a>';
        $tableHTML .= '</div>';
        $tableHTML .= '</td>';

        $tableHTML .= '</tr>';
    }
} else {
    $tableHTML .= '<tr><td colspan="7" class="text-center text-muted py-4">';
    $tableHTML .= '<i class="fas fa-tags fa-2x mb-2"></i><br>';
    $tableHTML .= $langs->trans('NoClassificationMarks');
    $tableHTML .= '</td></tr>';
}

$tableHTML .= '</tbody>';
$tableHTML .= '</table>';
$tableHTML .= '</div>'; // table-responsive

$form = new Form($db);
llxHeader("", $langs->trans("ClassificationMarks"), '', '', 0, 0, '', '', '', 'mod-seup page-oznake');

// === BOOTSTRAP CDN ===
print '<meta name="viewport" content="width=device-width, initial-scale=1">';
print '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet">';
print '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">';
print '<link href="/custom/seup/css/style.css" rel="stylesheet">';

print '<div class="container mt-5 shadow-sm p-3 mb-5 bg-body rounded">';
print '<div class="p-3 border rounded">';
print '<div class="d-flex justify-content-between align-items-center mb-4">';
print '<h4 class="mb-0">' . $langs->trans('ClassificationMarks') . '</h4>';
print '<button type="button" class="btn btn-primary btn-sm" id="novaOznakaBtn">';
print '<i class="fas fa-plus me-1"></i> ' . $langs->trans('NewClassificationMark'); // TODO napravi link na Postavke
print '</button>';
print '</div>';
print $tableHTML;
print '<div class="mt-3 d-flex justify-content-between">';
print '<div class="text-muted small">';
print '<i class="fas fa-info-circle me-1"></i> ' . $langs->trans('ShowingMarks', count($oznake));
print '</div>';
print '</div>';
print '</div>'; // p-3 border rounded
print '</div>'; // container

// Bootstrap JS
print '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>';
?>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        document.getElementById("novaOznakaBtn").addEventListener("click", function() {
            window.location.href = "nova_oznaka.php";
        });
    });
</script>

<?php
llxFooter();
$db->close();
