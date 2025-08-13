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
 */

class Predmet_helper
{
    /**
     * Create SEUP database tables if they don't exist
     */
    public static function createSeupDatabaseTables($db)
    {
        global $conf;

        $sql_tables = array();

        // Table for ustanove
        $sql_tables[] = "CREATE TABLE IF NOT EXISTS " . MAIN_DB_PREFIX . "a_oznaka_ustanove (
            ID_ustanove int(11) NOT NULL AUTO_INCREMENT,
            singleton tinyint(1) DEFAULT 1,
            code_ustanova varchar(20) NOT NULL,
            name_ustanova varchar(255) NOT NULL,
            PRIMARY KEY (ID_ustanove),
            UNIQUE KEY unique_singleton (singleton)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        // Table for interne oznake korisnika
        $sql_tables[] = "CREATE TABLE IF NOT EXISTS " . MAIN_DB_PREFIX . "a_interna_oznaka_korisnika (
            ID int(11) NOT NULL AUTO_INCREMENT,
            ID_ustanove int(11) NOT NULL,
            ime_prezime varchar(255) NOT NULL,
            rbr int(2) NOT NULL,
            naziv varchar(255) NOT NULL,
            PRIMARY KEY (ID),
            UNIQUE KEY unique_rbr (rbr),
            KEY fk_ustanova (ID_ustanove)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        // Table for klasifikacijske oznake
        $sql_tables[] = "CREATE TABLE IF NOT EXISTS " . MAIN_DB_PREFIX . "a_klasifikacijska_oznaka (
            ID_klasifikacijske_oznake int(11) NOT NULL AUTO_INCREMENT,
            ID_ustanove int(11) NOT NULL,
            klasa_broj varchar(3) NOT NULL,
            sadrzaj varchar(2) NOT NULL,
            dosje_broj varchar(2) NOT NULL,
            vrijeme_cuvanja int(2) NOT NULL DEFAULT 0,
            opis_klasifikacijske_oznake text,
            PRIMARY KEY (ID_klasifikacijske_oznake),
            UNIQUE KEY unique_combination (klasa_broj, sadrzaj, dosje_broj),
            KEY fk_ustanova_klasa (ID_ustanove)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        // Table for predmeti
        $sql_tables[] = "CREATE TABLE IF NOT EXISTS " . MAIN_DB_PREFIX . "a_predmet (
            ID_predmeta int(11) NOT NULL AUTO_INCREMENT,
            klasa_br varchar(3) NOT NULL,
            sadrzaj varchar(2) NOT NULL,
            dosje_broj varchar(2) NOT NULL,
            godina varchar(2) NOT NULL,
            predmet_rbr int(11) NOT NULL,
            naziv_predmeta text NOT NULL,
            ID_ustanove int(11) NOT NULL,
            ID_interna_oznaka_korisnika int(11) NOT NULL,
            ID_klasifikacijske_oznake int(11) NOT NULL,
            vrijeme_cuvanja int(2) NOT NULL,
            stranka varchar(255) DEFAULT NULL,
            tstamp_created timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (ID_predmeta),
            UNIQUE KEY unique_predmet (klasa_br, sadrzaj, dosje_broj, godina, predmet_rbr),
            KEY fk_ustanova_predmet (ID_ustanove),
            KEY fk_korisnik (ID_interna_oznaka_korisnika),
            KEY fk_klasifikacija (ID_klasifikacijske_oznake)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        // Table for tagovi
        $sql_tables[] = "CREATE TABLE IF NOT EXISTS " . MAIN_DB_PREFIX . "a_tagovi (
            rowid int(11) NOT NULL AUTO_INCREMENT,
            tag varchar(100) NOT NULL,
            color varchar(20) DEFAULT 'blue',
            entity int(11) NOT NULL DEFAULT 1,
            date_creation datetime DEFAULT NULL,
            fk_user_creat int(11) DEFAULT NULL,
            PRIMARY KEY (rowid),
            UNIQUE KEY unique_tag_entity (tag, entity)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        // Table for predmet-tag associations
        $sql_tables[] = "CREATE TABLE IF NOT EXISTS " . MAIN_DB_PREFIX . "a_predmet_tagovi (
            rowid int(11) NOT NULL AUTO_INCREMENT,
            fk_predmet int(11) NOT NULL,
            fk_tag int(11) NOT NULL,
            PRIMARY KEY (rowid),
            UNIQUE KEY unique_predmet_tag (fk_predmet, fk_tag),
            KEY fk_predmet_idx (fk_predmet),
            KEY fk_tag_idx (fk_tag)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        // Table for predmet-stranka associations
        $sql_tables[] = "CREATE TABLE IF NOT EXISTS " . MAIN_DB_PREFIX . "a_predmet_stranka (
            rowid int(11) NOT NULL AUTO_INCREMENT,
            ID_predmeta int(11) NOT NULL,
            fk_soc int(11) NOT NULL,
            role varchar(50) DEFAULT 'creator',
            date_stranka_opened datetime DEFAULT NULL,
            PRIMARY KEY (rowid),
            KEY fk_predmet_stranka (ID_predmeta),
            KEY fk_soc_stranka (fk_soc)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        // Table for arhiva
        $sql_tables[] = "CREATE TABLE IF NOT EXISTS " . MAIN_DB_PREFIX . "a_arhiva (
            ID_arhive int(11) NOT NULL AUTO_INCREMENT,
            ID_predmeta int(11) NOT NULL,
            klasa_predmeta varchar(50) NOT NULL,
            naziv_predmeta text NOT NULL,
            broj_dokumenata int(11) DEFAULT 0,
            razlog_arhiviranja text,
            datum_arhiviranja datetime DEFAULT CURRENT_TIMESTAMP,
            fk_user_arhivirao int(11) NOT NULL,
            status_arhive enum('active','deleted') DEFAULT 'active',
            PRIMARY KEY (ID_arhive),
            KEY fk_predmet_arhiva (ID_predmeta),
            KEY fk_user_arhiva (fk_user_arhivirao)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        // Execute table creation
        foreach ($sql_tables as $sql) {
            $resql = $db->query($sql);
            if (!$resql) {
                dol_syslog("Error creating table: " . $db->lasterror(), LOG_ERR);
            }
        }
    }

    /**
     * Sync files from filesystem to ECM database
     */
    public static function syncPredmetFiles($db, $conf, $user, $predmet_id)
    {
        try {
            $db->begin();
            
            $upload_dir = DOL_DATA_ROOT . '/ecm/SEUP/predmet_' . $predmet_id . '/';
            
            if (!is_dir($upload_dir)) {
                return ['success' => false, 'error' => 'Directory does not exist'];
            }

            // Get files from filesystem
            $filesystem_files = [];
            $files = scandir($upload_dir);
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..' && is_file($upload_dir . $file)) {
                    $filesystem_files[] = $file;
                }
            }

            // Get files from database
            $sql = "SELECT filename FROM " . MAIN_DB_PREFIX . "ecm_files 
                    WHERE filepath = 'SEUP/predmet_" . (int)$predmet_id . "/'";
            $resql = $db->query($sql);
            $database_files = [];
            if ($resql) {
                while ($obj = $db->fetch_object($resql)) {
                    $database_files[] = $obj->filename;
                }
            }

            // Find missing files (in filesystem but not in database)
            $missing_files = array_diff($filesystem_files, $database_files);
            $added_count = 0;

            foreach ($missing_files as $filename) {
                $filepath = 'SEUP/predmet_' . $predmet_id . '/';
                $fullpath = $upload_dir . $filename;
                
                // Generate external urbroj
                $urbroj = self::generateExternalUrbroj($db);
                
                // Create ECM record
                require_once DOL_DOCUMENT_ROOT . '/ecm/class/ecmfiles.class.php';
                $ecmfile = new EcmFiles($db);
                $ecmfile->filepath = $filepath;
                $ecmfile->filename = $filename;
                $ecmfile->urbroj = $urbroj;
                $ecmfile->label = $filename;
                $ecmfile->entity = $conf->entity;
                $ecmfile->gen_or_uploaded = 'uploaded';
                $ecmfile->description = 'External file synced for predmet ' . $predmet_id;
                $ecmfile->fk_user_c = $user->id;
                $ecmfile->fk_user_m = $user->id;
                $ecmfile->filetype = dol_mimetype($filename);

                $result = $ecmfile->create($user);
                if ($result > 0) {
                    $added_count++;
                } else {
                    dol_syslog("Error creating ECM record for $filename: " . $ecmfile->error, LOG_ERR);
                }
            }

            $db->commit();
            
            return [
                'success' => true,
                'added_count' => $added_count,
                'message' => "Uspješno dodano $added_count datoteka"
            ];
            
        } catch (Exception $e) {
            $db->rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get file statistics for sync indicator
     */
    public static function getFileStats($db, $predmet_id)
    {
        $upload_dir = DOL_DATA_ROOT . '/ecm/SEUP/predmet_' . $predmet_id . '/';
        
        // Count filesystem files
        $filesystem_count = 0;
        if (is_dir($upload_dir)) {
            $files = scandir($upload_dir);
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..' && is_file($upload_dir . $file)) {
                    $filesystem_count++;
                }
            }
        }

        // Count database files
        $sql = "SELECT COUNT(*) as count FROM " . MAIN_DB_PREFIX . "ecm_files 
                WHERE filepath = 'SEUP/predmet_" . (int)$predmet_id . "/'";
        $resql = $db->query($sql);
        $database_count = 0;
        if ($resql && $obj = $db->fetch_object($resql)) {
            $database_count = (int)$obj->count;
        }

        return [
            'filesystem_count' => $filesystem_count,
            'database_count' => $database_count,
            'needs_sync' => $filesystem_count > $database_count
        ];
    }

    /**
     * Generate external urbroj for synced files
     */
    public static function generateExternalUrbroj($db)
    {
        $today = date('Ymd');
        $prefix = "EXT-$today-";
        
        // Find highest number for today
        $sql = "SELECT urbroj FROM " . MAIN_DB_PREFIX . "ecm_files 
                WHERE urbroj LIKE '$prefix%' 
                ORDER BY urbroj DESC LIMIT 1";
        
        $resql = $db->query($sql);
        $next_number = 1;
        
        if ($resql && $obj = $db->fetch_object($resql)) {
            $last_urbroj = $obj->urbroj;
            $last_number = (int)substr($last_urbroj, -4);
            $next_number = $last_number + 1;
        }
        
        return $prefix . sprintf('%04d', $next_number);
    }

    /**
     * Fetch dropdown data for novi_predmet.php
     */
    public static function fetchDropdownData($db, $langs, &$klasaOptions, &$klasaMapJson, &$zaposlenikOptions)
    {
        // Fetch klasa options
        $sql = "SELECT DISTINCT klasa_broj FROM " . MAIN_DB_PREFIX . "a_klasifikacijska_oznaka ORDER BY klasa_broj ASC";
        $resql = $db->query($sql);
        $klasaOptions = '<option value="">Odaberi klasu</option>';
        $klasaMap = array();

        if ($resql) {
            while ($obj = $db->fetch_object($resql)) {
                $klasaOptions .= '<option value="' . $obj->klasa_broj . '">' . $obj->klasa_broj . '</option>';
                
                // Fetch sadrzaj for this klasa
                $sql2 = "SELECT DISTINCT sadrzaj FROM " . MAIN_DB_PREFIX . "a_klasifikacijska_oznaka 
                         WHERE klasa_broj = '" . $db->escape($obj->klasa_broj) . "' ORDER BY sadrzaj ASC";
                $resql2 = $db->query($sql2);
                $klasaMap[$obj->klasa_broj] = array();
                
                if ($resql2) {
                    while ($obj2 = $db->fetch_object($resql2)) {
                        // Fetch dosje for this klasa-sadrzaj combination
                        $sql3 = "SELECT DISTINCT dosje_broj FROM " . MAIN_DB_PREFIX . "a_klasifikacijska_oznaka 
                                 WHERE klasa_broj = '" . $db->escape($obj->klasa_broj) . "' 
                                 AND sadrzaj = '" . $db->escape($obj2->sadrzaj) . "' ORDER BY dosje_broj ASC";
                        $resql3 = $db->query($sql3);
                        $klasaMap[$obj->klasa_broj][$obj2->sadrzaj] = array();
                        
                        if ($resql3) {
                            while ($obj3 = $db->fetch_object($resql3)) {
                                $klasaMap[$obj->klasa_broj][$obj2->sadrzaj][] = $obj3->dosje_broj;
                            }
                        }
                    }
                }
            }
        }

        $klasaMapJson = json_encode($klasaMap);

        // Fetch zaposlenik options
        require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';
        $userStatic = new User($db);
        $sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . "user WHERE statut = 1 ORDER BY lastname ASC";
        $resql = $db->query($sql);
        $zaposlenikOptions = '<option value="">Odaberi zaposlenika</option>';
        
        if ($resql) {
            while ($obj = $db->fetch_object($resql)) {
                $userStatic->fetch($obj->rowid);
                $zaposlenikOptions .= '<option value="' . $obj->rowid . '">' . 
                                     htmlspecialchars($userStatic->getFullName($langs)) . '</option>';
            }
        }
    }

    /**
     * Check if predmet exists
     */
    public static function checkPredmetExists($db, $klasa_br, $sadrzaj, $dosje_br, $god)
    {
        $sql = "SELECT COUNT(*) as count FROM " . MAIN_DB_PREFIX . "a_predmet 
                WHERE klasa_br = '" . $db->escape($klasa_br) . "' 
                AND sadrzaj = '" . $db->escape($sadrzaj) . "' 
                AND dosje_broj = '" . $db->escape($dosje_br) . "' 
                AND godina = '" . $db->escape($god) . "'";
        
        $resql = $db->query($sql);
        if ($resql && $obj = $db->fetch_object($resql)) {
            return $obj->count > 0;
        }
        return false;
    }

    /**
     * Get next predmet rbr
     */
    public static function getNextPredmetRbr($db, $klasa_br, $sadrzaj, $dosje_br, $god)
    {
        $sql = "SELECT MAX(predmet_rbr) as max_rbr FROM " . MAIN_DB_PREFIX . "a_predmet 
                WHERE klasa_br = '" . $db->escape($klasa_br) . "' 
                AND sadrzaj = '" . $db->escape($sadrzaj) . "' 
                AND dosje_broj = '" . $db->escape($dosje_br) . "' 
                AND godina = '" . $db->escape($god) . "'";
        
        $resql = $db->query($sql);
        if ($resql && $obj = $db->fetch_object($resql)) {
            return ($obj->max_rbr ? $obj->max_rbr + 1 : 1);
        }
        return 1;
    }

    /**
     * Get ustanova by zaposlenik
     */
    public static function getUstanovaByZaposlenik($db, $zaposlenik_id)
    {
        $sql = "SELECT u.ID_ustanove FROM " . MAIN_DB_PREFIX . "a_oznaka_ustanove u 
                INNER JOIN " . MAIN_DB_PREFIX . "a_interna_oznaka_korisnika k ON u.ID_ustanove = k.ID_ustanove 
                WHERE k.ID = " . (int)$zaposlenik_id;
        
        $resql = $db->query($sql);
        if ($resql && $obj = $db->fetch_object($resql)) {
            return $obj;
        }
        return null;
    }

    /**
     * Get klasifikacijska oznaka
     */
    public static function getKlasifikacijskaOznaka($db, $klasa_br, $sadrzaj, $dosje_br)
    {
        $sql = "SELECT ID_klasifikacijske_oznake, vrijeme_cuvanja FROM " . MAIN_DB_PREFIX . "a_klasifikacijska_oznaka 
                WHERE klasa_broj = '" . $db->escape($klasa_br) . "' 
                AND sadrzaj = '" . $db->escape($sadrzaj) . "' 
                AND dosje_broj = '" . $db->escape($dosje_br) . "'";
        
        $resql = $db->query($sql);
        if ($resql && $obj = $db->fetch_object($resql)) {
            return $obj;
        }
        return null;
    }

    /**
     * Insert new predmet
     */
    public static function insertPredmet($db, $klasa_br, $sadrzaj, $dosje_br, $god, $rbr_predmeta, $naziv, $id_ustanove, $id_zaposlenik, $id_klasifikacijske_oznake, $vrijeme_cuvanja, $stranka, $datum_otvaranja)
    {
        $sql = "INSERT INTO " . MAIN_DB_PREFIX . "a_predmet (
                    klasa_br, sadrzaj, dosje_broj, godina, predmet_rbr, naziv_predmeta,
                    ID_ustanove, ID_interna_oznaka_korisnika, ID_klasifikacijske_oznake,
                    vrijeme_cuvanja, stranka, tstamp_created
                ) VALUES (
                    '" . $db->escape($klasa_br) . "',
                    '" . $db->escape($sadrzaj) . "',
                    '" . $db->escape($dosje_br) . "',
                    '" . $db->escape($god) . "',
                    " . (int)$rbr_predmeta . ",
                    '" . $db->escape($naziv) . "',
                    " . (int)$id_ustanove . ",
                    " . (int)$id_zaposlenik . ",
                    " . (int)$id_klasifikacijske_oznake . ",
                    " . (int)$vrijeme_cuvanja . ",
                    " . ($stranka ? "'" . $db->escape($stranka) . "'" : "NULL") . ",
                    " . ($datum_otvaranja ? "'" . $db->escape($datum_otvaranja) . "'" : "NOW()") . "
                )";

        return $db->query($sql);
    }

    /**
     * Fetch uploaded documents for predmet
     */
    public static function fetchUploadedDocuments($db, $conf, &$documentTableHTML, $langs, $caseId)
    {
        $sql = "SELECT 
                    e.rowid,
                    e.filename,
                    e.urbroj,
                    e.label,
                    DATE_FORMAT(e.date_c, '%d.%m.%Y %H:%i') as upload_date,
                    CONCAT(u.firstname, ' ', u.lastname) as uploaded_by
                FROM " . MAIN_DB_PREFIX . "ecm_files e
                LEFT JOIN " . MAIN_DB_PREFIX . "user u ON e.fk_user_c = u.rowid
                WHERE e.filepath = 'SEUP/predmet_" . (int)$caseId . "/'
                ORDER BY e.date_c DESC";

        $resql = $db->query($sql);
        $documents = [];
        if ($resql) {
            while ($obj = $db->fetch_object($resql)) {
                $documents[] = $obj;
            }
        }

        if (count($documents) > 0) {
            $documentTableHTML = '<table class="seup-documents-table">';
            $documentTableHTML .= '<thead>';
            $documentTableHTML .= '<tr>';
            $documentTableHTML .= '<th>Naziv datoteke</th>';
            $documentTableHTML .= '<th>Urbroj</th>';
            $documentTableHTML .= '<th>Datum uploada</th>';
            $documentTableHTML .= '<th>Uploadao</th>';
            $documentTableHTML .= '<th>Akcije</th>';
            $documentTableHTML .= '</tr>';
            $documentTableHTML .= '</thead>';
            $documentTableHTML .= '<tbody>';

            foreach ($documents as $doc) {
                $download_url = DOL_URL_ROOT . '/document.php?modulepart=ecm&file=' . 
                               urlencode('SEUP/predmet_' . $caseId . '/' . $doc->filename);
                
                $documentTableHTML .= '<tr>';
                $documentTableHTML .= '<td>' . htmlspecialchars($doc->filename) . '</td>';
                $documentTableHTML .= '<td><span class="seup-badge seup-badge-neutral">' . 
                                     htmlspecialchars($doc->urbroj) . '</span></td>';
                $documentTableHTML .= '<td><div class="seup-document-date">' . $doc->upload_date . '</div></td>';
                $documentTableHTML .= '<td><div class="seup-document-user">' . 
                                     htmlspecialchars($doc->uploaded_by ?: 'N/A') . '</div></td>';
                $documentTableHTML .= '<td>';
                $documentTableHTML .= '<a href="' . $download_url . '" class="seup-btn-download" target="_blank">';
                $documentTableHTML .= '<i class="fas fa-download"></i>';
                $documentTableHTML .= '</a>';
                $documentTableHTML .= '</td>';
                $documentTableHTML .= '</tr>';
            }

            $documentTableHTML .= '</tbody>';
            $documentTableHTML .= '</table>';
        } else {
            $documentTableHTML = '<div class="alert alert-info">' . $langs->trans("NoDocumentsFound") . '</div>';
        }
    }

    /**
     * Archive predmet
     */
    public static function archivePredmet($db, $conf, $user, $predmet_id, $razlog = '')
    {
        try {
            $db->begin();
            
            // Get predmet details
            $sql = "SELECT 
                        CONCAT(klasa_br, '-', sadrzaj, '/', godina, '-', dosje_broj, '/', predmet_rbr) as klasa_predmeta,
                        naziv_predmeta
                    FROM " . MAIN_DB_PREFIX . "a_predmet 
                    WHERE ID_predmeta = " . (int)$predmet_id;
            
            $resql = $db->query($sql);
            if (!$resql || $db->num_rows($resql) == 0) {
                return ['success' => false, 'error' => 'Predmet not found'];
            }
            
            $predmet = $db->fetch_object($resql);
            
            // Count documents
            $sql = "SELECT COUNT(*) as count FROM " . MAIN_DB_PREFIX . "ecm_files 
                    WHERE filepath = 'SEUP/predmet_" . (int)$predmet_id . "/'";
            $resql = $db->query($sql);
            $doc_count = 0;
            if ($resql && $obj = $db->fetch_object($resql)) {
                $doc_count = (int)$obj->count;
            }
            
            // Move files to archive directory
            $source_dir = DOL_DATA_ROOT . '/ecm/SEUP/predmet_' . $predmet_id . '/';
            $archive_dir = DOL_DATA_ROOT . '/ecm/SEUP/arhiva/predmet_' . $predmet_id . '/';
            
            $files_moved = 0;
            if (is_dir($source_dir)) {
                dol_mkdir($archive_dir);
                $files = scandir($source_dir);
                foreach ($files as $file) {
                    if ($file !== '.' && $file !== '..') {
                        if (rename($source_dir . $file, $archive_dir . $file)) {
                            $files_moved++;
                        }
                    }
                }
                rmdir($source_dir);
            }
            
            // Update ECM records
            $sql = "UPDATE " . MAIN_DB_PREFIX . "ecm_files 
                    SET filepath = 'SEUP/arhiva/predmet_" . (int)$predmet_id . "/' 
                    WHERE filepath = 'SEUP/predmet_" . (int)$predmet_id . "/'";
            $db->query($sql);
            
            // Insert into arhiva table
            $sql = "INSERT INTO " . MAIN_DB_PREFIX . "a_arhiva (
                        ID_predmeta, klasa_predmeta, naziv_predmeta, broj_dokumenata,
                        razlog_arhiviranja, fk_user_arhivirao
                    ) VALUES (
                        " . (int)$predmet_id . ",
                        '" . $db->escape($predmet->klasa_predmeta) . "',
                        '" . $db->escape($predmet->naziv_predmeta) . "',
                        " . (int)$doc_count . ",
                        '" . $db->escape($razlog) . "',
                        " . (int)$user->id . "
                    )";
            
            if (!$db->query($sql)) {
                throw new Exception($db->lasterror());
            }
            
            $db->commit();
            
            return [
                'success' => true,
                'files_moved' => $files_moved,
                'message' => "Predmet uspješno arhiviran"
            ];
            
        } catch (Exception $e) {
            $db->rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Restore predmet from archive
     */
    public static function restorePredmet($db, $conf, $user, $arhiva_id)
    {
        try {
            $db->begin();
            
            // Get arhiva details
            $sql = "SELECT ID_predmeta FROM " . MAIN_DB_PREFIX . "a_arhiva 
                    WHERE ID_arhive = " . (int)$arhiva_id . " AND status_arhive = 'active'";
            
            $resql = $db->query($sql);
            if (!$resql || $db->num_rows($resql) == 0) {
                return ['success' => false, 'error' => 'Archive record not found'];
            }
            
            $arhiva = $db->fetch_object($resql);
            $predmet_id = $arhiva->ID_predmeta;
            
            // Move files back to active directory
            $archive_dir = DOL_DATA_ROOT . '/ecm/SEUP/arhiva/predmet_' . $predmet_id . '/';
            $active_dir = DOL_DATA_ROOT . '/ecm/SEUP/predmet_' . $predmet_id . '/';
            
            $files_moved = 0;
            if (is_dir($archive_dir)) {
                dol_mkdir($active_dir);
                $files = scandir($archive_dir);
                foreach ($files as $file) {
                    if ($file !== '.' && $file !== '..') {
                        if (rename($archive_dir . $file, $active_dir . $file)) {
                            $files_moved++;
                        }
                    }
                }
                rmdir($archive_dir);
            }
            
            // Update ECM records
            $sql = "UPDATE " . MAIN_DB_PREFIX . "ecm_files 
                    SET filepath = 'SEUP/predmet_" . (int)$predmet_id . "/' 
                    WHERE filepath = 'SEUP/arhiva/predmet_" . (int)$predmet_id . "/'";
            $db->query($sql);
            
            // Mark archive as inactive
            $sql = "UPDATE " . MAIN_DB_PREFIX . "a_arhiva 
                    SET status_arhive = 'deleted' 
                    WHERE ID_arhive = " . (int)$arhiva_id;
            
            if (!$db->query($sql)) {
                throw new Exception($db->lasterror());
            }
            
            $db->commit();
            
            return [
                'success' => true,
                'files_moved' => $files_moved,
                'message' => "Predmet uspješno vraćen"
            ];
            
        } catch (Exception $e) {
            $db->rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Delete archive permanently
     */
    public static function deleteArchive($db, $conf, $user, $arhiva_id)
    {
        try {
            $db->begin();
            
            // Get arhiva details
            $sql = "SELECT ID_predmeta FROM " . MAIN_DB_PREFIX . "a_arhiva 
                    WHERE ID_arhive = " . (int)$arhiva_id . " AND status_arhive = 'active'";
            
            $resql = $db->query($sql);
            if (!$resql || $db->num_rows($resql) == 0) {
                return ['success' => false, 'error' => 'Archive record not found'];
            }
            
            $arhiva = $db->fetch_object($resql);
            $predmet_id = $arhiva->ID_predmeta;
            
            // Delete files from filesystem
            $archive_dir = DOL_DATA_ROOT . '/ecm/SEUP/arhiva/predmet_' . $predmet_id . '/';
            $files_deleted = 0;
            
            if (is_dir($archive_dir)) {
                $files = scandir($archive_dir);
                foreach ($files as $file) {
                    if ($file !== '.' && $file !== '..') {
                        if (unlink($archive_dir . $file)) {
                            $files_deleted++;
                        }
                    }
                }
                rmdir($archive_dir);
            }
            
            // Delete ECM records
            $sql = "DELETE FROM " . MAIN_DB_PREFIX . "ecm_files 
                    WHERE filepath = 'SEUP/arhiva/predmet_" . (int)$predmet_id . "/'";
            $db->query($sql);
            
            // Delete predmet record
            $sql = "DELETE FROM " . MAIN_DB_PREFIX . "a_predmet 
                    WHERE ID_predmeta = " . (int)$predmet_id;
            $db->query($sql);
            
            // Delete archive record
            $sql = "DELETE FROM " . MAIN_DB_PREFIX . "a_arhiva 
                    WHERE ID_arhive = " . (int)$arhiva_id;
            
            if (!$db->query($sql)) {
                throw new Exception($db->lasterror());
            }
            
            $db->commit();
            
            return [
                'success' => true,
                'files_deleted' => $files_deleted,
                'message' => "Arhiva je trajno obrisana"
            ];
            
        } catch (Exception $e) {
            $db->rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Build ORDER BY clause for predmeti
     */
    public static function buildOrderByKlasa($sortField, $sortOrder)
    {
        $orderByClause = '';
        
        if ($sortField === 'klasa_br') {
            $orderByClause = "ORDER BY p.klasa_br $sortOrder, p.sadrzaj $sortOrder, p.dosje_broj $sortOrder, p.predmet_rbr $sortOrder";
        } else {
            $orderByClause = "ORDER BY $sortField $sortOrder";
        }
        
        return $orderByClause;
    }

    /**
     * Build ORDER BY clause for klasifikacijske oznake
     */
    public static function buildKlasifikacijaOrderBy($sortField, $sortOrder, $alias = '')
    {
        $prefix = $alias ? $alias . '.' : '';
        
        if ($sortField === 'klasa_broj') {
            return "ORDER BY {$prefix}klasa_broj $sortOrder, {$prefix}sadrzaj $sortOrder, {$prefix}dosje_broj $sortOrder";
        } else {
            return "ORDER BY {$prefix}$sortField $sortOrder";
        }
    }
}