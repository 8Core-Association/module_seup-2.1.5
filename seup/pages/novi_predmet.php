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
  $availableTagsHTML .= '<div class="seup-tag-option" data-tag-id="' . $tag->rowid . '">';
  $availableTagsHTML .= '<i class="fas fa-tag me-2"></i>' . htmlspecialchars($tag->tag);
  $availableTagsHTML .= '</div>';
}

// Potrebno za kreiranje klase predmeta
// Inicijalno punjenje podataka za potrebe klase
$klasaOptions = '';
$zaposlenikOptions = '';
$code_ustanova = '';

$klasa_text = 'KLASA: OZN-SAD/GOD-DOS/RBR';
$klasaMapJson = '';

Predmet_helper::fetchDropdownData($db, $langs, $klasaOptions, $zaposlenikOptions, $klasaMapJson);

// Modern design assets
print '<meta name="viewport" content="width=device-width, initial-scale=1">';
print '<link rel="preconnect" href="https://fonts.googleapis.com">';
print '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
print '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">';
print '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">';
print '<link href="/custom/seup/css/seup-modern.css" rel="stylesheet">';
print '<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />';
print '<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>';
print '<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>';

// Main hero section
print '<main class="seup-settings-hero">';

// Copyright footer
print '<footer class="seup-footer">';
print '<div class="seup-footer-content">';
print '<div class="seup-footer-left">';
print '<p>Sva prava pridržana © <a href="https://8core.hr" target="_blank" rel="noopener">8Core Association</a> 2014 - ' . date('Y') . '</p>';
print '</div>';
print '<div class="seup-footer-right">';
print '<p class="seup-version">SEUP v.14.0.4</p>';
print '</div>';
print '</div>';
print '</footer>';

// Floating background elements
print '<div class="seup-floating-elements">';
for ($i = 1; $i <= 5; $i++) {
    print '<div class="seup-floating-element"></div>';
}
print '</div>';

print '<div class="seup-settings-content">';

// Header section
print '<div class="seup-settings-header">';
print '<h1 class="seup-settings-title">Novi Predmet</h1>';
print '<p class="seup-settings-subtitle">Kreirajte novi predmet s klasifikacijskim oznakama i povezanim dokumentima</p>';
print '</div>';

// Klasa display section
print '<div class="seup-klasa-display animate-fade-in-up">';
print '<div class="seup-klasa-content">';
print '<div class="seup-klasa-icon"><i class="fas fa-tag"></i></div>';
print '<div class="seup-klasa-text">';
print '<h4 class="seup-klasa-title">Trenutna Klasa</h4>';
print '<p id="klasa-value" class="seup-klasa-value">' . $klasa_text . '</p>';
print '</div>';
print '</div>';
print '</div>';

// Form grid
print '<div class="seup-form-container">';

// Left column - Form fields
print '<div class="seup-settings-card animate-fade-in-up">';
print '<div class="seup-card-header">';
print '<div class="seup-card-icon"><i class="fas fa-edit"></i></div>';
print '<h3 class="seup-card-title">Parametri Predmeta</h3>';
print '<p class="seup-card-description">Odaberite klasifikacijske oznake i osnovne podatke</p>';
print '</div>';

print '<form method="post" action="' . $_SERVER['PHP_SELF'] . '" class="seup-form">';

print '<div class="seup-form-grid seup-grid-3">';
print '<div class="seup-form-group">';
print '<label for="klasa_br" class="seup-label"><i class="fas fa-layer-group me-2"></i>Klasa broj</label>';
print '<select name="klasa_br" id="klasa_br" class="seup-select">';
print $klasaOptions;
print '</select>';
print '</div>';

print '<div class="seup-form-group">';
print '<label for="sadrzaj" class="seup-label"><i class="fas fa-list me-2"></i>Sadržaj</label>';
print '<select name="sadrzaj" id="sadrzaj" class="seup-select" data-placeholder="' . $langs->trans("Odaberi Sadrzaj") . '">';
print '<option value="">' . $langs->trans("Odaberi Sadrzaj") . '</option>';
print '</select>';
print '</div>';

print '<div class="seup-form-group">';
print '<label for="dosjeBroj" class="seup-label"><i class="fas fa-folder me-2"></i>Dosje broj</label>';
print '<select name="dosjeBroj" id="dosjeBroj" class="seup-select" data-placeholder="' . $langs->trans("Odaberi Dosje Broj") . '">';
print '<option value="">' . $langs->trans("Odaberi Dosje Broj") . '</option>';
print '</select>';
print '</div>';
print '</div>';

print '<div class="seup-form-grid">';
print '<div class="seup-form-group">';
print '<label for="zaposlenik" class="seup-label"><i class="fas fa-user me-2"></i>Zaposlenik</label>';
print '<select class="seup-select" id="zaposlenik" name="zaposlenik" required>';
print $zaposlenikOptions;
print '</select>';
print '</div>';

print '<div class="seup-form-group">';
print '<label for="stranka" class="seup-label"><i class="fas fa-building me-2"></i>Stranka</label>';
print '<div class="seup-stranka-container">';
print '<select class="seup-select" id="stranka" name="stranka" disabled style="flex:1;"></select>';
print '<div class="seup-checkbox-container">';
print '<input type="checkbox" class="seup-checkbox" id="strankaCheck" autocomplete="off">';
print '<label class="seup-checkbox-label" for="strankaCheck">';
print '<i class="fas fa-user-check me-2"></i>Otvorila stranka?';
print '</label>';
print '</div>';
print '</div>';

// Hidden date containers
print '<div id="strankaDatumContainer" class="seup-date-container" style="display:none;">';
print '<label class="seup-label"><i class="fas fa-calendar me-2"></i>Datum otvaranja od strane stranke</label>';
print '<button type="button" class="seup-date-btn" id="strankaDatumBtn">';
print '<i class="fas fa-calendar-alt me-2"></i>Odaberi datum';
print '</button>';
print '<input type="hidden" name="strankaDatumOtvaranja" id="strankaDatumValue">';
print '<div id="strankaDateError" class="seup-error-message" style="display: none;">Odaberite datum otvaranja predmeta!</div>';
print '</div>';

print '<div id="strankaError" class="seup-error-message" style="display: none;">Odaberite stranku!</div>';
print '</div>';
print '</div>';

print '</form>';
print '</div>';

// Right column - Case details
print '<div class="seup-settings-card animate-fade-in-up">';
print '<div class="seup-card-header">';
print '<div class="seup-card-icon"><i class="fas fa-file-alt"></i></div>';
print '<h3 class="seup-card-title">Detalji Predmeta</h3>';
print '<p class="seup-card-description">Naziv, datum i oznake predmeta</p>';
print '</div>';

print '<div class="seup-form">';
print '<div class="seup-form-group">';
print '<label for="naziv" class="seup-label"><i class="fas fa-heading me-2"></i>Naziv Predmeta</label>';
print '<textarea class="seup-textarea" id="naziv" name="naziv" rows="4" maxlength="500" placeholder="Unesite naziv predmeta (maksimalno 500 znakova)"></textarea>';
print '<div class="seup-char-counter">';
print '<span id="charCount">0</span>/500 znakova';
print '</div>';
print '</div>';

print '<div class="seup-form-group">';
print '<label class="seup-label"><i class="fas fa-calendar me-2"></i>Datum Otvaranja Predmeta</label>';
print '<button type="button" class="seup-date-btn" id="datumOtvaranjaBtn">';
print '<i class="fas fa-calendar-alt me-2"></i>Odaberi datum';
print '</button>';
print '<input type="hidden" name="datumOtvaranja" id="datumOtvaranjaValue">';
print '<small class="seup-help-text">Ostavite prazno za današnji datum</small>';
print '</div>';

print '<div class="seup-form-group">';
print '<label class="seup-label"><i class="fas fa-tags me-2"></i>Oznake</label>';
print '<button type="button" class="seup-tags-btn" id="tagsBtn">';
print '<i class="fas fa-tags me-2"></i>Odaberi oznake';
print '</button>';
print '<div class="seup-selected-tags" id="selected-tags"></div>';
print '</div>';

print '<div class="seup-form-actions">';
print '<button type="button" class="seup-btn seup-btn-primary" id="otvoriPredmetBtn">';
print '<i class="fas fa-plus me-2"></i>Otvori Predmet';
print '</button>';
print '</div>';

print '</div>';
print '</div>';

print '</div>'; // seup-form-container

print '</div>'; // seup-settings-content

print '</main>';

// Date Picker Modal
print '<div id="dateModal" class="seup-modal">';
print '<div class="seup-modal-content">';
print '<div class="seup-modal-header">';
print '<h4 class="seup-modal-title"><i class="fas fa-calendar me-2"></i>Odaberi Datum</h4>';
print '<button type="button" class="seup-modal-close" id="closeDateModal">';
print '<i class="fas fa-times"></i>';
print '</button>';
print '</div>';
print '<div class="seup-modal-body">';
print '<div id="calendar-container"></div>';
print '</div>';
print '<div class="seup-modal-footer">';
print '<button type="button" class="seup-btn seup-btn-secondary" id="cancelDate">Odustani</button>';
print '<button type="button" class="seup-btn seup-btn-primary" id="confirmDate">Potvrdi</button>';
print '</div>';
print '</div>';
print '</div>';

// Tags Modal
print '<div id="tagsModal" class="seup-modal">';
print '<div class="seup-modal-content">';
print '<div class="seup-modal-header">';
print '<h4 class="seup-modal-title"><i class="fas fa-tags me-2"></i>Odaberi Oznake</h4>';
print '<button type="button" class="seup-modal-close" id="closeTagsModal">';
print '<i class="fas fa-times"></i>';
print '</button>';
print '</div>';
print '<div class="seup-modal-body">';
print '<div class="seup-tags-grid" id="available-tags">';
print $availableTagsHTML;
print '</div>';
print '</div>';
print '<div class="seup-modal-footer">';
print '<button type="button" class="seup-btn seup-btn-secondary" id="cancelTags">Odustani</button>';
print '<button type="button" class="seup-btn seup-btn-primary" id="confirmTags">Potvrdi</button>';
print '</div>';
print '</div>';
print '</div>';

// JavaScript for enhanced functionality
print '<script src="/custom/seup/js/seup-modern.js"></script>';

// End of page
llxFooter();
$db->close();
?>

<style>
/* Novi Predmet specific styles */
.seup-klasa-display {
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(10px);
  border-radius: var(--radius-2xl);
  padding: var(--space-6);
  margin-bottom: var(--space-8);
  border: 1px solid rgba(255, 255, 255, 0.2);
  box-shadow: var(--shadow-lg);
}

.seup-klasa-content {
  display: flex;
  align-items: center;
  gap: var(--space-4);
}

.seup-klasa-icon {
  width: 48px;
  height: 48px;
  background: linear-gradient(135deg, var(--accent-500), var(--accent-600));
  border-radius: var(--radius-xl);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 20px;
}

.seup-klasa-text {
  flex: 1;
}

.seup-klasa-title {
  font-size: var(--text-lg);
  font-weight: var(--font-semibold);
  color: var(--secondary-900);
  margin-bottom: var(--space-1);
}

.seup-klasa-value {
  font-size: var(--text-xl);
  font-weight: var(--font-bold);
  color: var(--primary-600);
  font-family: var(--font-family-mono);
  margin: 0;
}

/* Form Container */
.seup-form-container {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-6);
}

/* Stranka Container */
.seup-stranka-container {
  display: flex;
  gap: var(--space-3);
  align-items: flex-start;
}

.seup-checkbox-container {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  white-space: nowrap;
}

.seup-checkbox {
  width: 18px;
  height: 18px;
  accent-color: var(--primary-500);
}

.seup-checkbox-label {
  font-size: var(--text-sm);
  font-weight: var(--font-medium);
  color: var(--secondary-700);
  cursor: pointer;
  display: flex;
  align-items: center;
}

/* Date Container */
.seup-date-container {
  margin-top: var(--space-3);
  padding: var(--space-3);
  background: var(--primary-50);
  border-radius: var(--radius-lg);
  border: 1px solid var(--primary-200);
}

/* Date Button */
.seup-date-btn {
  width: 100%;
  padding: var(--space-3);
  border: 1px solid var(--neutral-300);
  border-radius: var(--radius-lg);
  background: white;
  color: var(--secondary-700);
  font-size: var(--text-base);
  cursor: pointer;
  transition: all var(--transition-fast);
  display: flex;
  align-items: center;
  justify-content: center;
}

.seup-date-btn:hover {
  border-color: var(--primary-500);
  background: var(--primary-50);
  color: var(--primary-700);
}

.seup-date-btn.selected {
  background: var(--primary-500);
  color: white;
  border-color: var(--primary-500);
}

/* Tags Button */
.seup-tags-btn {
  width: 100%;
  padding: var(--space-3);
  border: 1px solid var(--neutral-300);
  border-radius: var(--radius-lg);
  background: white;
  color: var(--secondary-700);
  font-size: var(--text-base);
  cursor: pointer;
  transition: all var(--transition-fast);
  display: flex;
  align-items: center;
  justify-content: center;
}

.seup-tags-btn:hover {
  border-color: var(--accent-500);
  background: var(--accent-50);
  color: var(--accent-700);
}

/* Selected Tags */
.seup-selected-tags {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-2);
  margin-top: var(--space-3);
  min-height: 40px;
  padding: var(--space-2);
  border: 1px dashed var(--neutral-300);
  border-radius: var(--radius-lg);
  background: var(--neutral-50);
}

.seup-selected-tag {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-2) var(--space-3);
  background: var(--accent-100);
  color: var(--accent-800);
  border-radius: var(--radius-md);
  font-size: var(--text-sm);
  font-weight: var(--font-medium);
}

.seup-tag-remove {
  background: none;
  border: none;
  color: var(--accent-600);
  cursor: pointer;
  padding: 0;
  width: 16px;
  height: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  transition: all var(--transition-fast);
}

.seup-tag-remove:hover {
  background: var(--accent-200);
  color: var(--accent-800);
}

/* Character Counter */
.seup-char-counter {
  text-align: right;
  font-size: var(--text-xs);
  color: var(--secondary-500);
  margin-top: var(--space-1);
}

.seup-char-counter.warning {
  color: var(--warning-600);
}

.seup-char-counter.danger {
  color: var(--error-600);
}

/* Help Text */
.seup-help-text {
  font-size: var(--text-xs);
  color: var(--secondary-500);
  margin-top: var(--space-1);
  display: flex;
  align-items: center;
}

/* Error Messages */
.seup-error-message {
  color: var(--error-600);
  font-size: var(--text-xs);
  margin-top: var(--space-1);
  display: flex;
  align-items: center;
  gap: var(--space-1);
}

.seup-error-message::before {
  content: '\f071';
  font-family: 'Font Awesome 6 Free';
  font-weight: 900;
}

/* Modal Styles */
.seup-modal {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
  backdrop-filter: blur(4px);
  z-index: var(--z-modal);
  align-items: center;
  justify-content: center;
}

.seup-modal.show {
  display: flex;
}

.seup-modal-content {
  background: white;
  border-radius: var(--radius-2xl);
  box-shadow: var(--shadow-2xl);
  max-width: 500px;
  width: 90%;
  max-height: 80vh;
  overflow: hidden;
  animation: modalSlideIn 0.3s ease-out;
}

.seup-modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: var(--space-6);
  background: linear-gradient(135deg, var(--primary-500), var(--primary-600));
  color: white;
}

.seup-modal-title {
  font-size: var(--text-lg);
  font-weight: var(--font-semibold);
  margin: 0;
}

.seup-modal-close {
  background: none;
  border: none;
  color: white;
  font-size: var(--text-lg);
  cursor: pointer;
  padding: var(--space-2);
  border-radius: var(--radius-md);
  transition: background var(--transition-fast);
}

.seup-modal-close:hover {
  background: rgba(255, 255, 255, 0.2);
}

.seup-modal-body {
  padding: var(--space-6);
  max-height: 400px;
  overflow-y: auto;
}

.seup-modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: var(--space-3);
  padding: var(--space-6);
  background: var(--neutral-50);
  border-top: 1px solid var(--neutral-200);
}

/* Calendar Styles */
#calendar-container {
  background: white;
  border-radius: var(--radius-lg);
  padding: var(--space-4);
}

/* Tags Grid */
.seup-tags-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: var(--space-3);
}

.seup-tag-option {
  padding: var(--space-3);
  border: 1px solid var(--neutral-300);
  border-radius: var(--radius-lg);
  background: white;
  cursor: pointer;
  transition: all var(--transition-fast);
  display: flex;
  align-items: center;
  font-size: var(--text-sm);
  font-weight: var(--font-medium);
  color: var(--secondary-700);
}

.seup-tag-option:hover {
  border-color: var(--accent-500);
  background: var(--accent-50);
  color: var(--accent-700);
  transform: translateY(-2px);
  box-shadow: var(--shadow-md);
}

.seup-tag-option.selected {
  background: var(--accent-500);
  color: white;
  border-color: var(--accent-500);
}

/* Animations */
@keyframes modalSlideIn {
  from {
    opacity: 0;
    transform: scale(0.9) translateY(-20px);
  }
  to {
    opacity: 1;
    transform: scale(1) translateY(0);
  }
}

/* Responsive Design */
@media (max-width: 1024px) {
  .seup-form-container {
    grid-template-columns: 1fr;
  }
  
  .seup-grid-3 {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 768px) {
  .seup-klasa-content {
    flex-direction: column;
    text-align: center;
  }
  
  .seup-form-grid,
  .seup-grid-3 {
    grid-template-columns: 1fr;
  }
  
  .seup-stranka-container {
    flex-direction: column;
    gap: var(--space-2);
  }
  
  .seup-modal-content {
    width: 95%;
    margin: var(--space-4);
  }
  
  .seup-tags-grid {
    grid-template-columns: 1fr;
  }
}
</style>

<script type="text/javascript">
  // Override da se u dropdownu prikazuje hrvatski jezik za placeholder text
  jQuery.fn.select2.defaults.set('language', {
    inputTooShort: function(args) {
      return "Unesite barem 2 znaka za pretraživanje";
    }
  });

  document.addEventListener("DOMContentLoaded", function() {
    // Get the select elements and klasa value element
    const klasaMap = JSON.parse('<?php echo $klasaMapJson; ?>');
    console.log("KlasaMap loaded:", klasaMap);
    
    var klasaSelect = document.getElementById("klasa_br");
    var sadrzajSelect = document.getElementById("sadrzaj");
    const dosjeSelect = document.getElementById("dosjeBroj");
    var zaposlenikSelect = document.getElementById("zaposlenik");
    var klasaValue = document.getElementById("klasa-value");
    const otvoriPredmetBtn = document.getElementById("otvoriPredmetBtn");

    // Character counter for naziv
    const nazivTextarea = document.getElementById("naziv");
    const charCount = document.getElementById("charCount");
    const charCounter = document.querySelector(".seup-char-counter");

    if (nazivTextarea && charCount) {
      nazivTextarea.addEventListener("input", function() {
        const count = this.value.length;
        charCount.textContent = count;
        
        charCounter.classList.remove("warning", "danger");
        if (count > 400) {
          charCounter.classList.add("danger");
        } else if (count > 300) {
          charCounter.classList.add("warning");
        }
      });
    }

    // Modal functionality
    const dateModal = document.getElementById("dateModal");
    const tagsModal = document.getElementById("tagsModal");
    let currentDateTarget = null;
    let selectedTags = new Set();
    let tempSelectedTags = new Set();

    // Date picker functionality
    document.getElementById("datumOtvaranjaBtn").addEventListener("click", function() {
      currentDateTarget = "datumOtvaranja";
      showDateModal();
    });

    document.getElementById("strankaDatumBtn").addEventListener("click", function() {
      currentDateTarget = "strankaDatum";
      showDateModal();
    });

    // Tags functionality
    document.getElementById("tagsBtn").addEventListener("click", function() {
      tempSelectedTags = new Set(selectedTags);
      updateTagsModal();
      showTagsModal();
    });

    function showDateModal() {
      dateModal.classList.add("show");
      initCalendar();
    }

    function hideDateModal() {
      dateModal.classList.remove("show");
    }

    function showTagsModal() {
      tagsModal.classList.add("show");
    }

    function hideTagsModal() {
      tagsModal.classList.remove("show");
    }

    function initCalendar() {
      const container = document.getElementById("calendar-container");
      const today = new Date();
      const currentMonth = today.getMonth();
      const currentYear = today.getFullYear();
      
      container.innerHTML = createCalendarHTML(currentYear, currentMonth);
      
      // Add click handlers to dates
      container.querySelectorAll(".calendar-date").forEach(date => {
        date.addEventListener("click", function() {
          container.querySelectorAll(".calendar-date").forEach(d => d.classList.remove("selected"));
          this.classList.add("selected");
        });
      });
    }

    function createCalendarHTML(year, month) {
      const monthNames = ["Siječanj", "Veljača", "Ožujak", "Travanj", "Svibanj", "Lipanj",
                         "Srpanj", "Kolovoz", "Rujan", "Listopad", "Studeni", "Prosinac"];
      
      const firstDay = new Date(year, month, 1).getDay();
      const daysInMonth = new Date(year, month + 1, 0).getDate();
      
      let html = `
        <div class="calendar-header">
          <h5>${monthNames[month]} ${year}</h5>
        </div>
        <div class="calendar-grid">
          <div class="calendar-day-header">Pon</div>
          <div class="calendar-day-header">Uto</div>
          <div class="calendar-day-header">Sri</div>
          <div class="calendar-day-header">Čet</div>
          <div class="calendar-day-header">Pet</div>
          <div class="calendar-day-header">Sub</div>
          <div class="calendar-day-header">Ned</div>
      `;
      
      // Empty cells for days before month starts
      for (let i = 0; i < (firstDay === 0 ? 6 : firstDay - 1); i++) {
        html += '<div class="calendar-empty"></div>';
      }
      
      // Days of the month
      for (let day = 1; day <= daysInMonth; day++) {
        const isToday = (day === new Date().getDate() && month === new Date().getMonth() && year === new Date().getFullYear());
        html += `<div class="calendar-date ${isToday ? 'today' : ''}" data-date="${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}">${day}</div>`;
      }
      
      html += '</div>';
      return html;
    }

    function updateTagsModal() {
      document.querySelectorAll(".seup-tag-option").forEach(tag => {
        const tagId = tag.dataset.tagId;
        if (tempSelectedTags.has(tagId)) {
          tag.classList.add("selected");
        } else {
          tag.classList.remove("selected");
        }
      });
    }

    function updateSelectedTagsDisplay() {
      const container = document.getElementById("selected-tags");
      container.innerHTML = "";
      
      selectedTags.forEach(tagId => {
        const tagElement = document.querySelector(`[data-tag-id="${tagId}"]`);
        if (tagElement) {
          const tagName = tagElement.textContent.trim();
          const tagBadge = document.createElement("div");
          tagBadge.className = "seup-selected-tag";
          tagBadge.innerHTML = `
            <i class="fas fa-tag me-1"></i>
            ${tagName}
            <button type="button" class="seup-tag-remove" data-tag-id="${tagId}">
              <i class="fas fa-times"></i>
            </button>
          `;
          container.appendChild(tagBadge);
        }
      });
      
      // Update button text
      const tagsBtn = document.getElementById("tagsBtn");
      if (selectedTags.size > 0) {
        tagsBtn.innerHTML = `<i class="fas fa-tags me-2"></i>Odabrano: ${selectedTags.size} oznaka`;
        tagsBtn.classList.add("selected");
      } else {
        tagsBtn.innerHTML = `<i class="fas fa-tags me-2"></i>Odaberi oznake`;
        tagsBtn.classList.remove("selected");
      }
    }

    // Modal event listeners
    document.getElementById("closeDateModal").addEventListener("click", hideDateModal);
    document.getElementById("cancelDate").addEventListener("click", hideDateModal);
    document.getElementById("closeTagsModal").addEventListener("click", hideTagsModal);
    document.getElementById("cancelTags").addEventListener("click", hideTagsModal);

    document.getElementById("confirmDate").addEventListener("click", function() {
      const selectedDate = document.querySelector(".calendar-date.selected");
      if (selectedDate) {
        const dateValue = selectedDate.dataset.date;
        const [year, month, day] = dateValue.split("-");
        const formattedDate = `${day}.${month}.${year}`;
        
        if (currentDateTarget === "datumOtvaranja") {
          document.getElementById("datumOtvaranjaValue").value = dateValue;
          document.getElementById("datumOtvaranjaBtn").innerHTML = `<i class="fas fa-calendar-check me-2"></i>${formattedDate}`;
          document.getElementById("datumOtvaranjaBtn").classList.add("selected");
        } else if (currentDateTarget === "strankaDatum") {
          document.getElementById("strankaDatumValue").value = dateValue;
          document.getElementById("strankaDatumBtn").innerHTML = `<i class="fas fa-calendar-check me-2"></i>${formattedDate}`;
          document.getElementById("strankaDatumBtn").classList.add("selected");
        }
      }
      hideDateModal();
    });

    document.getElementById("confirmTags").addEventListener("click", function() {
      selectedTags = new Set(tempSelectedTags);
      updateSelectedTagsDisplay();
      hideTagsModal();
    });

    // Tag selection in modal
    document.addEventListener("click", function(e) {
      if (e.target.closest(".seup-tag-option")) {
        const tagOption = e.target.closest(".seup-tag-option");
        const tagId = tagOption.dataset.tagId;
        
        if (tempSelectedTags.has(tagId)) {
          tempSelectedTags.delete(tagId);
          tagOption.classList.remove("selected");
        } else {
          tempSelectedTags.add(tagId);
          tagOption.classList.add("selected");
        }
      }
      
      // Remove tag from selected
      if (e.target.closest(".seup-tag-remove")) {
        const tagId = e.target.closest(".seup-tag-remove").dataset.tagId;
        selectedTags.delete(tagId);
        updateSelectedTagsDisplay();
      }
    });

    // Close modals on outside click
    window.addEventListener("click", function(e) {
      if (e.target === dateModal) hideDateModal();
      if (e.target === tagsModal) hideTagsModal();
    });

    // Stranka autocomplete functionality
    const strankaInput = document.getElementById('stranka');
    let lastSearchTerm = '';

    document.getElementById('strankaCheck').addEventListener('change', function() {
      const selectField = document.getElementById('stranka');
      const errorDiv = document.getElementById('strankaError');
      const container = document.getElementById('strankaDatumContainer');

      if (this.checked) {
        selectField.disabled = false;
        selectField.required = true;

        if (!selectField.hasAttribute('data-select2-id')) {
          jQuery(selectField).select2({
            placeholder: "OIB ili naziv stranke",
            allowClear: true,
            ajax: {
              url: 'novi_predmet.php?ajax=autocomplete_stranka',
              dataType: 'json',
              delay: 300,
              data: function(params) {
                return { term: params.term };
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

        errorDiv.style.display = 'none';
        selectField.classList.remove('is-invalid');
        container.style.display = 'block';
        selectField.focus();
      } else {
        if (selectField.hasAttribute('data-select2-id')) {
          $(selectField).select2('destroy');
        }
        selectField.disabled = true;
        selectField.required = false;
        selectField.innerHTML = '';
        errorDiv.style.display = 'none';
        selectField.classList.remove('is-invalid');
        container.style.display = 'none';
        
        // Clear date
        document.getElementById("strankaDatumValue").value = '';
        document.getElementById("strankaDatumBtn").innerHTML = '<i class="fas fa-calendar-alt me-2"></i>Odaberi datum';
        document.getElementById("strankaDatumBtn").classList.remove("selected");
      }
    });

    // Check if elements are present
    if (!klasaSelect || !sadrzajSelect || !zaposlenikSelect || !klasaValue) {
      console.error("Required elements not found in DOM.");
      return;
    }

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

      const updatedText = `KLASA: ${klasa}-${sadrzaj}/${year}-${selectedDosje}/${rbr}`;
      klasaValue.textContent = updatedText;
    }

    function checkIfPredmetExists() {
      var klasa = klasaSelect.value || "OZN";
      var sadrzaj = sadrzajSelect.value || "SAD";
      var dosje_br = dosjeSelect.value || "DOS";
      
      if (klasa !== "OZN" && sadrzaj !== "SAD" && dosje_br !== "DOS") {
        fetch(
            "novi_predmet.php?ajax=1&" +
            "klasa_br=" + encodeURIComponent(klasa) +
            "&sadrzaj=" + encodeURIComponent(sadrzaj) +
            "&dosje_br=" + encodeURIComponent(dosje_br) +
            "&god=" + encodeURIComponent(year), {
              headers: { "Accept": "application/json" }
            }
          )
          .then(response => response.json())
          .then(data => {
            if (data.status === "exists" || data.status === "inserted") {
              currentValues.rbr = data.next_rbr;
              updateKlasaValue();
            }
          })
          .catch(error => console.error("Error checking predmet:", error));
      }
    }

    function resetKlasaDisplay() {
      currentValues = { klasa: "", sadrzaj: "", dosje: "", rbr: "1", zaposlenik: "" };
      klasaSelect.value = "";
      sadrzajSelect.innerHTML = `<option value="">${sadrzajSelect.dataset.placeholder}</option>`;
      dosjeSelect.innerHTML = `<option value="">${dosjeSelect.dataset.placeholder}</option>`;
      zaposlenikSelect.value = "";
      updateKlasaValue();
    }

    // Update on klasa change
    if (klasaSelect) {
      klasaSelect.addEventListener("change", function() {
        currentValues.klasa = this.value || "";
        currentValues.dosje = "";

        sadrzajSelect.innerHTML = `<option value="">${sadrzajSelect.dataset.placeholder}</option>`;
        dosjeSelect.innerHTML = `<option value="">${dosjeSelect.dataset.placeholder}</option>`;

        if (this.value && klasaMap[this.value]) {
          const sadrzajValues = Object.keys(klasaMap[this.value]);
          sadrzajValues.forEach(sadrzaj => {
            const option = new Option(sadrzaj, sadrzaj);
            sadrzajSelect.appendChild(option);
          });
        }

        updateKlasaValue();
        checkIfPredmetExists();
      });
    }

    // Update on sadrzaj change
    if (sadrzajSelect) {
      sadrzajSelect.addEventListener("change", function() {
        dosjeSelect.innerHTML = `<option value="">${dosjeSelect.dataset.placeholder}</option>`;
        currentValues.sadrzaj = this.value || "SAD";
        currentValues.dosje = "";

        const klasa = klasaSelect.value;
        const sadrzaj = this.value;
        
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

    if (dosjeSelect) {
      dosjeSelect.addEventListener("change", function() {
        currentValues.dosje = this.value || "";
        updateKlasaValue();
        checkIfPredmetExists();
      });
    }

    // Submit handler
    otvoriPredmetBtn.addEventListener("click", function() {
      const klasa = klasaSelect.value;
      const sadrzaj = sadrzajSelect.value;
      const dosje = dosjeSelect.value;
      const zaposlenik = zaposlenikSelect.value;
      const naziv = document.getElementById("naziv").value;

      const strankaCheckbox = document.getElementById('strankaCheck');
      const strankaField = document.getElementById('stranka');
      const strankaError = document.getElementById('strankaError');

      strankaField.classList.remove('is-invalid');
      strankaError.style.display = 'none';

      let isValid = true;
      const missingFields = [];

      if (!klasa) missingFields.push("Klasa broj");
      if (!sadrzaj) missingFields.push("Sadržaj");
      if (!dosje) missingFields.push("Dosje broj");
      if (!zaposlenik) missingFields.push("Zaposlenik");
      if (!naziv.trim()) missingFields.push("Naziv predmeta");

      const strankaDateError = document.getElementById('strankaDateError');
      if (strankaDateError) {
        strankaDateError.style.display = 'none';
      }

      if (strankaCheckbox.checked) {
        if (!strankaField.value) {
          isValid = false;
          strankaField.classList.add('is-invalid');
          strankaError.style.display = 'block';
          strankaField.focus();
        }

        const strankaDateValue = document.getElementById('strankaDatumValue').value;
        if (!strankaDateValue) {
          isValid = false;
          if (strankaDateError) {
            strankaDateError.style.display = 'block';
          }
        }
      }

      if (missingFields.length > 0) {
        isValid = false;
        const errorMessage = "Molimo vas da popunite sva obavezna polja:\n\n" +
          missingFields.map(field => `- ${field}`).join("\n");
        alert(errorMessage);
      }

      if (!isValid) {
        return;
      }

      // Add loading state
      this.classList.add('seup-loading');
      this.disabled = true;

      const formData = new FormData();
      formData.append("action", "otvori_predmet");
      formData.append("klasa_br", klasa);
      formData.append("sadrzaj", sadrzaj);
      formData.append("dosje_broj", dosje);
      formData.append("zaposlenik", zaposlenik);
      formData.append("god", year);
      formData.append("naziv", naziv);

      if (strankaCheckbox.checked) {
        formData.append("stranka", strankaField.value.trim());
        const strankaDateValue = document.getElementById('strankaDatumValue').value;
        if (strankaDateValue) {
          formData.append("strankaDatumOtvaranja", strankaDateValue);
        }
      }

      const datumValue = document.getElementById('datumOtvaranjaValue').value;
      let datumOtvaranjaTimestamp = null;

      if (datumValue) {
        const now = new Date();
        datumOtvaranjaTimestamp = `${datumValue} ${now.getHours().toString().padStart(2, '0')}:${now.getMinutes().toString().padStart(2, '0')}:${now.getSeconds().toString().padStart(2, '0')}`;
      } else {
        const now = new Date();
        datumOtvaranjaTimestamp = `${now.getFullYear()}-${(now.getMonth() + 1).toString().padStart(2, '0')}-${now.getDate().toString().padStart(2, '0')} ${now.getHours().toString().padStart(2, '0')}:${now.getMinutes().toString().padStart(2, '0')}:${now.getSeconds().toString().padStart(2, '0')}`;
      }

      formData.append("datumOtvaranja", datumOtvaranjaTimestamp);

      selectedTags.forEach(tagId => {
        formData.append("tags[]", tagId);
      });

      fetch("novi_predmet.php", {
          method: "POST",
          body: formData
        })
        .then(async response => {
          const responseText = await response.text();
          try {
            return JSON.parse(responseText);
          } catch (e) {
            throw new Error(`Invalid JSON response: ${responseText.substring(0, 100)}...`);
          }
        })
        .then(data => {
          if (data.success) {
            // Show success message
            showMessage("Predmet je uspješno otvoren!", "success");

            // Reset form
            resetKlasaDisplay();
            document.getElementById("naziv").value = "";
            
            // Reset dates
            document.getElementById("datumOtvaranjaValue").value = '';
            document.getElementById("datumOtvaranjaBtn").innerHTML = '<i class="fas fa-calendar-alt me-2"></i>Odaberi datum';
            document.getElementById("datumOtvaranjaBtn").classList.remove("selected");
            
            document.getElementById("strankaDatumValue").value = '';
            document.getElementById("strankaDatumBtn").innerHTML = '<i class="fas fa-calendar-alt me-2"></i>Odaberi datum';
            document.getElementById("strankaDatumBtn").classList.remove("selected");

            // Reset stranka
            const strankaCheckbox = document.getElementById('strankaCheck');
            const strankaField = document.getElementById('stranka');
            
            if (strankaCheckbox && strankaField) {
              strankaCheckbox.checked = false;
              if (strankaField.hasAttribute('data-select2-id')) {
                $(strankaField).val(null).trigger('change');
              } else {
                strankaField.value = '';
              }
              strankaField.disabled = true;
              strankaField.classList.remove('is-invalid');
              document.getElementById('strankaError').style.display = 'none';
              document.getElementById('strankaDatumContainer').style.display = 'none';
            }

            // Reset tags
            selectedTags.clear();
            updateSelectedTagsDisplay();
            
            // Reset character counter
            if (charCount) charCount.textContent = "0";
            if (charCounter) charCounter.classList.remove("warning", "danger");
            
          } else {
            showMessage("Greška pri otvaranju predmeta: " + data.error, "error");
          }
        })
        .catch(error => {
          showMessage("Došlo je do greške: " + error.message, "error");
        })
        .finally(() => {
          // Remove loading state
          this.classList.remove('seup-loading');
          this.disabled = false;
        });
    });

    // Initial update
    updateKlasaValue();
    updateSelectedTagsDisplay();
  });

  // Toast message function
  function showMessage(message, type = 'success', duration = 5000) {
    let messageEl = document.querySelector('.seup-message-toast');
    if (!messageEl) {
      messageEl = document.createElement('div');
      messageEl.className = 'seup-message-toast';
      document.body.appendChild(messageEl);
    }

    messageEl.className = `seup-message-toast seup-message-${type} show`;
    messageEl.innerHTML = `
      <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>
      ${message}
    `;

    setTimeout(() => {
      messageEl.classList.remove('show');
    }, duration);
  }
</script>

<style>
/* Calendar Styles */
.calendar-header {
  text-align: center;
  margin-bottom: var(--space-4);
  padding-bottom: var(--space-3);
  border-bottom: 1px solid var(--neutral-200);
}

.calendar-header h5 {
  font-size: var(--text-lg);
  font-weight: var(--font-semibold);
  color: var(--secondary-900);
  margin: 0;
}

.calendar-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: var(--space-1);
}

.calendar-day-header {
  padding: var(--space-2);
  text-align: center;
  font-size: var(--text-xs);
  font-weight: var(--font-semibold);
  color: var(--secondary-600);
  text-transform: uppercase;
}

.calendar-date {
  padding: var(--space-2);
  text-align: center;
  cursor: pointer;
  border-radius: var(--radius-md);
  transition: all var(--transition-fast);
  font-size: var(--text-sm);
  font-weight: var(--font-medium);
}

.calendar-date:hover {
  background: var(--primary-100);
  color: var(--primary-700);
}

.calendar-date.today {
  background: var(--primary-500);
  color: white;
}

.calendar-date.selected {
  background: var(--accent-500);
  color: white;
}

.calendar-empty {
  padding: var(--space-2);
}

/* Toast Messages */
.seup-message-toast {
  position: fixed;
  top: 20px;
  right: 20px;
  padding: var(--space-4) var(--space-6);
  border-radius: var(--radius-lg);
  color: white;
  font-weight: var(--font-medium);
  box-shadow: var(--shadow-xl);
  transform: translateX(400px);
  transition: transform var(--transition-normal);
  z-index: var(--z-tooltip);
  max-width: 400px;
}

.seup-message-toast.show {
  transform: translateX(0);
}

.seup-message-success {
  background: linear-gradient(135deg, var(--success-500), var(--success-600));
}

.seup-message-error {
  background: linear-gradient(135deg, var(--error-500), var(--error-600));
}

/* Loading state for buttons */
.seup-btn.seup-loading {
  position: relative;
  color: transparent;
}

.seup-btn.seup-loading::after {
  content: '';
  position: absolute;
  top: 50%;
  left: 50%;
  width: 16px;
  height: 16px;
  margin: -8px 0 0 -8px;
  border: 2px solid transparent;
  border-top: 2px solid currentColor;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}
</style>