<?php

/**
 * Additional functions for predmet_helper
 * These should be merged into the main predmet_helper.class.php
 */

class Predmet_helper_additions 
{
    /**
     * Restore predmet from archive
     */
    public static function restorePredmet($db, $conf, $user, $arhiva_id)
    {
        try {
            $db->begin();
            
            // Get archive details
            $sql = "SELECT * FROM " . MAIN_DB_PREFIX . "a_arhiva 
                    WHERE ID_arhive = " . (int)$arhiva_id . " 
                    AND status_arhive = 'active'";
            
            $resql = $db->query($sql);
            if (!$resql || $db->num_rows($resql) == 0) {
                throw new Exception("Arhiva nije pronađena");
            }
            
            $arhiva = $db->fetch_object($resql);
            
            // Restore predmet data (if needed - depends on your archive structure)
            // This assumes predmet data is still in a_predmet table
            
            // Move files from archive to active directory
            $archive_dir = DOL_DATA_ROOT . '/ecm/SEUP/arhiva/predmet_' . $arhiva->ID_predmeta . '/';
            $active_dir = DOL_DATA_ROOT . '/ecm/SEUP/predmet_' . $arhiva->ID_predmeta . '/';
            
            $files_moved = 0;
            
            if (is_dir($archive_dir)) {
                // Create active directory
                if (!is_dir($active_dir)) {
                    dol_mkdir($active_dir);
                }
                
                // Move files
                $files = scandir($archive_dir);
                foreach ($files as $file) {
                    if ($file != '.' && $file != '..') {
                        $source = $archive_dir . $file;
                        $dest = $active_dir . $file;
                        
                        if (rename($source, $dest)) {
                            $files_moved++;
                        }
                    }
                }
                
                // Remove empty archive directory
                rmdir($archive_dir);
            }
            
            // Update archive status
            $sql = "UPDATE " . MAIN_DB_PREFIX . "a_arhiva 
                    SET status_arhive = 'restored',
                        datum_vracanja = NOW(),
                        fk_user_vratio = " . $user->id . "
                    WHERE ID_arhive = " . (int)$arhiva_id;
            
            $resql = $db->query($sql);
            if (!$resql) {
                throw new Exception("Greška pri ažuriranju arhive: " . $db->lasterror());
            }
            
            $db->commit();
            
            return [
                'success' => true,
                'message' => 'Predmet uspješno vraćen',
                'files_moved' => $files_moved
            ];
            
        } catch (Exception $e) {
            $db->rollback();
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Delete archive permanently
     */
    public static function deleteArchive($db, $conf, $user, $arhiva_id)
    {
        try {
            $db->begin();
            
            // Get archive details
            $sql = "SELECT * FROM " . MAIN_DB_PREFIX . "a_arhiva 
                    WHERE ID_arhive = " . (int)$arhiva_id . " 
                    AND status_arhive = 'active'";
            
            $resql = $db->query($sql);
            if (!$resql || $db->num_rows($resql) == 0) {
                throw new Exception("Arhiva nije pronađena");
            }
            
            $arhiva = $db->fetch_object($resql);
            
            // Delete files from filesystem
            $archive_dir = DOL_DATA_ROOT . '/ecm/SEUP/arhiva/predmet_' . $arhiva->ID_predmeta . '/';
            
            if (is_dir($archive_dir)) {
                // Delete all files in directory
                $files = scandir($archive_dir);
                foreach ($files as $file) {
                    if ($file != '.' && $file != '..') {
                        unlink($archive_dir . $file);
                    }
                }
                rmdir($archive_dir);
            }
            
            // Delete archive record
            $sql = "DELETE FROM " . MAIN_DB_PREFIX . "a_arhiva 
                    WHERE ID_arhive = " . (int)$arhiva_id;
            
            $resql = $db->query($sql);
            if (!$resql) {
                throw new Exception("Greška pri brisanju arhive: " . $db->lasterror());
            }
            
            $db->commit();
            
            return [
                'success' => true,
                'message' => 'Arhiva trajno obrisana'
            ];
            
        } catch (Exception $e) {
            $db->rollback();
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Check for new files in filesystem vs database
     */
    public static function checkForNewFiles($db, $conf, $caseId)
    {
        try {
            $upload_dir = DOL_DATA_ROOT . '/ecm/SEUP/predmet_' . $caseId . '/';
            
            // Get files from filesystem
            $filesystem_files = [];
            if (is_dir($upload_dir)) {
                $files = scandir($upload_dir);
                foreach ($files as $file) {
                    if ($file != '.' && $file != '..' && is_file($upload_dir . $file)) {
                        $filesystem_files[] = $file;
                    }
                }
            }
            
            // Get files from database
            $sql = "SELECT filename FROM " . MAIN_DB_PREFIX . "ecm_files 
                    WHERE filepath = 'SEUP/predmet_" . (int)$caseId . "/' 
                    AND entity = " . $conf->entity;
            
            $resql = $db->query($sql);
            $database_files = [];
            if ($resql) {
                while ($obj = $db->fetch_object($resql)) {
                    $database_files[] = $obj->filename;
                }
            }
            
            // Find new files (in filesystem but not in database)
            $new_files = array_diff($filesystem_files, $database_files);
            
            return [
                'success' => true,
                'filesystem_count' => count($filesystem_files),
                'database_count' => count($database_files),
                'new_files' => array_values($new_files),
                'has_new_files' => count($new_files) > 0
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Sync new files from filesystem to database
     */
    public static function syncNewFiles($db, $conf, $user, $caseId)
    {
        try {
            $upload_dir = DOL_DATA_ROOT . '/ecm/SEUP/predmet_' . $caseId . '/';
            $relativepath = 'SEUP/predmet_' . $caseId . '/';
            
            // Check for new files first
            $check_result = self::checkForNewFiles($db, $conf, $caseId);
            if (!$check_result['success'] || !$check_result['has_new_files']) {
                return [
                    'success' => true,
                    'files_added' => 0,
                    'message' => 'Nema novih datoteka za sync'
                ];
            }
            
            $db->begin();
            $files_added = 0;
            
            foreach ($check_result['new_files'] as $filename) {
                $fullpath = $upload_dir . $filename;
                
                if (!file_exists($fullpath)) {
                    continue;
                }
                
                // Generate urbroj
                $generatedUrbroj = self::generateUrbroj($db);
                
                // Create ECM record
                require_once DOL_DOCUMENT_ROOT . '/ecm/class/ecmfiles.class.php';
                $ecmfile = new EcmFiles($db);
                $ecmfile->filepath = $relativepath;
                $ecmfile->filename = $filename;
                $ecmfile->urbroj = $generatedUrbroj;
                $ecmfile->label = $filename;
                $ecmfile->entity = $conf->entity;
                $ecmfile->gen_or_uploaded = 'uploaded';
                $ecmfile->description = 'Synced file for predmet ' . $caseId;
                $ecmfile->fk_user_c = $user->id;
                $ecmfile->fk_user_m = $user->id;
                $ecmfile->filetype = dol_mimetype($filename);
                
                $result = $ecmfile->create($user);
                if ($result > 0) {
                    $files_added++;
                }
            }
            
            $db->commit();
            
            return [
                'success' => true,
                'files_added' => $files_added,
                'message' => "Uspješno dodano {$files_added} datoteka"
            ];
            
        } catch (Exception $e) {
            $db->rollback();
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate unique urbroj
     */
    private static function generateUrbroj($db)
    {
        $today = date('Ymd');
        $prefix = 'EXT-' . $today . '-';
        
        // Find highest number for today
        $sql = "SELECT urbroj FROM " . MAIN_DB_PREFIX . "ecm_files 
                WHERE urbroj LIKE '" . $db->escape($prefix) . "%' 
                ORDER BY urbroj DESC LIMIT 1";
        
        $resql = $db->query($sql);
        $next_num = 1;
        
        if ($resql && $obj = $db->fetch_object($resql)) {
            $last_urbroj = $obj->urbroj;
            $last_num = (int)substr($last_urbroj, -4);
            $next_num = $last_num + 1;
        }
        
        return $prefix . sprintf('%04d', $next_num);
    }
}