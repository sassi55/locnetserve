<?php
//-------------------------------------------------------------
// UtilsBackupCommand.php - Simplified Backup Handler for LocNetServe
//-------------------------------------------------------------
// Handles backup-related utils commands defined in cmd.json.
// (all, mysql, projects, list, restore)
//
// Author  : Sassi Souid
// Project : LocNetServe
// Version : 1.0.0
//-------------------------------------------------------------

namespace Utils\Commands;

use Core\Commands\Command;
use Database\Connection\ConnectionManager;
use Core\Utils\ZipManager;
use Core\Utils\FolderZipper;
use Core\Utils\MySQLAutoStarter;


class UtilsBackupCommand implements Command
{
    private array $colors;
    private array $config;

    public function __construct(array $colors = [], array $config = [])
    {
        $this->colors = $colors;
        $this->config = $config;
    }

    /**
     * Execute backup-related command
     */
    public function execute(string $action, array $args = []): string
    {
        switch ($action) {
            case 'all':
                return $this->backupAll();
            case 'config':
                return $this->backupConfig();
            case 'projects':
                return $this->backupProjects();
            case 'list':
                return $this->listBackups();
            case 'restore':
                return $this->handleRestore($args);
            default:
                return $this->colors['RED'] . "Unknown backup command: $action" . $this->colors['RESET'];
        }
    }

    private function backupAll(): string
    {
        return $this->colors['GREEN'] .
            "Starting full backup (MySQL + WWW + Configs)..." . PHP_EOL .
            "→ MySQL databases dumped" . PHP_EOL .
            "→ Project folders archived" . PHP_EOL .
            "→ Config files saved" . PHP_EOL .
            "Backup completed successfully " .
            $this->colors['RESET'];
    }




    /**
     * Clean up temporary directory
     */
    private function cleanupTempDir(string $tempDir): void
    {
        if (!is_dir($tempDir)) {
            return;
        }

        foreach (glob($tempDir . '*') as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        @rmdir($tempDir);
    }



//-------------------------------------------------------------
// Handles creation of dated project backup ZIP archives.
// Uses system command to improve performance and compatibility.
//-------------------------------------------------------------
private function backupProjects(): string
{
    $sourceDir = BASE_DIR . DIRECTORY_SEPARATOR . 'www';
    $backupDir = BASE_DIR . DIRECTORY_SEPARATOR . 'backups';

    // Create dated ZIP filename
    $timestamp = date('Y-m-d_H-i-s');
    $zipFile   = $backupDir . DIRECTORY_SEPARATOR . "projects" . DIRECTORY_SEPARATOR ."projects_{$timestamp}.zip";
    
    // Ensure backup directory exists
    if (!is_dir($backupDir)) {
        if (!mkdir($backupDir, 0777, true)) {
            return $this->colors['RED'] .
                "❌ Failed to create backup directory: $backupDir" . PHP_EOL .
                "Please check file permissions or available space." .
                $this->colors['RESET'];
        }
    }

    // Inform start
    echo $this->colors['YELLOW'] .
        "Creating project backups from $sourceDir ..." . PHP_EOL .
        $this->colors['RESET'];

    // Use system command to create ZIP
    $result = ZipManager::createZipWithSystemCommand($sourceDir, $zipFile);
    
    if ($result !== true) {
        return $this->colors['RED'] .
            "Failed to create project backup ZIP file." . PHP_EOL .
            "Error: $result" . PHP_EOL .
            "Check file permissions or available disk space." . PHP_EOL .
            $this->colors['RESET'];
    }

    // Verify ZIP file existence
    if (!file_exists($zipFile)) {
        return $this->colors['RED'] .
            "Backup failed: ZIP file not found after creation." . PHP_EOL .
            "Expected file: $zipFile" . PHP_EOL .
            $this->colors['RESET'];
    }

    // Compute file size using formatFileSize()
    $fileSize = $this->formatFileSize(filesize($zipFile));

    // Count project folders
    $projects = array_filter(glob($sourceDir . DIRECTORY_SEPARATOR . '*'), 'is_dir');
    $projectCount = count($projects);

    // Success output
    return $this->colors['GREEN'] .
        "Project backup completed successfully!" . PHP_EOL .
        "Projects archived: $projectCount" . PHP_EOL .
        "Destination: $zipFile" . PHP_EOL .
        "Archive size: $fileSize" . PHP_EOL .
        $this->colors['RESET'];
}






    private function listBackups(): string
    {
        if (!defined('FILES_BACKUP')) {
            return $this->colors['RED'] .
                "Error: FILES_BACKUP path not defined." .
                $this->colors['RESET'];
        }

        $root = FILES_BACKUP;
        $categories = ['config', 'mysql', 'projects'];

        if (!is_dir($root)) {
            return $this->colors['RED'] .
                "Backup root folder not found: " . $root .
                $this->colors['RESET'];
        }

        $output = $this->colors['CYAN'] . "Backup Summary" . $this->colors['RESET'] . PHP_EOL;
        $output .= "Root: " . $root . PHP_EOL . str_repeat('-', 60) . PHP_EOL;

        $totalFiles = 0;

        foreach ($categories as $cat) {
            $dir = $root . $cat . DIRECTORY_SEPARATOR;
            $output .= PHP_EOL . $this->colors['YELLOW'] . strtoupper($cat) . " Backups:" . $this->colors['RESET'] . PHP_EOL;

            if (!is_dir($dir)) {
                $output .= "  " . $this->colors['RED'] . "Missing folder: $dir" . $this->colors['RESET'] . PHP_EOL;
                continue;
            }

            $files = glob($dir . '*.zip');
            if (empty($files)) {
                $output .= "  " . $this->colors['YELLOW'] . "No backups found." . $this->colors['RESET'] . PHP_EOL;
                continue;
            }

            usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));

            foreach ($files as $file) {
                $filename = basename($file);
                $date = date("Y-m-d H:i", filemtime($file));
                $size =$this->formatFileSize(filesize($file));
                $output .= "  - " . $this->colors['GREEN'] . $filename . $this->colors['RESET'] .
                           " ($date | " .$this->colors['YELLOW'] . $size . $this->colors['RESET'] . ")" . PHP_EOL;
                $totalFiles++;
            }
        }

        $output .= PHP_EOL . str_repeat('-', 60) . PHP_EOL;
        $output .= $this->colors['CYAN'] . "Total backups found: " .
                   $this->colors['GREEN'] . $totalFiles . $this->colors['RESET'] . PHP_EOL;

        return $output;
    }

	/**
	 * Handle restore using UtilsRestoreCommand
	 */
	private function handleRestore(array $args): string
	{
		$restoreHandler = new UtilsRestoreCommand($this->colors, $this->config);
		return $restoreHandler->execute('restore', $args);
	}

	/**
	 * Format file sizes intelligently (B, KB, MB)
	 */
	private function formatFileSize(int $bytes): string
	{
		if ($bytes < 1024) {
			return $bytes . ' B';
		} elseif ($bytes < 1048576) {
			return number_format($bytes / 1024, 2) . ' KB';
		} else {
			return number_format($bytes / 1048576, 2) . ' MB';
		}
	}
	
	/**
	 * Backup configuration files using ZipManager
	 */
	private function backupConfig(): string
	{
		if (!defined('FILES_BACKUP')) {
			return $this->colors['RED'] . "Error: FILES_BACKUP constant not defined." . $this->colors['RESET'];
		}

		$backupDir = FILES_BACKUP . 'config' . DIRECTORY_SEPARATOR;
		if (!is_dir($backupDir)) {
			mkdir($backupDir, 0777, true);
		}

		// Liste des fichiers de configuration à sauvegarder
		$configFiles = $this->getConfigFilesToBackup();
		
		if (empty($configFiles)) {
			return $this->colors['YELLOW'] . "No configuration files found to backup." . $this->colors['RESET'];
		}

		$timestamp = date('Y-m-d_H-i-s');
		$zipFile = $backupDir . "config_backup_{$timestamp}.zip";
		
		// Créer un dossier temporaire avec les fichiers de config
		$tempDir = $backupDir . 'temp_config_' . $timestamp . DIRECTORY_SEPARATOR;
		if (!is_dir($tempDir)) {
			mkdir($tempDir, 0777, true);
		}

		$successCount = 0;
		$failedFiles = [];

		// Copier les fichiers dans le dossier temporaire
		foreach ($configFiles as $configFile) {
			if (file_exists($configFile)) {
				$destFile = $tempDir . basename($configFile);
				if (copy($configFile, $destFile)) {
					$successCount++;
				} else {
					$failedFiles[] = basename($configFile);
				}
			}
		}

		if ($successCount === 0) {
			$this->cleanupTempDir($tempDir);
			return $this->colors['RED'] . "No configuration files could be copied to temporary directory." . $this->colors['RESET'];
		}

		// Utiliser ZipManager pour créer le ZIP
		$result = ZipManager::createZipWithSystemCommand($tempDir, $zipFile);
		
		// Nettoyer le dossier temporaire
		$this->cleanupTempDir($tempDir);

		if ($result !== true) {
			return $this->colors['RED'] . 
				   "Failed to create config backup ZIP file." . PHP_EOL .
				   "Error: $result" . 
				   $this->colors['RESET'];
		}

		if (!file_exists($zipFile)) {
			return $this->colors['RED'] . 
				   "Backup failed: ZIP file not found after creation." . 
				   $this->colors['RESET'];
		}

		$fileSize = $this->formatFileSize(filesize($zipFile));

		$result = $this->colors['GREEN'] .
			"Configuration backup completed successfully" . PHP_EOL .
			"Files backed up: " . $successCount . "/" . count($configFiles) . 
			$this->colors['RESET'];

		if (!empty($failedFiles)) {
			$result .= PHP_EOL . $this->colors['YELLOW'] .
				"Failed files: " . implode(", ", $failedFiles) .
				$this->colors['RESET'];
		}

		$result .= PHP_EOL . "File saved: " . $this->colors['CYAN'] . basename($zipFile) . 
				  $this->colors['RESET'] . " ($fileSize)";

		return $result;
	}
/**
 * Get list of configuration files to backup
 */
private function getConfigFilesToBackup(): array
{
    $configFiles = [];

    // Fichiers de configuration principaux
    $mainConfigs = [
        BASE_DIR . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.json',
        BASE_DIR . DIRECTORY_SEPARATOR . 'config.json',
        CONF_DIR . 'configs.php',
        CONF_DIR . 'config.json'
    ];

    // Fichiers Apache
    $apacheConfigs = [
        BASE_DIR . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'apache' . DIRECTORY_SEPARATOR . 'conf' . DIRECTORY_SEPARATOR . 'httpd.conf',
        BASE_DIR . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'apache' . DIRECTORY_SEPARATOR . 'conf' . DIRECTORY_SEPARATOR . 'extra' . DIRECTORY_SEPARATOR . 'httpd-vhosts.conf',
        BASE_DIR . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'apache' . DIRECTORY_SEPARATOR . 'conf' . DIRECTORY_SEPARATOR . 'extra' . DIRECTORY_SEPARATOR . 'httpd-ssl.conf'
    ];

    // Fichiers PHP
    $phpConfigs = [
        BASE_DIR . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . 'php.ini',
        BASE_DIR . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . 'conf.d' . DIRECTORY_SEPARATOR . 'php.ini'
    ];

    // Fichiers MySQL
    $mysqlConfigs = [
        BASE_DIR . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'mysql' . DIRECTORY_SEPARATOR . 'my.ini',
        BASE_DIR . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'mysql' . DIRECTORY_SEPARATOR . 'my.cnf'
    ];

    // Combiner toutes les listes
    $allConfigs = array_merge($mainConfigs, $apacheConfigs, $phpConfigs, $mysqlConfigs);

    // Garder seulement les fichiers qui existent
    foreach ($allConfigs as $configFile) {
        if (file_exists($configFile)) {
            $configFiles[] = $configFile;
        }
    }

    return $configFiles;
}

    public function getHelp(): string
    {
        return <<<HELP
{$this->colors['CYAN']}Backup Commands (-u backup):{$this->colors['RESET']}

  -u backup all                 Backup MySQL + WWW + configs
  -u backup mysql               Backup MySQL databases
  -u backup projects            Backup projects in www
  -u backup list                List available backups
  -u backup restore <zip>       Restore from a backup
HELP;
    }
}
