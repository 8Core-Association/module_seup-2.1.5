<?php

/**
 * Plaćena licenca
 * (c) 2025 8Core Association
 * Tomislav Galić <tomislav@8core.hr>
 * Marko Šimunović <marko@8core.hr>
 * Web: https://8core.hr
 * Kontakt: info@8core.hr | Tel: +385 099 851 0717
 * Sva prava pridržana. Ovaj softver je vlasnički i zaštićen je autorskim i srodnim pravima 
 * te ga je izričito zabranjeno umnožavati, distribuirati, mijenjati, objavljivati ili 
 * na drugi način eksploatirati bez pismenog odobrenja autora.
 * U skladu sa Zakonom o autorskom pravu i srodnim pravima 
 * (NN 167/03, 79/07, 80/11, 125/17), a osobito člancima 32. (pravo na umnožavanje), 35. 
 * (pravo na preradu i distribuciju) i 76. (kaznene odredbe), 
 * svako neovlašteno umnožavanje ili prerada ovog softvera smatra se prekršajem. 
 * Prema Kaznenom zakonu (NN 125/11, 144/12, 56/15), članak 228., stavak 1., 
 * prekršitelj se može kazniti novčanom kaznom ili zatvorom do jedne godine, 
 * a sud može izreći i dodatne mjere oduzimanja protivpravne imovinske koristi.
 * Bilo kakve izmjene, prijevodi, integracije ili dijeljenje koda bez izričitog pismenog 
 * odobrenja autora smatraju se kršenjem ugovora i zakona te će se pravno sankcionirati. 
 * Za sva pitanja, zahtjeve za licenciranjem ili dodatne informacije obratite se na info@8core.hr.
 */
/**
 *    \file       seup/seupindex.php
 *    \ingroup    seup
 *    \brief      Home page of seup top menu
 */


// Učitaj Dolibarr okruženje
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
  $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"] . "/main.inc.php";
}
// Pokušaj učitati main.inc.php iz korijenskog direktorija weba
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

// Omoguci debugiranje php skripti
error_reporting(E_ALL);
ini_set('display_errors', 1);


require_once DOL_DOCUMENT_ROOT . '/core/class/html.formfile.class.php';

if ($res) {
  // include Form class za token
  if (file_exists("../../../core/class/html.form.class.php")) {
    if (!dol_include_once('/core/class/html.form.class.php')) {
      die("Include of form fails");
    }
  }
} else {
  die("Error: Unable to include main.inc.php");
}
// Učitaj prijevode
$langs->loadLangs(array("seup@seup"));

$action = GETPOST('action', 'aZ09');

$now = dol_now();
$max = getDolGlobalInt('MAIN_SIZE_SHORTLIST_LIMIT', 5);
ob_start(); // Kontrolira buffer
// Sigurnosne provjere
$socid = GETPOST('socid', 'int');
if (isset($user->socid) && $user->socid > 0) {
  $action = '';
  $socid = $user->socid;
}

/*
 * View
 */
$form = new Form($db);

$formfile = new FormFile($db);

llxHeader("", "", '', '', 0, 0, '', '', '', 'mod-seup page-index');

// Modern design assets
print '<meta name="viewport" content="width=device-width, initial-scale=1">';
print '<link rel="preconnect" href="https://fonts.googleapis.com">';
print '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
print '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">';
print '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">';
print '<link href="/custom/seup/css/seup-modern.css" rel="stylesheet">';
print '<link href="/custom/seup/css/style.css" rel="stylesheet">';

require_once __DIR__ . '/../class/klasifikacijska_oznaka.class.php';
require_once __DIR__ . '/../class/oznaka_ustanove.class.php';
require_once __DIR__ . '/../class/interna_oznaka_korisnika.class.php';

// Import JS skripti
global $hookmanager;
$messagesFile = DOL_URL_ROOT . '/custom/seup/js/messages.js';
$hookmanager->initHooks(array('seup'));
print '<script src="' . $messagesFile . '"></script>';


// importanje klasa za rad s podacima: 
/*
**************************************
RAD S BAZOM 
**************************************
*/
// Provjeravamo da li u bazi vec postoji OZNAKA USTANOVE, ako postoji napunit cemo formu podacima
global $db;

// Provjera i Loadanje vrijednosti oznake ustanove pri loadu stranice
$podaci_postoje = null;
$sql = "SELECT ID_ustanove, singleton, code_ustanova, name_ustanova FROM " . MAIN_DB_PREFIX . "a_oznaka_ustanove WHERE  singleton = 1 LIMIT 1";
$resql = $db->query($sql);
$ID_ustanove = 0;
if ($resql && $db->num_rows($resql) > 0) {
  $podaci_postoje = $db->fetch_object($resql);
  $ID_ustanove = $podaci_postoje->ID_ustanove;
  dol_syslog("Podaci o oznaci ustanove su ucitani iz baze: " . $ID_ustanove, LOG_INFO);
}


// Provjera i Loadanje korisnika pri loadu stranice
require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';

$listUsers = [];
$userStatic = new User($db);

// Dohvati sve aktivne korisnike
$sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . "user WHERE statut = 1 ORDER BY lastname ASC";
$resql = $db->query($sql);
if ($resql) {
  while ($obj = $db->fetch_object($resql)) {
    $userStatic->fetch($obj->rowid);
    $listUsers[] = clone $userStatic;
  }
} else {
  echo $db->lasterror();
}

/*************************************
UNOSENJE PODATAKA IZ FORME U TABLICE
 **************************************/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  //TODO rijesi sigurnisni token - ne registriraju se metode Form klase. PROVJERI INICIJALNIZACIJU 
  // Provjera sigurnosnog tokena - sprijecava ponavljanje unosa pri refreshu i stiti protiv CSRF napada
  // if (!dol_check_token(GETPOST('token', 'alpha'))) {
  //   setEventMessages($langs->trans("ErrorBadCSRFToken"), null, 'errors');
  //   exit;
  // }

  // 1. Dodavanje interne oznake korisnika 
  if (isset($_POST['action_oznaka']) && $_POST['action_oznaka'] === 'add') {
    // Get form values
    $interna_oznaka_korisnika = new Interna_oznaka_korisnika();
    $interna_oznaka_korisnika->setIme_prezime(GETPOST('ime_user', 'alphanohtml'));
    $interna_oznaka_korisnika->setRbr_korisnika(GETPOST('redni_broj', 'int'));
    $interna_oznaka_korisnika->setRadno_mjesto_korisnika(GETPOST('radno_mjesto_korisnika', 'alphanohtml'));
    dol_syslog("User full name: " . $interna_oznaka_korisnika->getIme_prezime(), LOG_INFO);

    // Validate inputs
    if (empty($interna_oznaka_korisnika->getIme_prezime()) || empty($interna_oznaka_korisnika->getRbr_korisnika()) || empty($interna_oznaka_korisnika->getRadno_mjesto_korisnika())) {
      setEventMessages($langs->trans("All fields are required"), null, 'errors');
    } elseif (!preg_match('/^\d{1,2}$/', $interna_oznaka_korisnika->getRbr_korisnika())) {
      setEventMessages($langs->trans("Invalid serial number (vrijednosti moraju biti u rasponu 0 - 99)"), null, 'errors');
    } else {


      // Provjera da li postoji vec korisnik s tim rednim brojem
      $sqlCheck = "SELECT COUNT(*) as cnt FROM " . MAIN_DB_PREFIX . "a_interna_oznaka_korisnika WHERE rbr = '" . $db->escape($interna_oznaka_korisnika->getRbr_korisnika()) . "'";
      $resCheck = $db->query($sqlCheck);

      if ($resCheck) {
        $obj = $db->fetch_object($resCheck);
        if ($obj->cnt > 0) {
          setEventMessages($langs->trans("Korisnik s tim rednim brojem vec postoji u bazi"), null, 'errors');
        } else {

          $db->begin();
          // Insert into database
          $sql = "INSERT INTO " . MAIN_DB_PREFIX . "a_interna_oznaka_korisnika 
                      (ID_ustanove, ime_prezime, rbr, naziv) 
                      VALUES (
                    " . (int)$ID_ustanove . ", 
                    '" . $db->escape($interna_oznaka_korisnika->getIme_prezime()) . "',
                    '" . $db->escape($interna_oznaka_korisnika->getRbr_korisnika()) . "',
                    '" . $db->escape($interna_oznaka_korisnika->getRadno_mjesto_korisnika()) . "'                
                )";

          if ($db->query($sql)) {
            $db->commit();
            setEventMessages($langs->trans("Intena Oznaka Korisnika uspjesno dodana"), null, 'mesgs');
          } else {
            setEventMessages($langs->trans("Database error: ") . $db->lasterror(), null, 'errors');
          }
        }
      }
    }
  }
  if (isset($_POST['action_oznaka']) && $_POST['action_oznaka'] === 'update') {
    $originalCombination = json_decode(GETPOST('original_combination', 'restricthtml'), true);

    // Check if we have a valid combination
    if (
      !$originalCombination ||
      !isset($originalCombination['klasa_br']) ||
      !isset($originalCombination['sadrzaj']) ||
      !isset($originalCombination['dosje_br'])
    ) {

      setEventMessages($langs->trans("ErrorMissingOriginalCombination"), null, 'errors');
      $error++;
    } else {
      // Escape original values
      $origKlasa = $db->escape($originalCombination['klasa_br']);
      $origSadrzaj = $db->escape($originalCombination['sadrzaj']);
      $origDosje = $db->escape($originalCombination['dosje_br']);

      // Check if the original record exists
      $sqlProvjera = "SELECT ID_klasifikacijske_oznake 
                    FROM " . MAIN_DB_PREFIX . "a_klasifikacijska_oznaka 
                    WHERE klasa_broj = '$origKlasa'
                    AND sadrzaj = '$origSadrzaj'
                    AND dosje_broj = '$origDosje'";

      $rezultatProvjere = $db->query($sqlProvjera);

      if ($db->num_rows($rezultatProvjere) <= 0) {
        setEventMessages($langs->trans("KombinacijaNePostoji"), null, 'errors');
        $error++;
      } else {
        $update_array = array();
        $where_array = array();

        // Add fields to update
        if (!empty($klasifikacijska_oznaka->getKlasa_br())) {
          $update_array[] = "klasa_broj = '" . $db->escape($klasifikacijska_oznaka->getKlasa_br()) . "'";
        }
        if (!empty($klasifikacijska_oznaka->getSadrzaj())) {
          $update_array[] = "sadrzaj = '" . $db->escape($klasifikacijska_oznaka->getSadrzaj()) . "'";
        }
        if (!empty($klasifikacijska_oznaka->getDosjeBroj())) {
          $update_array[] = "dosje_broj = '" . $db->escape($klasifikacijska_oznaka->getDosjeBroj()) . "'";
        }
        if (!empty($klasifikacijska_oznaka->getVrijemeCuvanja())) {
          $update_array[] = "vrijeme_cuvanja = '" . $db->escape($klasifikacijska_oznaka->getVrijemeCuvanja()) . "'";
        }
        if (!empty($klasifikacijska_oznaka->getOpisKlasifikacijskeOznake())) {
          $update_array[] = "opis_klasifikacijske_oznake = '" . $db->escape($klasifikacijska_oznaka->getOpisKlasifikacijskeOznake()) . "'";
        }

        // Build WHERE clause using original combination
        $where_array[] = "klasa_broj = '$origKlasa'";
        $where_array[] = "sadrzaj = '$origSadrzaj'";
        $where_array[] = "dosje_broj = '$origDosje'";

        if (!empty($update_array)) {
          $sql = "UPDATE " . MAIN_DB_PREFIX . "a_klasifikacijska_oznaka 
                    SET " . implode(', ', $update_array) . "
                    WHERE " . implode(' AND ', $where_array);

          dol_syslog("Update SQL: $sql", LOG_DEBUG);

          if ($db->query($sql)) {
            setEventMessages($langs->trans("Uspjesno azurirana klasifikacijska oznaka"), null, 'mesgs');
          } else {
            setEventMessages($langs->trans("ErrorDatabase") . ": " . $db->lasterror(), null, 'errors');
            $error++;
          }
        } else {
          setEventMessages($langs->trans("NemaPromjenaZaSpremanje"), null, 'warnings');
        }
        unset($klasifikacijska_oznaka);
      }
    }
  }

  /***************** /***************** /***************** /*****************/
  /***************** SEKCIJA OZNAKA USTANOVE   ******************************/
  /***************** /***************** /***************** /*****************/

  // 2. Oznaka ustanove 
  if (isset($_POST['action_ustanova'])) {
    header('Content-Type: application/json; charset=UTF-8');
    ob_end_clean();

    $oznaka_ustanove = new Oznaka_ustanove();
    try {
      $conf->global->MAIN_HTML_THEME = 'nodumb';
      $db->begin();
      if ($podaci_postoje) {
        $oznaka_ustanove->setID_oznaka_ustanove($podaci_postoje->singleton);
      }
      $oznaka_ustanove->setOznaka_ustanove(GETPOST('code_ustanova', 'alphanohtml'));
      // Validacija formata unesenog teksta oznake_ustanove
      if (!preg_match('/^\d{4}-\d-\d$/', $oznaka_ustanove->getOznaka_ustanove())) {
        throw new Exception($langs->trans("Neispravan format Oznake Ustanove"));
      }

      $oznaka_ustanove->setNaziv_ustanove(GETPOST('name_ustanova', 'alphanohtml'));
      $action = GETPOST('action_ustanova', 'alpha');
      $sql = '';

      // Validacija tipke DODAJ / AZURIRAJ
      if ($action === 'add' && !$podaci_postoje) {
        dol_syslog("Dodaj Klik", LOG_INFO);
        $sql = "INSERT INTO " . MAIN_DB_PREFIX . "a_oznaka_ustanove 
                      (code_ustanova, name_ustanova) 
                      VALUES ( 
                    '" . $db->escape($oznaka_ustanove->getOznaka_ustanove()) . "',
                    '" . $db->escape($oznaka_ustanove->getNaziv_ustanove()) . "'                  
                )";
      } else {
        if (!is_object($podaci_postoje) || empty($podaci_postoje->singleton)) {
          throw new Exception($langs->trans('RecordNotFound'));
        }
        $oznaka_ustanove->setID_oznaka_ustanove($podaci_postoje->singleton);
        dol_syslog("Azuriraj Klik", LOG_INFO);
        $sql = "UPDATE " . MAIN_DB_PREFIX . "a_oznaka_ustanove 
                SET code_ustanova =  '" . $db->escape($oznaka_ustanove->getOznaka_ustanove()) . "',
                name_ustanova = '" . $db->escape($oznaka_ustanove->getNaziv_ustanove()) . "'
                WHERE ID_ustanove = '" . $db->escape($oznaka_ustanove->getID_oznaka_ustanove()) . "'";
      }

      $resql = $db->query($sql);
      if (!$resql) {
        dol_syslog("NE RADI DOBRO db->query(sql, params)", LOG_ERR);
        throw new Exception($db->lasterror());
      }

      $db->commit();

      echo json_encode([
        'success' => true,
        'message' => $langs->trans($action === 'add' ? 'Oznaka Ustanove Uspjesno dodana' : 'Oznaka Ustanove uspjesno azurirana'),
        'data' => [
          'code_ustanova' => $oznaka_ustanove->getOznaka_ustanove(),
          'name_ustanova' => $oznaka_ustanove->getNaziv_ustanove()
        ]
      ]);
      exit;
    } catch (Exception $e) {
      $db->rollback();
      http_response_code(500);
      echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
      ]);
    }
    unset($oznaka_ustanove);
    exit;
  }


  /***************** /***************** /***************** /*****************/
  /***************** SEKCIJA KLASIFIKACIJSKA OZNAKA ****************/
  /***************** /***************** /***************** /*****************/

  // 3. Unos klasifikacijske oznake
  if (isset($_POST['action_klasifikacija'])) {
    $klasifikacijska_oznaka = new Klasifikacijska_oznaka();
    $klasifikacijska_oznaka->setKlasa_br(GETPOST('klasa_br', 'int'));
    if (!preg_match('/^\d{3}$/', $klasifikacijska_oznaka->getKlasa_br())) {
      setEventMessages($langs->trans("ErrorKlasaBrFormat"), null, 'errors');
      $error++;
    }
    $klasifikacijska_oznaka->setSadrzaj(GETPOST('sadrzaj', 'int'));
    if (!preg_match('/^\d{2}$/', $klasifikacijska_oznaka->getSadrzaj()) || $klasifikacijska_oznaka->getSadrzaj() > 99 || $klasifikacijska_oznaka->getSadrzaj() < 00) {
      setEventMessages($langs->trans("ErrorSadrzajFormat"), null, 'errors');
      $error++;
    }
    $klasifikacijska_oznaka->setDosjeBroj(GETPOST('dosje_br', 'int'));
    if (!preg_match('/^\d{2}$/', $klasifikacijska_oznaka->getDosjeBroj()) || $klasifikacijska_oznaka->getDosjeBroj() > 50 || $klasifikacijska_oznaka->getDosjeBroj() < 0) {
      setEventMessages($langs->trans("ErrorDosjeBrojFormat"), null, 'errors');
      $error++;
    }
    $klasifikacijska_oznaka->setVrijemeCuvanja($klasifikacijska_oznaka->CastVrijemeCuvanjaToInt(GETPOST('vrijeme_cuvanja', 'int')));
    if (!preg_match('/^\d{1,2}$/', $klasifikacijska_oznaka->getVrijemeCuvanja()) || $klasifikacijska_oznaka->getVrijemeCuvanja() > 10 || $klasifikacijska_oznaka->getVrijemeCuvanja() < 0) {
      setEventMessages($langs->trans("ErrorVrijemeCuvanjaFormat"), null, 'errors');
      $error++;
    }  // TODO dodaj sve ErrorVrijemeCuvanjaFormat u lang file (i sve ostale tekstove koje korisimo u setEventMessages)
    $klasifikacijska_oznaka->setOpisKlasifikacijskeOznake(GETPOST('opis_klasifikacije', 'alphanohtml'));

    // Logika za gumb Unos Klasifikacijske Oznake : DODAJ
    if ($_POST['action_klasifikacija'] === 'add') {
      // provjera da li postoji vec klasa s unesenim brojem
      $klasa_br = $db->escape($klasifikacijska_oznaka->getKlasa_br());
      $sadrzaj = $db->escape($klasifikacijska_oznaka->getSadrzaj());
      $dosje_br = $db->escape($klasifikacijska_oznaka->getDosjeBroj());

      // Check if combination exists
      $sqlProvjera = "SELECT ID_klasifikacijske_oznake 
                    FROM " . MAIN_DB_PREFIX . "a_klasifikacijska_oznaka 
                    WHERE klasa_broj = '$klasa_br'
                    AND sadrzaj = '$sadrzaj'
                    AND dosje_broj = '$dosje_br'";
      $rezultatProvjere = $db->query($sqlProvjera);
      if ($db->num_rows($rezultatProvjere) > 0) {
        setEventMessages($langs->trans("KombinacijaKlaseSadrzajaDosjeaVecPostoji"), null, 'errors');
        $error++;
      } else { // ako ne postoji opleti dalje s insertom
        $sql = "INSERT INTO " . MAIN_DB_PREFIX . "a_klasifikacijska_oznaka 
                (ID_ustanove, klasa_broj, sadrzaj, dosje_broj, vrijeme_cuvanja, opis_klasifikacijske_oznake) 
                VALUES (
                    " . (int)$ID_ustanove . ",
                    '" . $db->escape($klasifikacijska_oznaka->getKlasa_br()) . "',
                    '" . $db->escape($klasifikacijska_oznaka->getSadrzaj()) . "',
                    '" . $db->escape($klasifikacijska_oznaka->getDosjeBroj()) . "',
                    '" . $db->escape($klasifikacijska_oznaka->getVrijemeCuvanja()) . "',
                    '" . $db->escape($klasifikacijska_oznaka->getOpisKlasifikacijskeOznake()) . "'
                )";
        $rezultatProvjere = $db->query($sql);
        if (!$rezultatProvjere) {
          if ($db->lasterrno() == 1062) {
            setEventMessages($langs->trans("ErrorKombinacijaDuplicate"), null, 'errors');
          } else {
            setEventMessages($langs->trans("ErrorDatabase") . ": " . $db->lasterror(), null, 'errors');
          }
          $error++;
        } else {
          setEventMessages($langs->trans("Uspjesno pohranjena klasifikacijska oznaka"), null, 'mesgs');
        }
        unset($klasifikacijska_oznaka);
      }

      // Logika za gumb Unos Klasifikacijske Oznake : AZURIRAJ
    } elseif ($_POST['action_klasifikacija'] === 'update') {
      dol_syslog("Received POST data: " . print_r($_POST, true), LOG_DEBUG);

      $id_oznake = GETPOST('id_klasifikacijske_oznake', 'int');
      dol_syslog("ID klasifikacijske oznake: " . $id_oznake, LOG_DEBUG);

      if (!$id_oznake) {
        setEventMessages($langs->trans("ErrorMissingRecordID"), null, 'errors');
        $error++;
      } else {
        // Check if the record with this ID exists
        $sqlProvjera = "SELECT * FROM " . MAIN_DB_PREFIX . "a_klasifikacijska_oznaka 
            WHERE ID_klasifikacijske_oznake = " . (int)$id_oznake;

        $rezultatProvjere = $db->query($sqlProvjera);

        if ($db->num_rows($rezultatProvjere) <= 0) {
          setEventMessages($langs->trans("KlasifikacijskaOznakaNePostoji"), null, 'errors');
          $error++;
        } else {
          $update_array = array();

          // Build the update array
          if (!empty($klasifikacijska_oznaka->getKlasa_br())) {
            $update_array[] = "klasa_broj = '" . $db->escape($klasifikacijska_oznaka->getKlasa_br()) . "'";
          }
          if (!empty($klasifikacijska_oznaka->getSadrzaj())) {
            $update_array[] = "sadrzaj = '" . $db->escape($klasifikacijska_oznaka->getSadrzaj()) . "'";
          }
          if (!empty($klasifikacijska_oznaka->getDosjeBroj())) {
            $update_array[] = "dosje_broj = '" . $db->escape($klasifikacijska_oznaka->getDosjeBroj()) . "'";
          }
          if (!empty($klasifikacijska_oznaka->getVrijemeCuvanja())) {
            $update_array[] = "vrijeme_cuvanja = '" . $db->escape($klasifikacijska_oznaka->getVrijemeCuvanja()) . "'";
          }
          if (!empty($klasifikacijska_oznaka->getOpisKlasifikacijskeOznake())) {
            $update_array[] = "opis_klasifikacijske_oznake = '" . $db->escape($klasifikacijska_oznaka->getOpisKlasifikacijskeOznake()) . "'";
          }

          if (count($update_array) > 0) {
            $sql = "UPDATE " . MAIN_DB_PREFIX . "a_klasifikacijska_oznaka 
                SET " . implode(', ', $update_array) . "
                WHERE ID_klasifikacijske_oznake = " . (int)$id_oznake;

            dol_syslog("Update SQL: $sql", LOG_DEBUG);

            if ($db->query($sql)) {
              setEventMessages($langs->trans("Uspjesno azurirana klasifikacijska oznaka"), null, 'mesgs');
            } else {
              setEventMessages($langs->trans("ErrorDatabase") . ": " . $db->lasterror(), null, 'errors');
              $error++;
            }
          } else {
            setEventMessages($langs->trans("NemaPromjenaZaSpremanje"), null, 'warnings');
          }
          unset($klasifikacijska_oznaka);
        }
      }
      // logika za gumb OBRISI
    } elseif ($_POST['action_klasifikacija'] === 'delete') {

      $id_oznake = GETPOST('id_klasifikacijske_oznake', 'int');

      if (!$id_oznake) {
        setEventMessages($langs->trans("ErrorMissingRecordID"), null, 'errors');
        $error++;
      } else {
        try {
          $db->begin();

          // First check if the record exists
          $sqlProvjera = "SELECT ID_klasifikacijske_oznake 
                            FROM " . MAIN_DB_PREFIX . "a_klasifikacijska_oznaka 
                            WHERE ID_klasifikacijske_oznake = " . (int)$id_oznake;

          $rezultatProvjere = $db->query($sqlProvjera);

          if ($db->num_rows($rezultatProvjere) <= 0) {
            setEventMessages($langs->trans("KlasifikacijskaOznakaNePostoji"), null, 'errors');
            $error++;
          } else {
            // Delete query using ID
            $sql = "DELETE FROM " . MAIN_DB_PREFIX . "a_klasifikacijska_oznaka 
                        WHERE ID_klasifikacijske_oznake = " . (int)$id_oznake;

            if ($db->query($sql)) {
              $db->commit();
              setEventMessages($langs->trans("KlasifikacijskaOznakaUspjesnoObrisana"), null, 'mesgs');

              // Redirect da se izbjegne ponovno slanje forme
              header('Location: ' . $_SERVER['PHP_SELF']);
              exit;

            } else {
              $db->rollback();
              setEventMessages($langs->trans("ErrorDeleteFailed") . ": " . $db->lasterror(), null, 'errors');
            }
          }
        } catch (Exception $e) {
          $db->rollback();
          setEventMessages($langs->trans("ErrorException") . ": " . $e->getMessage(), null, 'errors');
        }
      }
    }
  }
}


?>
<div class="container py-5">
  <!-- Glavni red sa dva stupca -->
  <div class="row g-4 mb-4">

    <!-- Lijevi stupac: dva kontejnera -->
    <div class="col-12 col-md-6">
      <!-- TODO mora se postaviti prava da samo admin moze ovo postavljati za unos, azuriranje i brisanje korisnika -->
      <!-- 1. Dodavanje interne oznake korisnika -->
      <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
        <div class="custom-container bg-white shadow rounded-3 p-4">
          <h5 class="mb-4"><?php echo $langs->trans('Dodavanje Interne Oznake Korisnika'); ?></h5>
          <div class="row g-2 mb-3 align-items-center">
            <div class="col-md-6">
              <label for="ime_user" class="form-label"><?php echo $langs->trans('Izaberi Korisnika'); ?></label>
              <select name="ime_user" class="form-select">
                <option value=""><?php echo $langs->trans('Ime i Prezime Korisnika'); ?></option>
                <?php foreach ($listUsers as $u): ?>
                  <option value="<?php echo $u->getFullName($langs); ?>">
                    <?php echo $u->getFullName($langs); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <!-- Redni broj korisnika -->
            <div class="col-md-6">
              <label for="redni_broj" class="form-label"><?php echo $langs->trans('Redni broj korisnika'); ?></label>
              <input type="text" name="redni_broj" id="redni_broj" class="form-control" placeholder="<?php echo $langs->trans('Unesi redni broj'); ?>" min="0" max="99" required>
            </div>
          </div>
          <!-- Naziv korisnika -->
          <div class="mb-3">
            <label for="radno_mjesto_korisnika" class="form-label"><?php echo $langs->trans('Radno Mjesto Korisnika'); ?></label>
            <input type="text" name="radno_mjesto_korisnika" id="radno_mjesto_korisnika" class="form-control" placeholder="<?php echo $langs->trans('Unesi Radno Mjesto Korisnika'); ?>" required>
          </div>
          <div class="mt-3">
            <button type="submit" name="action_oznaka" value="add" class="btn btn-primary me-2"><?php echo $langs->trans('DODAJ'); ?></button>
            <button type="submit" name="action_oznaka" value="update" class="btn btn-secondary me-2"><?php echo $langs->trans('AŽURIRAJ'); ?></button>
            <button type="submit" name="action_oznaka" value="delete" class="btn btn-danger"><?php echo $langs->trans('OBRIŠI'); ?></button>
          </div>
        </div>
      </form>

      <!-- 2. Oznaka ustanove -->
      <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" id="ustanova-form">
        <input type="hidden" name="action_ustanova" id="form-action" value="<?php echo ($podaci_postoje ? 'update' : 'add'); ?>">
        <div class="custom-container bg-white shadow rounded-3 p-4 mt-4 position-relative">
          <div id="messageDiv" class="alert" role="alert"></div>
          <h5 class="mb-4"><?php echo $langs->trans('Oznaka Ustanove'); ?></h5>
          <div class="row g-2 mb-3">
            <div class="col-md-6">
              <label for="code_ustanova" class="form-label"><?php echo $langs->trans('Oznaka'); ?></label>
              <input type="text" id="code_ustanova" name="code_ustanova" class="form-control" placeholder="<?php echo $langs->trans('Unesi Oznaku'); ?>" required pattern="^\d{4}-\d-\d$" value="<?php echo $podaci_postoje ? htmlspecialchars($podaci_postoje->code_ustanova) : ''; ?>">
            </div>
            <div class="col-md-6">
              <label for="name_ustanova" class="form-label"><?php echo $langs->trans('Naziv'); ?></label>
              <input type="text" id="name_ustanova" name="name_ustanova" class="form-control" placeholder="<?php echo $langs->trans('Unesi Naziv');  ?>" value="<?php echo $podaci_postoje ? htmlspecialchars($podaci_postoje->name_ustanova) : ''; ?>">
            </div>
          </div>
          <div class="mt-3">
            <button type="submit" id="ustanova-submit" class="btn btn-primary me-2">
              <?php echo $podaci_postoje ? $langs->trans('AŽURIRAJ') : $langs->trans('DODAJ'); ?>
            </button>
          </div>
        </div>
      </form>
      <script>
        document.addEventListener('DOMContentLoaded', function() {

          const form = document.getElementById('ustanova-form');
          const actionField = document.getElementById('form-action');
          const btnSubmit = document.getElementById('ustanova-submit');

          form.addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            formData.append('action_ustanova', btnSubmit.textContent.trim() === 'DODAJ' ? 'add' : 'update');

            try {
              const response = await fetch('<?php echo $_SERVER['PHP_SELF'] ?>', {
                method: 'POST',
                body: formData,
                headers: {
                  'X-Requested-With': 'XMLHttpRequest',
                  'Accept': 'application/json'
                }
              });
              // Check for HTML error pages first
              if (!response.ok) {
                const text = await response.text();
                throw new Error(`HTTP error ${response.status}: ${text.slice(0, 100)}`);
              }

              // Check content type first
              const contentType = response.headers.get('content-type');

              if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                throw new Error(`Invalid response: ${text.slice(0, 100)}`);
              }
              const result = await response.json();
              if (result.success) {
                // Update UI
                actionField.value = 'update';
                btnSubmit.textContent = 'AŽURIRAJ';
                btnSubmit.classList.replace('btn-primary', 'btn-secondary');


                // Update input values
                document.getElementById('code_ustanova').value = result.data.code_ustanova;
                document.getElementById('name_ustanova').value = result.data.name_ustanova;

                // Show success message

                showMessage(result.message, type = 'success');
              } else {
                showMessage(result.message, type = 'success');
              }
            } catch (error) {
              console.error('Error:', error);
            }
          });
        });
      </script>
    </div>

    <!-- Desni stupac: unos klasifikacijske oznake -->
    <div class="col-12 col-md-6">
      <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
        <input type="hidden" id="hidden_id_klasifikacijske_oznake" name="id_klasifikacijske_oznake" value="">
        <div class="custom-container bg-white shadow rounded-3 p-4">
          <h5> <?php echo $langs->trans('Unos Klasifikacijske Oznake'); ?></h5>

          <div class="mb-3">
            <label for="klasa_br" class="form-label"><?php echo $langs->trans('Klasa Br:'); ?></label>
            <input type="text" id="klasa_br" name="klasa_br" class="form-control" placeholder="<?php echo $langs->trans('Unesi Klasu'); ?>" pattern="\d{3}" maxlength="3" autocomplete="off">
            <div id="autocomplete-results" class="autocomplete-dropdown"></div>
          </div>

          <div class="mb-3">
            <label for="sadrzaj" class="form-label"><?php echo $langs->trans('Sadrzaj:'); ?></label>
            <input type="text" id="sadrzaj" name="sadrzaj" class="form-control" placeholder="<?php echo $langs->trans('Unesi Sadrzaj'); ?>" pattern="\d{2}" maxlength="2">
          </div>

          <div class="mb-3">
            <label for="dosje_br" class="form-label"><?php echo $langs->trans('Dosje Br:'); ?></label>
            <select id="dosje_br" name="dosje_br" class="form-select">
              <option value="" disabled selected hidden><?php echo $langs->trans('Izaberi Dosje'); ?></option>
              <?php for ($i = 1; $i <= 50; $i++): $val = sprintf('%02d', $i); ?>
                <option value="<?php echo $val; ?>"><?php echo $val; ?></option>
              <?php endfor; ?>
            </select>
          </div>

          <div class="mb-3">
            <label for="vrijeme_cuvanja" class="form-label"><?php echo $langs->trans('Vrijeme Cuvanja:'); ?></label>
            <select id="vrijeme_cuvanja" name="vrijeme_cuvanja" class="form-select">
              <option value="permanent"><?php echo $langs->trans('Trajno'); ?></option>
              <?php for ($g = 1; $g <= 10; $g++): ?>
                <option value="<?php echo $g; ?>"><?php echo $g . ' ' . $langs->trans('Godina'); ?></option>
              <?php endfor; ?>
            </select>
          </div>

          <div class="mb-3">
            <label for="opis_klasifikacije" class="form-label"><?php echo $langs->trans('Opis Klasifikacijske Oznake:'); ?></label>
            <textarea id="opis_klasifikacije" name="opis_klasifikacije" class="form-control" rows="3" placeholder="<?php echo $langs->trans('Unesi Opis'); ?>"></textarea>
          </div>

          <div class="mt-3">
            <button type="submit" name="action_klasifikacija" value="add" class="btn btn-primary me-2"><?php echo $langs->trans('DODAJ'); ?></button>
            <button type="submit" name="action_klasifikacija" value="update" class="btn btn-secondary me-2"><?php echo $langs->trans('AŽURIRAJ'); ?></button>
            <button type="submit" name="action_klasifikacija" value="delete" class="btn btn-danger"><?php echo $langs->trans('OBRIŠI'); ?></button>
          </div>
        </div>
    </div>
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        const input = document.getElementById('klasa_br');
        const resultsContainer = document.getElementById('autocomplete-results');
        const formFields = {
          sadrzaj: document.getElementById('sadrzaj'),
          dosje_br: document.getElementById('dosje_br'),
          vrijeme_cuvanja: document.getElementById('vrijeme_cuvanja'),
          opis_klasifikacije: document.getElementById('opis_klasifikacije')
        };

        let debounceTimer;

        input.addEventListener('input', debounce(function(e) {
          const searchTerm = e.target.value.trim();
          if (searchTerm.length >= 1) {
            fetch('../class/autocomplete.php', {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'query=' + encodeURIComponent(searchTerm)
              })
              .then(handleErrors)
              .then(response => response.json())
              .then(data => showResults(data))
              .catch(error => console.error('Error:', error));
          } else {
            clearResults();
          }
        }, 300));

        function showResults(results) {
          resultsContainer.innerHTML = '';
          results.forEach(result => {
            const div = document.createElement('div');
            div.className = 'autocomplete-item';
            div.textContent = result.klasa_br + ' - ' + result.sadrzaj + ' - ' + result.dosje_br;
            div.dataset.id = result.id; // ID po kojem se drzimo za update i delete
            div.dataset.record = JSON.stringify(result);
            console.log('Result:', result);
            div.addEventListener('click', () => populateForm(result));
            resultsContainer.appendChild(div);
          });
        }

        function populateForm(data) {
          input.value = data.klasa_br;
          formFields.sadrzaj.value = data.sadrzaj || '';
          formFields.dosje_br.value = data.dosje_br || '';
          formFields.vrijeme_cuvanja.value = data.vrijeme_cuvanja.toString() === '0' ? 'permanent' : data.vrijeme_cuvanja;
          formFields.opis_klasifikacije.value = data.opis_klasifikacije || '';
          // Store the original combination for update identification
          if (!document.getElementById('hidden_id_klasifikacijske_oznake')) {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.id = 'hidden_id_klasifikacijske_oznake';
            hiddenInput.name = 'id_klasifikacijske_oznake';
            document.querySelector('form').appendChild(hiddenInput);
          }
          // Store as JSON string
          const combination = JSON.stringify({
            klasa_br: data.klasa_br,
            sadrzaj: data.sadrzaj,
            dosje_br: data.dosje_br
          });

          document.getElementById('hidden_id_klasifikacijske_oznake').value = data.ID;
          console.log('Set record ID:', data.ID);
          clearResults();
        }

        function debounce(func, wait) {
          let timeout;
          return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
          };
        }

        function handleErrors(response) {
          if (!response.ok) throw new Error(response.statusText);
          return response;
        }

        function clearResults() {
          resultsContainer.innerHTML = '';
        }

        // Hide results when clicking outside
        document.addEventListener('click', function(e) {
          if (!e.target.closest('.autocomplete-dropdown') && e.target !== input) {
            resultsContainer.innerHTML = '';
          }
        });
      });
    </script>
    </form>
  </div>

  <!-- Full-width opis -->
  <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
    <div class="row">
      <div class="col-12">
        <div class="custom-container bg-light border border-2 border-dashed rounded-3 p-4">
          <h4 class="text-center text-muted mb-3">
            <i class="fas fa-align-left me-2"></i><?php echo $langs->trans('Opis'); ?>
          </h4>
          <div class="text-center text-muted"><?php echo $langs->trans('Opis Tekst'); ?></div>
          <div class="text-center mt-3">
            <button class="btn btn-primary btn-lg">
              <i class="fas fa-download me-2"></i><?php echo $langs->trans('Preuzmi Dokumentaciju'); ?>
            </button>
          </div>
        </div>
      </div>
    </div>
  </form>
</div>


<?php

llxFooter();
$db->close();

?>