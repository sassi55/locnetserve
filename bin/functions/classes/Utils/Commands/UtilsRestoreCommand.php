<?php
//-------------------------------------------------------------
// UtilsRestoreCommand.php - Restore Command Handler for LocNetServe
//-------------------------------------------------------------
// Handles all backup restoration operations for MySQL, projects, and configs.
//
// Author  : Sassi Souid
// Email   : locnetserve@gmail.com
// Project : LocNetServe
// Version : 1.0.0
//-------------------------------------------------------------

namespace Utils\Commands;

use Core\Commands\Command;

class UtilsRestoreCommand implements Command
{
    private array $colors;
    private array $config;

    public function __construct(array $colors = [], array $config = [])
    {
        $this->colors = $colors;
        $this->config = $config;
    }

    /**
     * Execute restore command
     */
    public function execute(string $action, array $args = []): string
    {
        return $this->restoreBackup($args);
    }

    /**
     * Main restore backup method
     */
    private function restoreBackup(array $args): string
    {
        if (empty($args)) {
            return $this->showUsage();
        }

        $backupFile = $args[0];
        $backupFile = $this->findBackupFile($backupFile);
        
        if (!$backupFile) {
            return $this->colors['RED'] . 
                   "Backup file not found: " . $args[0] . PHP_EOL . PHP_EOL .
                   $this->listAvailableBackups() . 
                   $this->colors['RESET'];
        }

        $backupType = $this->detectBackupType($backupFile);
        
        switch ($backupType) {
            case 'mysql':
                return $this->restoreMySQL($backupFile);
            case 'projects':
                return $this->restoreProjects($backupFile);
            case 'config':
                return $this->restoreConfig($backupFile);
            default:
                return $this->colors['RED'] . 
                       "Unsupported backup type or corrupted file." . 
                       $this->colors['RESET'];
        }
    }

    /**
     * Show usage and available backups
     */
    private function showUsage(): string
    {
        return $this->colors['YELLOW'] . 
               "Usage: lns -u backup restore <backup_file>" . PHP_EOL . PHP_EOL .
               "Available backups:" . PHP_EOL . 
               $this->listAvailableBackups() . 
               $this->colors['RESET'];
    }

    /**
     * Find backup file in backup directories
     */
    private function findBackupFile(string $filename): string
    {
        $backupDirs = [
            FILES_BACKUP . 'mysql' . DIRECTORY_SEPARATOR,
            FILES_BACKUP . 'projects' . DIRECTORY_SEPARATOR,
            FILES_BACKUP . 'config' . DIRECTORY_SEPARATOR,
            FILES_BACKUP
        ];

        foreach ($backupDirs as $dir) {
            $fullPath = $dir . $filename;
            if (file_exists($fullPath)) {
                return $fullPath;
            }
            
            // Ajouter .zip si non fourni
            if (pathinfo($filename, PATHINFO_EXTENSION) !== 'zip') {
                $fullPath = $dir . $filename . '.zip';
                if (file_exists($fullPath)) {
                    return $fullPath;
                }
            }
        }

        return '';
    }

    /**
     * Detect backup type from filename
     */
    private function detectBackupType(string $backupFile): string
    {
        $filename = basename($backupFile);
        
        if (strpos($filename, 'mysql_backup') === 0) {
            return 'mysql';
        } elseif (strpos($filename, 'projects_') === 0) {
            return 'projects';
        } elseif (strpos($filename, 'config_backup') === 0) {
            return 'config';
        }
        
        return 'unknown';
    }

    /**
     * Restore MySQL backup
     */
    private function restoreMySQL(string $backupFile): string
    {
        $output = $this->colors['CYAN'] . "Restoring MySQL backup..." . $this->colors['RESET'] . PHP_EOL;
        
        $tempDir = $this->createTempDir();
        $this->extractZip($backupFile, $tempDir);
        
        $sqlFiles = glob($tempDir . '*.sql');
        if (empty($sqlFiles)) {
            $this->cleanupTempDir($tempDir);
            return $this->colors['YELLOW'] . "No SQL files found in backup." . $this->colors['RESET'];
        }
        
        $importedCount = $this->importSQLFiles($sqlFiles);
        $this->cleanupTempDir($tempDir);
        
        $output .= $this->colors['GREEN'] . 
                  "MySQL restore completed. Databases imported: $importedCount/" . count($sqlFiles) . 
                  $this->colors['RESET'];
        
        return $output;
    }

    /**
     * Restore projects backup
     */
    private function restoreProjects(string $backupFile): string
    {
        $restoreDir = BASE_DIR . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . 
                     'restored_' . date('Y-m-d_H-i-s');
        
        if (!is_dir($restoreDir)) {
            mkdir($restoreDir, 0777, true);
        }
        
        $this->extractZip($backupFile, $restoreDir);
        $projectCount = count(glob($restoreDir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR));
        
        return $this->colors['GREEN'] . 
               "Projects backup restored successfully!" . PHP_EOL .
               "Projects restored: $projectCount" . PHP_EOL .
               "Location: $restoreDir" . 
               $this->colors['RESET'];
    }

    /**
     * Restore config backup
     */
private function restoreConfig(string $backupFile): string
{
    $tempDir = $this->createTempDir();
    $this->extractZip($backupFile, $tempDir);
    
    // Chercher les fichiers de configuration de différentes manières
    $configFiles = [];
    
    // 1. Dans le sous-dossier config/
    $configFiles = array_merge($configFiles, glob($tempDir . 'config' . DIRECTORY_SEPARATOR . '*'));
    
    // 2. À la racine du ZIP
    $configFiles = array_merge($configFiles, glob($tempDir . '*.json'));
    $configFiles = array_merge($configFiles, glob($tempDir . '*.conf'));
    $configFiles = array_merge($configFiles, glob($tempDir . '*.ini'));
    $configFiles = array_merge($configFiles, glob($tempDir . '*.php'));
    
    // 3. Recherche récursive
    $iterator = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($tempDir, \RecursiveDirectoryIterator::SKIP_DOTS),
        \RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $ext = $file->getExtension();
            if (in_array($ext, ['json', 'conf', 'ini', 'php', 'cnf'])) {
                $configFiles[] = $file->getPathname();
            }
        }
    }
    
    // Éliminer les doublons
    $configFiles = array_unique($configFiles);
    
    $restoredCount = $this->restoreConfigFiles($configFiles);
    $this->cleanupTempDir($tempDir);
    
    return $this->colors['GREEN'] . 
           "Configuration restore completed!" . PHP_EOL .
           "Files found: " . count($configFiles) . PHP_EOL .
           "Files restored: $restoredCount" . 
           $this->colors['RESET'];
}

    /**
     * Create temporary directory
     */
    private function createTempDir(): string
    {
        $tempDir = FILES_BACKUP . 'temp_restore_' . date('Y-m-d_H-i-s') . DIRECTORY_SEPARATOR;
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }
        return $tempDir;
    }

    /**
     * Extract ZIP file to directory
     */
    private function extractZip(string $zipFile, string $targetDir): bool
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipFile) !== true) {
            return false;
        }
        
        $zip->extractTo($targetDir);
        $zip->close();
        return true;
    }

    /**
     * Import SQL files to MySQL
     */
    private function importSQLFiles(array $sqlFiles): int
    {
        $mysqlConfig = $this->getMySQLConfig();
        $mysqlPath = $this->getMySQLExecutable();
        $importedCount = 0;

        foreach ($sqlFiles as $sqlFile) {
            $dbName = pathinfo($sqlFile, PATHINFO_FILENAME);
            
            $cmd = "\"{$mysqlPath}\" -h {$mysqlConfig['host']} -P {$mysqlConfig['port']} " .
                   "-u {$mysqlConfig['user']} " .
                   (!empty($mysqlConfig['pass']) ? "-p{$mysqlConfig['pass']} " : "") .
                   "{$dbName} < \"{$sqlFile}\" 2>&1";
            
            exec($cmd, $output, $returnCode);
            
            if ($returnCode === 0) {
                $importedCount++;
            }
        }
        
        return $importedCount;
    }

    /**
     * Restore configuration files
     */

private function restoreConfigFiles(array $configFiles): int
{
    $restoredCount = 0;
    
    foreach ($configFiles as $configFile) {
        $filename = basename($configFile);
        $destPath = $this->getConfigDestination($filename);
        
        if ($destPath) {
            // Créer le dossier de destination si nécessaire
            $destDir = dirname($destPath);
            if (!is_dir($destDir)) {
                mkdir($destDir, 0777, true);
            }
            
            // Sauvegarder l'ancien fichier
            if (file_exists($destPath)) {
                $backupPath = $destPath . '.backup_' . date('Y-m-d_H-i-s');
                copy($destPath, $backupPath);
            }
            
            // Copier le nouveau fichier
            if (copy($configFile, $destPath)) {
                $restoredCount++;
                echo $this->colors['GREEN'] . "Restored: $filename → $destPath" . $this->colors['RESET'] . PHP_EOL;
            } else {
                echo $this->colors['RED'] . "Failed to restore: $filename" . $this->colors['RESET'] . PHP_EOL;
            }
        } else {
            echo $this->colors['YELLOW'] . "? No destination for: $filename" . $this->colors['RESET'] . PHP_EOL;
        }
    }
    
    return $restoredCount;
}

    /**
     * Get MySQL configuration
     */
    private function getMySQLConfig(): array
    {
        return [
            'host' => $this->config['mysql']['host'] ?? 'localhost',
            'port' => $this->config['mysql']['port'] ?? 3306,
            'user' => $this->config['mysql']['user'] ?? 'root',
            'pass' => $this->config['mysql']['pass'] ?? ''
        ];
    }

    /**
     * Get MySQL executable path
     */
    private function getMySQLExecutable(): string
    {
        return BASE_DIR . DIRECTORY_SEPARATOR . "bin" . DIRECTORY_SEPARATOR .
               "mysql" . DIRECTORY_SEPARATOR . "bin" . DIRECTORY_SEPARATOR . "mysql.exe";
    }

    /**
     * Get configuration file destination
     */
    private function getConfigDestination(string $filename): string
    {
        $configMap = [
            'config.json' => BASE_DIR . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.json',
            'httpd.conf' => BASE_DIR . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'apache' . DIRECTORY_SEPARATOR . 'conf' . DIRECTORY_SEPARATOR . 'httpd.conf',
            'httpd-ssl.conf' => BASE_DIR . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'apache' . DIRECTORY_SEPARATOR . 'conf' . DIRECTORY_SEPARATOR . 'extra' . DIRECTORY_SEPARATOR . 'httpd-ssl.conf',
            'httpd-vhosts.conf' => BASE_DIR . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'apache' . DIRECTORY_SEPARATOR . 'conf' . DIRECTORY_SEPARATOR . 'extra' . DIRECTORY_SEPARATOR . 'httpd-vhosts.conf',
            'php.ini' => BASE_DIR . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . 'php.ini',
            'my.ini' => BASE_DIR . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'mysql' . DIRECTORY_SEPARATOR . 'my.ini',
            'configs.php' => BASE_DIR . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'functions' . DIRECTORY_SEPARATOR . 'conf'. DIRECTORY_SEPARATOR . 'configs.php'
        ];
        
        return $configMap[$filename] ?? '';
    }

    /**
     * List available backups
     */
    private function listAvailableBackups(): string
    {
        $backupDirs = [
            'MySQL' => FILES_BACKUP . 'mysql' . DIRECTORY_SEPARATOR,
            'Projects' => FILES_BACKUP . 'projects' . DIRECTORY_SEPARATOR,
            'Config' => FILES_BACKUP . 'config' . DIRECTORY_SEPARATOR
        ];
        
        $output = '';
        
        foreach ($backupDirs as $type => $dir) {
            if (!is_dir($dir)) continue;
            
            $files = glob($dir . '*.zip');
            if (empty($files)) continue;
            
            $output .= $this->colors['CYAN'] . "$type:" . $this->colors['RESET'] . PHP_EOL;
            
            usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
            
            foreach (array_slice($files, 0, 3) as $file) {
                $filename = basename($file);
                $output .= "  - " . $this->colors['GREEN'] . $filename . $this->colors['RESET'] . PHP_EOL;
            }
            $output .= PHP_EOL;
        }
        
        return $output ?: "  No backup files found." . PHP_EOL;
    }

    /**
     * Clean up temporary directory
     */
    private function cleanupTempDir(string $tempDir): void
    {
        if (!is_dir($tempDir)) return;
        
        foreach (glob($tempDir . '*') as $file) {
            if (is_file($file)) unlink($file);
        }
        
        @rmdir($tempDir);
    }

    /**
     * Display help information
     */
    public function getHelp(): string
    {
        return <<<HELP
{$this->colors['CYAN']}Restore Command (-u backup restore):{$this->colors['RESET']}

  lns -u backup restore <file>    Restore from backup file
  lns -u backup restore           Show available backups

HELP;
    }
}