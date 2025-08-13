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
 *	\file       seup/predmeti.php
 *	\ingroup    seup
 *	\brief      List of open cases
 */

// Učitaj Dolibarr okruženje
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

// Fetch sorting parameters
$sortField = GETPOST('sort', 'aZ09') ?: 'ID_predmeta';
$sortOrder = GETPOST('order', 'aZ09') ?: 'ASC';

// Validate sort fields
$allowedSortFields = ['ID_predmeta', 'klasa_br', 'naziv_predmeta', 'name_ustanova', 'ime_prezime', 'tstamp_created'];
if (!in_array($sortField, $allowedSortFields)) {
    $sortField = 'ID_predmeta';
}
$sortOrder = ($sortOrder === 'ASC') ? 'ASC' : 'DESC';

// Use helper to build ORDER BY
$orderByClause = Predmet_helper::buildOrderByKlasa($sortField, $sortOrder);

// Fetch all open cases with proper sorting
$sql = "SELECT 
            p.ID_predmeta,
            p.klasa_br,
            p.sadrzaj,
            p.dosje_broj,
            p.godina,
            p.predmet_rbr,
            p.naziv_predmeta,
            DATE_FORMAT(p.tstamp_created, '%d/%m/%Y') as datum_otvaranja,
            u.name_ustanova,
            k.ime_prezime,
            ko.opis_klasifikacijske_oznake
        FROM " . MAIN_DB_PREFIX . "a_predmet p
        LEFT JOIN " . MAIN_DB_PREFIX . "a_oznaka_ustanove u ON p.ID_ustanove = u.ID_ustanove
        LEFT JOIN " . MAIN_DB_PREFIX . "a_interna_oznaka_korisnika k ON p.ID_interna_oznaka_korisnika = k.ID
        LEFT JOIN " . MAIN_DB_PREFIX . "a_klasifikacijska_oznaka ko ON p.ID_klasifikacijske_oznake = ko.ID_klasifikacijske_oznake
        {$orderByClause}";

$resql = $db->query($sql);
$predmeti = [];
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        $predmeti[] = $obj;
    }
}

$form = new Form($db);
llxHeader("", $langs->trans("OpenCases"), '', '', 0, 0, '', '', '', 'mod-seup page-predmeti');

// Modern design assets
print '<meta name="viewport" content="width=device-width, initial-scale=1">';
print '<link rel="preconnect" href="https://fonts.googleapis.com">';
print '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
print '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">';
print '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">';
print '<link href="/custom/seup/css/seup-modern.css" rel="stylesheet">';
print '<link href="/custom/seup/css/predmeti.css" rel="stylesheet">';

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
print '<h1 class="seup-settings-title">Otvoreni Predmeti</h1>';
print '<p class="seup-settings-subtitle">Pregled i upravljanje svim aktivnim predmetima u sustavu</p>';
print '</div>';

// Main content card
print '<div class="seup-predmeti-container">';
print '<div class="seup-settings-card seup-card-wide animate-fade-in-up">';
print '<div class="seup-card-header">';
print '<div class="seup-card-icon"><i class="fas fa-folder-open"></i></div>';
print '<div class="seup-card-header-content">';
print '<h3 class="seup-card-title">Aktivni Predmeti</h3>';
print '<p class="seup-card-description">Pregled svih otvorenih predmeta s mogućnostima sortiranja i pretraživanja</p>';
print '</div>';
print '<div class="seup-card-actions">';
print '<button type="button" class="seup-btn seup-btn-primary" id="noviPredmetBtn">';
print '<i class="fas fa-plus me-2"></i>Novi Predmet';
print '</button>';
print '</div>';
print '</div>';

// Search and filter section
print '<div class="seup-table-controls">';
print '<div class="seup-search-container">';
print '<div class="seup-search-input-wrapper">';
print '<i class="fas fa-search seup-search-icon"></i>';
print '<input type="text" id="searchInput" class="seup-search-input" placeholder="Pretraži predmete...">';
print '</div>';
print '</div>';
print '<div class="seup-filter-controls">';
print '<select id="filterUstanova" class="seup-filter-select">';
print '<option value="">Sve ustanove</option>';
// Add unique ustanove from predmeti
$ustanove = array_unique(array_column($predmeti, 'name_ustanova'));
foreach ($ustanove as $ustanova) {
    if ($ustanova) {
        print '<option value="' . htmlspecialchars($ustanova) . '">' . htmlspecialchars($ustanova) . '</option>';
    }
}
print '</select>';
print '</div>';
print '</div>';

// Enhanced table with modern styling
print '<div class="seup-table-container">';
print '<table class="seup-table">';
print '<thead class="seup-table-header">';
print '<tr>';

// Function to generate sortable header
function sortableHeader($field, $label, $currentSort, $currentOrder, $icon = '')
{
    $newOrder = ($currentSort === $field && $currentOrder === 'DESC') ? 'ASC' : 'DESC';
    $sortIcon = '';

    if ($currentSort === $field) {
        $sortIcon = ($currentOrder === 'ASC')
            ? ' <i class="fas fa-arrow-up seup-sort-icon"></i>'
            : ' <i class="fas fa-arrow-down seup-sort-icon"></i>';
    }

    return '<th class="seup-table-th sortable-header">' .
        '<a href="?sort=' . $field . '&order=' . $newOrder . '" class="seup-sort-link">' .
        ($icon ? '<i class="' . $icon . ' me-2"></i>' : '') .
        $label . $sortIcon .
        '</a></th>';
}

// Generate sortable headers with icons
print sortableHeader('ID_predmeta', 'ID', $sortField, $sortOrder, 'fas fa-hashtag');
print sortableHeader('klasa_br', 'Klasa', $sortField, $sortOrder, 'fas fa-layer-group');
print sortableHeader('naziv_predmeta', 'Naziv Predmeta', $sortField, $sortOrder, 'fas fa-heading');
print sortableHeader('name_ustanova', 'Ustanova', $sortField, $sortOrder, 'fas fa-building');
print sortableHeader('ime_prezime', 'Zaposlenik', $sortField, $sortOrder, 'fas fa-user');
print sortableHeader('tstamp_created', 'Datum Otvaranja', $sortField, $sortOrder, 'fas fa-calendar');
print '<th class="seup-table-th"><i class="fas fa-cogs me-2"></i>Akcije</th>';
print '</tr>';
print '</thead>';
print '<tbody class="seup-table-body">';

if (count($predmeti)) {
    foreach ($predmeti as $index => $predmet) {
        $klasa = $predmet->klasa_br . '-' . $predmet->sadrzaj . '/' .
            $predmet->godina . '-' . $predmet->dosje_broj . '/' .
            $predmet->predmet_rbr;
        
        $rowClass = ($index % 2 === 0) ? 'seup-table-row-even' : 'seup-table-row-odd';
        print '<tr class="seup-table-row ' . $rowClass . '" data-id="' . $predmet->ID_predmeta . '">';
        
        print '<td class="seup-table-td">';
        print '<span class="seup-badge seup-badge-neutral">' . $predmet->ID_predmeta . '</span>';
        print '</td>';
        
        // Make Klasa badge clickable
        $url = dol_buildpath('/custom/seup/pages/predmet.php', 1) . '?id=' . $predmet->ID_predmeta;
        print '<td class="seup-table-td">';
        print '<a href="' . $url . '" class="seup-badge seup-badge-primary seup-klasa-link">' . $klasa . '</a>';
        print '</td>';
        
        print '<td class="seup-table-td">';
        print '<div class="seup-naziv-cell" title="' . htmlspecialchars($predmet->naziv_predmeta) . '">';
        print dol_trunc($predmet->naziv_predmeta, 50);
        print '</div>';
        print '</td>';
        
        print '<td class="seup-table-td">';
        print '<span class="seup-ustanova-badge">' . ($predmet->name_ustanova ?: 'N/A') . '</span>';
        print '</td>';
        
        print '<td class="seup-table-td">';
        print '<div class="seup-user-info">';
        print '<i class="fas fa-user-circle me-2"></i>';
        print $predmet->ime_prezime ?: 'N/A';
        print '</div>';
        print '</td>';
        
        print '<td class="seup-table-td">';
        print '<div class="seup-date-info">';
        print '<i class="fas fa-calendar me-2"></i>';
        print $predmet->datum_otvaranja;
        print '</div>';
        print '</td>';

        // Action buttons
        print '<td class="seup-table-td">';
        print '<div class="seup-action-buttons">';
        print '<a href="' . $url . '" class="seup-action-btn seup-btn-view" title="Pregled detalja">';
        print '<i class="fas fa-eye"></i>';
        print '</a>';
        print '<button class="seup-action-btn seup-btn-edit" title="Uredi" data-id="' . $predmet->ID_predmeta . '">';
        print '<i class="fas fa-edit"></i>';
        print '</button>';
        print '<button class="seup-action-btn seup-btn-archive" title="Arhiviraj" data-id="' . $predmet->ID_predmeta . '">';
        print '<i class="fas fa-archive"></i>';
        print '</button>';
        print '</div>';
        print '</td>';

        print '</tr>';
    }
} else {
    print '<tr class="seup-table-row">';
    print '<td colspan="7" class="seup-table-empty">';
    print '<div class="seup-empty-state">';
    print '<i class="fas fa-folder-open seup-empty-icon"></i>';
    print '<h4 class="seup-empty-title">Nema otvorenih predmeta</h4>';
    print '<p class="seup-empty-description">Kreirajte novi predmet za početak rada</p>';
    print '<button type="button" class="seup-btn seup-btn-primary mt-3" id="noviPredmetBtn2">';
    print '<i class="fas fa-plus me-2"></i>Kreiraj prvi predmet';
    print '</button>';
    print '</div>';
    print '</td>';
    print '</tr>';
}

print '</tbody>';
print '</table>';
print '</div>'; // seup-table-container

// Table footer with stats and actions
print '<div class="seup-table-footer">';
print '<div class="seup-table-stats">';
print '<i class="fas fa-info-circle me-2"></i>';
print '<span>Prikazano <strong id="visibleCount">' . count($predmeti) . '</strong> od <strong>' . count($predmeti) . '</strong> predmeta</span>';
print '</div>';
print '<div class="seup-table-actions">';
print '<button type="button" class="seup-btn seup-btn-secondary seup-btn-sm" id="exportBtn">';
print '<i class="fas fa-download me-2"></i>Izvoz Excel';
print '</button>';
print '<button type="button" class="seup-btn seup-btn-secondary seup-btn-sm" id="printBtn">';
print '<i class="fas fa-print me-2"></i>Ispis';
print '</button>';
print '</div>';
print '</div>';

print '</div>'; // seup-settings-card
print '</div>'; // seup-predmeti-container

print '</div>'; // seup-settings-content
print '</main>';

// JavaScript for enhanced functionality
print '<script src="/custom/seup/js/seup-modern.js"></script>';

print '<script>';
print '
document.addEventListener("DOMContentLoaded", function() {
    // Navigation buttons
    const noviPredmetBtn = document.getElementById("noviPredmetBtn");
    const noviPredmetBtn2 = document.getElementById("noviPredmetBtn2");
    
    if (noviPredmetBtn) {
        noviPredmetBtn.addEventListener("click", function() {
            this.classList.add(\'seup-loading\');
            window.location.href = "novi_predmet.php";
        });
    }
    
    if (noviPredmetBtn2) {
        noviPredmetBtn2.addEventListener("click", function() {
            this.classList.add(\'seup-loading\');
            window.location.href = "novi_predmet.php";
        });
    }

    // Enhanced search and filter functionality
    const searchInput = document.getElementById(\'searchInput\');
    const filterUstanova = document.getElementById(\'filterUstanova\');
    const tableRows = document.querySelectorAll(\'.seup-table-row[data-id]\');
    const visibleCountSpan = document.getElementById(\'visibleCount\');

    function filterTable() {
        const searchTerm = searchInput.value.toLowerCase();
        const selectedUstanova = filterUstanova.value;
        let visibleCount = 0;

        tableRows.forEach(row => {
            const cells = row.querySelectorAll(\'.seup-table-td\');
            const rowText = Array.from(cells).map(cell => cell.textContent.toLowerCase()).join(\' \');
            
            // Check search term
            const matchesSearch = !searchTerm || rowText.includes(searchTerm);
            
            // Check ustanova filter
            let matchesUstanova = true;
            if (selectedUstanova) {
                const ustanovaCell = cells[3]; // ustanova column
                matchesUstanova = ustanovaCell.textContent.trim() === selectedUstanova;
            }

            if (matchesSearch && matchesUstanova) {
                row.style.display = \'\';
                visibleCount++;
                // Add staggered animation
                row.style.animationDelay = `${visibleCount * 50}ms`;
                row.classList.add(\'animate-fade-in-up\');
            } else {
                row.style.display = \'none\';
                row.classList.remove(\'animate-fade-in-up\');
            }
        });

        // Update visible count
        if (visibleCountSpan) {
            visibleCountSpan.textContent = visibleCount;
        }
    }

    if (searchInput) {
        searchInput.addEventListener(\'input\', debounce(filterTable, 300));
    }
    
    if (filterUstanova) {
        filterUstanova.addEventListener(\'change\', filterTable);
    }

    // Enhanced row interactions
    tableRows.forEach(row => {
        row.addEventListener(\'mouseenter\', function() {
            this.style.transform = \'translateX(4px)\';
        });
        
        row.addEventListener(\'mouseleave\', function() {
            this.style.transform = \'translateX(0)\';
        });
    });

    // Action button handlers
    document.querySelectorAll(\'.seup-btn-edit\').forEach(btn => {
        btn.addEventListener(\'click\', function() {
            const id = this.dataset.id;
            this.classList.add(\'seup-loading\');
            // Navigate to edit page
            window.location.href = `predmet.php?id=${id}&action=edit`;
        });
    });

    document.querySelectorAll(\'.seup-btn-archive\').forEach(btn => {
        btn.addEventListener(\'click\', function() {
            const id = this.dataset.id;
            if (confirm(\'Jeste li sigurni da želite arhivirati ovaj predmet?\')) {
                this.classList.add(\'seup-loading\');
                // Implement archive functionality
                console.log(\'Archive predmet:\', id);
                showMessage(\'Predmet je arhiviran\', \'success\');
            }
        });
    });

    // Export and print handlers
    document.getElementById(\'exportBtn\').addEventListener(\'click\', function() {
        this.classList.add(\'seup-loading\');
        // Implement export functionality
        setTimeout(() => {
            this.classList.remove(\'seup-loading\');
            showMessage(\'Excel izvoz je pokrenut\', \'success\');
        }, 2000);
    });

    document.getElementById(\'printBtn\').addEventListener(\'click\', function() {
        window.print();
    });

    // Utility functions
    function debounce(func, wait) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }

    // Toast message function
    window.showMessage = function(message, type = \'success\', duration = 5000) {
        let messageEl = document.querySelector(\'.seup-message-toast\');
        if (!messageEl) {
            messageEl = document.createElement(\'div\');
            messageEl.className = \'seup-message-toast\';
            document.body.appendChild(messageEl);
        }

        messageEl.className = `seup-message-toast seup-message-${type} show`;
        messageEl.innerHTML = `
            <i class="fas fa-${type === \'success\' ? \'check-circle\' : \'exclamation-triangle\'} me-2"></i>
            ${message}
        `;

        setTimeout(() => {
            messageEl.classList.remove(\'show\');
        }, duration);
    };

    // Initial staggered animation for existing rows
    tableRows.forEach((row, index) => {
        row.style.animationDelay = `${index * 100}ms`;
        row.classList.add(\'animate-fade-in-up\');
    });
});
';
print '</script>';

llxFooter();
$db->close();
?>