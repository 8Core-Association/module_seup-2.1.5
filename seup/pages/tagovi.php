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
 *	\file       seup/tagovi.php
 *	\ingroup    seup
 *	\brief      Tagovi page
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

require_once DOL_DOCUMENT_ROOT . '/core/class/html.formfile.class.php';

// Učitaj datoteke prijevoda
$langs->loadLangs(array("seup@seup"));

$action = GETPOST('action', 'aZ09');
$now = dol_now();

// Sigurnosna provjera
$socid = GETPOST('socid', 'int');
if (isset($user->socid) && $user->socid > 0) {
    $action = '';
    $socid = $user->socid;
}

// Process form submission
$error = 0;
$success = 0;
$tag_name = '';

if ($action == 'addtag' && !empty($_POST['tag'])) {
    $tag_name = GETPOST('tag', 'alphanohtml');

    // Validate input
    if (dol_strlen($tag_name) < 2) {
        $error++;
        setEventMessages($langs->trans('ErrorTagTooShort'), null, 'errors');
    } else {
        $db->begin();

        // Check if tag already exists
        $sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . "a_tagovi";
        $sql .= " WHERE tag = '" . $db->escape($tag_name) . "'";
        $sql .= " AND entity = " . $conf->entity;

        $resql = $db->query($sql);
        if ($resql) {
            if ($db->num_rows($resql) > 0) {
                $error++;
                setEventMessages($langs->trans('ErrorTagAlreadyExists'), null, 'errors');
            } else {
                // Insert new tag
                $sql = "INSERT INTO " . MAIN_DB_PREFIX . "a_tagovi";
                $sql .= " (tag, entity, date_creation, fk_user_creat)";
                $sql .= " VALUES ('" . $db->escape($tag_name) . "',";
                $sql .= " " . $conf->entity . ",";
                $sql .= " '" . $db->idate(dol_now()) . "',";
                $sql .= " " . $user->id . ")";

                $resql = $db->query($sql);
                if ($resql) {
                    $db->commit();
                    $success++;
                    $tag_name = ''; // Reset input field
                    setEventMessages($langs->trans('TagAddedSuccessfully'), null, 'mesgs');
                } else {
                    $db->rollback();
                    $error++;
                    setEventMessages($langs->trans('ErrorTagNotAdded') . ' ' . $db->lasterror(), null, 'errors');
                }
            }
        } else {
            $db->rollback();
            $error++;
            setEventMessages($langs->trans('ErrorDatabaseRequest') . ' ' . $db->lasterror(), null, 'errors');
        }
    }
}

if ($action == 'deletetag') {
    $tagid = GETPOST('tagid', 'int');
    if ($tagid > 0) {
        $db->begin();

        // First delete associations in a_predmet_tagovi
        $sql = "DELETE FROM " . MAIN_DB_PREFIX . "a_predmet_tagovi";
        $sql .= " WHERE fk_tag = " . $tagid;
        $resql = $db->query($sql);

        if ($resql) {
            // Then delete the tag itself
            $sql = "DELETE FROM " . MAIN_DB_PREFIX . "a_tagovi";
            $sql .= " WHERE rowid = " . $tagid;
            $sql .= " AND entity = " . $conf->entity;

            $resql = $db->query($sql);
            if ($resql) {
                $db->commit();
                setEventMessages($langs->trans('TagDeletedSuccessfully'), null, 'mesgs');
            } else {
                $db->rollback();
                setEventMessages($langs->trans('ErrorTagNotDeleted') . ' ' . $db->lasterror(), null, 'errors');
            }
        } else {
            $db->rollback();
            setEventMessages($langs->trans('ErrorDeletingTagAssociations') . ' ' . $db->lasterror(), null, 'errors');
        }
    }
}

/*
 * View
 */

$form = new Form($db);
$formfile = new FormFile($db);

// Set page title to "Tagovi"
llxHeader("", $langs->trans("Tagovi"), '', '', 0, 0, '', '', '', 'mod-seup page-tagovi');

// === BOOTSTRAP CDN ===
print '<meta name="viewport" content="width=device-width, initial-scale=1">';
print '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet">';

// Font Awesome za ikone
print '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">';

// Main content using HEREDOC syntax
$htmlContent = <<<HTML
<div class="container mt-3">
  <div class="shadow-sm p-4 bg-body rounded">
    <div class="text-center mb-4">
      <h2 class="mb-2"><i class="fas fa-tags me-2"></i>{$langs->trans("Tagovi")}</h2>
      <p class="lead mb-3">Upravljanje oznakama za dokumente i predmete</p>
    </div>
    
    <form method="POST" action="" class="mt-2">
      <input type="hidden" name="action" value="addtag">
      <div class="mb-3">
        <label for="tag" class="form-label">{$langs->trans('Tag')}</label>
        <div class="input-group">
          <input type="text" name="tag" id="tag" class="form-control" 
                 placeholder="{$langs->trans('UnesiNoviTag')}" 
                 value="{$tag_name}" required>
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>
            {$langs->trans('DodajTag')}
          </button>
        </div>
        <div class="form-text mt-2">{$langs->trans('TagoviHelpText')}</div>
      </div>
    </form>
    
    <div class="mt-4">
      <h4 class="mb-3">{$langs->trans('ExistingTags')}</h4>
HTML;

print $htmlContent;

// Display existing tags
$sql = "SELECT rowid, tag, date_creation";
$sql .= " FROM " . MAIN_DB_PREFIX . "a_tagovi";
$sql .= " WHERE entity = " . $conf->entity;
$sql .= " ORDER BY tag ASC";

$resql = $db->query($sql);
if ($resql) {
    $num = $db->num_rows($resql);
    $trans_confirm = $langs->trans('ConfirmDeleteTag');

    if ($num > 0) {
        print '<ul class="list-group">';
        while ($obj = $db->fetch_object($resql)) {
            print '<li class="list-group-item d-flex justify-content-between align-items-center">';
            print '<span class="badge bg-primary rounded-pill me-2">' . $obj->tag . '</span>';

            // Delete button with confirmation
            print '<form method="POST" action="" style="display:inline;">';
            print '<input type="hidden" name="action" value="deletetag">';
            print '<input type="hidden" name="tagid" value="' . $obj->rowid . '">';
            print '<button type="submit" class="btn btn-sm btn-danger" onclick="return confirm(\'' . dol_escape_js($trans_confirm) . '\')">';
            print '<i class="fas fa-trash"></i>';
            print '</button>';
            print '</form>';

            print '</li>';
        }
        print '</ul>';
    } else {
        print '<div class="alert alert-info">' . $langs->trans('NoTagsAvailable') . '</div>';
    }
} else {
    print '<div class="alert alert-warning">' . $langs->trans('ErrorLoadingTags') . '</div>';
}

// Close HTML content
$htmlFooter = <<<HTML
    </div>
  </div>
</div>
HTML;

print $htmlFooter;

// Bootstrap JS
print '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>';

// End of page
llxFooter();
$db->close();
