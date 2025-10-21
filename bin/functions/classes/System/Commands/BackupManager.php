<?php
//-------------------------------------------------------------
// BackupManager.php - Backup management for LocNetServe
//-------------------------------------------------------------
// Handles MySQL, projects, and config backups, restoration, 
// and backup listing.
//-------------------------------------------------------------
// Author : Sassi Souid
// Project: LocNetServe
// Version: 1.0.0
//-------------------------------------------------------------

namespace Utils\Commands;

class BackupManager
{
    protected string $backupDir;
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->backupDir = $config['paths']['base'] . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR;

        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
    }

    /**
     * Backup everything: MySQL + projects + configs
     */
    public function backupAll(): string
    {
        $messages = [];
        $messages[] = $this->backupMySQL();
        $messages[] = $this->backupProjects();
        $messages[] = $this->backupConfigs();
        return implode(PHP_EOL, $messages);
    }

    /**
     * Backup MySQL databases
     */
    public function backupMySQL(): string
    {
        $timestamp = date('Ymd_His');
        $filename = $this->backupDir . "mysql_backup_{$timestamp}.sql";

        $user = $this->config['mysql']['user'] ?? 'root';
        $pass = $this->config['mysql']['password'] ?? '';
        $host = $this->config['mysql']['host'] ?? '127.0.0.1';

        $command = "mysqldump -u$user -p$pass -h$host --all-databases > \"$filename\"";

        exec($command, $output, $return_var);

        if ($return_var === 0) {
            return "MySQL backup created: $filename";
        } else {
            return "Error: MySQL backup failed. Command: $command";
        }
    }

    /**
     * Backup projects (www folder)
     */
    public function backupProjects(): string
    {
        $wwwPath = $this->config['paths']['www'] ?? BASE_DIR . DIRECTORY_SEPARATOR . 'www';
        $timestamp = date('Ymd_His');
        $zipFile = $this->backupDir . "projects_backup_{$timestamp}.zip";

        $zip = new \ZipArchive();
        if ($zip->open($zipFile, \ZipArchive::CREATE) !== true) {
            return "Error: Cannot create projects backup zip file: $zipFile";
        }

        $this->addFolderToZip($wwwPath, $zip, strlen($wwwPath) + 1);
        $zip->close();

        return "Projects backup created: $zipFile";
    }

    /**
     * Backup configuration files
     */
    public function backupConfigs(): string
    {
        $configPath = $this->config['paths']['config'] ?? BASE_DIR . DIRECTORY_SEPARATOR . 'config';
        $timestamp = date('Ymd_His');
        $zipFile = $this->backupDir . "configs_backup_{$timestamp}.zip";

        $zip = new \ZipArchive();
        if ($zip->open($zipFile, \ZipArchive::CREATE) !== true) {
            return "Error: Cannot create configs backup zip file: $zipFile";
        }

        $this->addFolderToZip($configPath, $zip, strlen($configPath) + 1);
        $zip->close();

        return "Configs backup created: $zipFile";
    }

    /**
     * List all available backups
     */
    public function listBackups(): string
    {
        $files = glob($this->backupDir . '*');
        if (!$files) return "No backups found.";

        sort($files);
        return "Available backups:" . PHP_EOL . implode(PHP_EOL, $files);
    }

    /**
     * Restore a backup from a zip or SQL file
     */
    public function restoreBackup(string $backupFile): string
    {
        $fullPath = $this->backupDir . $backupFile;
        if (!file_exists($fullPath)) return "Backup file not found: $backupFile";

        $ext = pathinfo($backupFile, PATHINFO_EXTENSION);
        switch ($ext) {
            case 'zip':
                $zip = new \ZipArchive();
                if ($zip->open($fullPath) === true) {
                    $zip->extractTo(BASE_DIR);
                    $zip->close();
                    return "Backup restored: $backupFile";
                } else {
                    return "Error: Cannot extract backup zip: $backupFile";
                }
            case 'sql':
                $user = $this->config['mysql']['user'] ?? 'root';
                $pass = $this->config['mysql']['password'] ?? '';
                $host = $this->config['mysql']['host'] ?? '127.0.0.1';
                $command = "mysql -u$user -p$pass -h$host < \"$fullPath\"";
                exec($command, $output, $return_var);
                return $return_var === 0 ? "MySQL restored: $backupFile" : "Error restoring MySQL backup: $backupFile";
            default:
                return "Unsupported backup file type: $backupFile";
        }
    }

    /**
     * Recursive folder to zip
     */
    protected function addFolderToZip(string $folder, \ZipArchive $zip, int $baseLength): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($folder),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, $baseLength);
                $zip->addFile($filePath, $relativePath);
            }
        }
    }
}
