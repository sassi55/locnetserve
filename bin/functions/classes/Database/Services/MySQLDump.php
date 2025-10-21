<?php
//-------------------------------------------------------------
// MySQLDump.php - MySQL Backup Service for LocNetServe
//-------------------------------------------------------------
// Handles database backup and restore operations.
// Provides secure database export and import functionality.
//
// Author : Sassi Souid
// Project: LocNetServe
// Version: 1.0.0
//-------------------------------------------------------------

namespace Database\Services;

use Database\Connection\MySQLConnection;
use Database\Formatters\DatabaseFormatter;

class MySQLDump
{
    private array $config;
    private DatabaseFormatter $formatter;
    private array $colors;
    private string $backupDir;

    /**
     * MySQLDump constructor.
     *
     * @param array $config Application configuration
     * @param DatabaseFormatter $formatter Output formatter
     */
    public function __construct(array $config, DatabaseFormatter $formatter, array $colors)
    {
        $this->config = $config;
        $this->formatter = $formatter;
        $this->colors = $colors;
        $this->backupDir = $this->getBackupDirectory();
    }

    

    /**
     * Get backup directory
     */
    private function getBackupDirectory(): string
    {
        $baseDir = $this->config['paths']['base'] ?? dirname(__DIR__, 5);
        $backupDir = $baseDir . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . 'mysql';
        
        // Create backup directory if it doesn't exist
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        return $backupDir;
    }

	/**
	 * Export a database to SQL file.
	 *
	 * @param string $database Database name to export
	 * @param string $outputPath Output file path or directory (optional)
	 * @return string Success or error message
	 */
	public function exportDatabase(string $database, string $outputPath = ''): string
	{
		// Validate database name
		if (empty($database)) {
			return $this->formatter->formatError("Database name cannot be empty.");
		}

		// Check if database exists
		if (!$this->databaseExists($database)) {
			return $this->formatter->formatError("Database '$database' does not exist.");
		}

		// Determine output file path
		$outputFile = $this->resolveOutputPath($database, $outputPath);
		if (strpos($outputFile, 'Error:') !== false) {
			return $outputFile; // Retourne l'erreur
		}

		try {
			$mysqldump = $this->getMySQLDumpPath();
			
			if (!file_exists($mysqldump)) {
				return $this->formatter->formatError("mysqldump executable not found: $mysqldump");
			}

			$host = $this->config['services']['MySQL']['host'] ?? 'localhost';
			$port = $this->config['services']['MySQL']['port'] ?? 3306;
			$user = $this->config['services']['MySQL']['user'] ?? 'root';
			$pass = $this->config['services']['MySQL']['password'] ?? '';

			// Build mysqldump command
			$cmd = '"' . $mysqldump . '"' .
				   ' -h ' . escapeshellarg($host) .
				   ' -P ' . escapeshellarg($port) .
				   ' -u ' . escapeshellarg($user) .
				   ($pass !== '' ? ' -p' . escapeshellarg($pass) : '') .
				   ' --single-transaction' .
				   ' --routines' .
				   ' --events' .
				   ' --triggers' .
				   ' --add-drop-database' .
				   ' --databases ' . escapeshellarg($database) .
				   ' > "' . $outputFile . '" 2>&1';

			exec($cmd, $output, $return_var);

			if ($return_var !== 0) {
				$error = implode("\n", $output);
				// Clean up failed backup file safely
				$this->safeUnlink($outputFile);
				return $this->formatter->formatError("Export failed: " . $error);
			}

			// Verify the backup file was created and has content
			if (!file_exists($outputFile) || filesize($outputFile) === 0) {
				$this->safeUnlink($outputFile);
				return $this->formatter->formatError("Export failed: Backup file is empty or was not created.");
			}

			$fileSize = $this->formatFileSize(filesize($outputFile));
			
			return $this->formatter->formatSuccess("Database '$database' exported successfully to: $outputFile ($fileSize)");

		} catch (\Exception $e) {
			$this->safeUnlink($outputFile);
			return $this->formatter->formatError("Export exception: " . $e->getMessage());
		}
	}

	/**
	 * Resolve output file path from user input
	 */
	private function resolveOutputPath(string $database, string $outputPath): string
	{
		// If no path provided, use default backup directory
		if (empty($outputPath)) {
			$timestamp = date('Y-m-d_H-i-s');
			return $this->backupDir . DIRECTORY_SEPARATOR . $database . '_' . $timestamp . '.sql';
		}

		// If it's a directory, create filename inside it
		if (is_dir($outputPath)) {
			$timestamp = date('Y-m-d_H-i-s');
			return rtrim($outputPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $database . '_' . $timestamp . '.sql';
		}

		// If path ends with directory separator, treat as directory
		if (substr($outputPath, -1) === DIRECTORY_SEPARATOR) {
			$timestamp = date('Y-m-d_H-i-s');
			return $outputPath . $database . '_' . $timestamp . '.sql';
		}

		// It's probably a file path
		$outputDir = dirname($outputPath);
		if (!is_dir($outputDir)) {
			if (!mkdir($outputDir, 0755, true)) {
				return $this->formatter->formatError("Cannot create output directory: $outputDir");
			}
		}

		// Ensure .sql extension
		if (strtolower(substr($outputPath, -4)) !== '.sql') {
			$outputPath .= '.sql';
		}

		return $outputPath;
	}

	/**
	 * Safely delete a file (only if it exists and is a file)
	 */
	private function safeUnlink(string $filePath): void
	{
		if (file_exists($filePath) && is_file($filePath)) {
			@unlink($filePath);
		}
	}

    /**
     * Import a SQL file into a database.
     *
     * @param string $database Target database name
     * @param string $inputFile SQL file to import
     * @return string Success or error message
     */
    public function importDatabase(string $database, string $inputFile): string
    {
        // Validate inputs
        if (empty($database)) {
            return $this->formatter->formatError("Database name cannot be empty.");
        }

        if (empty($inputFile)) {
            return $this->formatter->formatError("Input file cannot be empty.");
        }

        if (!file_exists($inputFile)) {
            return $this->formatter->formatError("Input file not found: $inputFile");
        }

        if (filesize($inputFile) === 0) {
            return $this->formatter->formatError("Input file is empty: $inputFile");
        }

        try {
            $mysql = $this->getMySQLPath();
            
            if (!file_exists($mysql)) {
                return $this->formatter->formatError("MySQL client not found: $mysql");
            }

            $host = $this->config['services']['MySQL']['host'] ?? 'localhost';
            $port = $this->config['services']['MySQL']['port'] ?? 3306;
            $user = $this->config['services']['MySQL']['user'] ?? 'root';
            $pass = $this->config['services']['MySQL']['password'] ?? '';

            // Create database if it doesn't exist
            if (!$this->databaseExists($database)) {
                $createResult = $this->createDatabase($database);
                if (strpos($createResult, 'Error:') !== false) {
                    return $createResult;
                }
            }

            // Build import command
            $cmd = '"' . $mysql . '"' .
                   ' -h ' . escapeshellarg($host) .
                   ' -P ' . escapeshellarg($port) .
                   ' -u ' . escapeshellarg($user) .
                   ($pass !== '' ? ' -p' . escapeshellarg($pass) : '') .
                   ' ' . escapeshellarg($database) .
                   ' < "' . $inputFile . '" 2>&1';

            exec($cmd, $output, $return_var);

            if ($return_var !== 0) {
                $error = implode("\n", $output);
                return $this->formatter->formatError("Import failed: " . $error);
            }

            return $this->formatter->formatSuccess("✅ Database '$database' imported successfully from: $inputFile");

        } catch (\Exception $e) {
            return $this->formatter->formatError("Import exception: " . $e->getMessage());
        }
    }

    /**
     * Export all databases to separate files.
     *
     * @return string Success or error message
     */
    public function exportAllDatabases(): string
    {
        try {
            $databases = $this->getAllDatabases();
            $successCount = 0;
            $errorCount = 0;
            $results = [];

            foreach ($databases as $database) {
                // Skip system databases
                if (in_array(strtolower($database), ['information_schema', 'performance_schema', 'mysql', 'sys'])) {
                    continue;
                }

                $result = $this->exportDatabase($database);
                if (strpos($result, '✅') !== false) {
                    $successCount++;
                    $results[] = "✅ $database: Success";
                } else {
                    $errorCount++;
                    $results[] = "❌ $database: " . strip_tags($result);
                }
            }

            $output = $this->formatter->formatInfo("=== Database Export Summary ===") . PHP_EOL . PHP_EOL;
            $output .= $this->formatter->formatSuccess("▲ Export Results:") . PHP_EOL;
            $output .= "  " . $this->formatter->formatInfo("• Successful: ") . $this->colors['GREEN'] . $successCount . $this->colors['RESET'] . PHP_EOL;
            $output .= "  " . $this->formatter->formatInfo("• Failed: ") . $this->colors['RED'] . $errorCount . $this->colors['RESET'] . PHP_EOL;
            $output .= "  " . $this->formatter->formatInfo("• Total: ") . $this->colors['CYAN'] . count($databases) . $this->colors['RESET'] . PHP_EOL;

            $output .= PHP_EOL . $this->formatter->formatSuccess("▲ Details:") . PHP_EOL;
            foreach ($results as $result) {
                $output .= "  " . $result . PHP_EOL;
            }

            return $output;

        } catch (\Exception $e) {
            return $this->formatter->formatError("Export all failed: " . $e->getMessage());
        }
    }

    /**
     * List available backup files.
     *
     * @return string Formatted backup list
     */
    public function listBackups(): string
    {
        $backupFiles = glob($this->backupDir . DIRECTORY_SEPARATOR . '*.sql');
        
        if (empty($backupFiles)) {
            return $this->formatter->formatWarning("No backup files found in: " . $this->backupDir);
        }

        // Sort by modification time (newest first)
        usort($backupFiles, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        $output = $this->formatter->formatInfo("=== Available MySQL Backups ===") . PHP_EOL . PHP_EOL;
        $output .= $this->formatter->formatSuccess("▲ Backup Files:") . PHP_EOL;

        foreach ($backupFiles as $file) {
            $filename = basename($file);
            $fileSize = $this->formatFileSize(filesize($file));
            $modified = date('Y-m-d H:i:s', filemtime($file));
            
            $output .= "  " . $this->formatter->formatInfo("• ") . 
                      $this->colors['GREEN'] . $filename . $this->colors['RESET'] . 
                      " (" . $this->colors['CYAN'] . $fileSize . $this->colors['RESET'] . ") - " .
                      $this->colors['YELLOW'] . $modified . $this->colors['RESET'] . PHP_EOL;
        }

        $output .= PHP_EOL . $this->formatter->formatInfo("Total: " . count($backupFiles) . " backup files");
        $output .= PHP_EOL . $this->formatter->formatInfo("Location: " . $this->backupDir);

        return $output;
    }

    /**
     * Get mysqldump executable path
     */
    private function getMySQLDumpPath(): string
    {
        $mysql_bin = rtrim($this->config['paths']['mysql'] ?? '', DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'bin';
        return $mysql_bin . DIRECTORY_SEPARATOR . 'mysqldump' . (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? '.exe' : '');
    }

    /**
     * Get mysql executable path
     */
    private function getMySQLPath(): string
    {
        $mysql_bin = rtrim($this->config['paths']['mysql'] ?? '', DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'bin';
        return $mysql_bin . DIRECTORY_SEPARATOR . 'mysql' . (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? '.exe' : '');
    }

    /**
     * Check if database exists
     */
    private function databaseExists(string $database): bool
    {
        try {
            $connection = $this->getConnection();
            $stmt = $connection->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?", [$database]);
            return $stmt !== false && $stmt->fetch() !== false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Create a database
     */
    private function createDatabase(string $database): string
    {
        try {
            $connection = $this->getConnection();
            $stmt = $connection->query("CREATE DATABASE `$database`");
            return $stmt !== false ? 
                $this->formatter->formatSuccess("Database '$database' created") :
                $this->formatter->formatError("Failed to create database '$database'");
        } catch (\Exception $e) {
            return $this->formatter->formatError("Error creating database: " . $e->getMessage());
        }
    }

    /**
     * Get all databases
     */
    private function getAllDatabases(): array
    {
        try {
            $connection = $this->getConnection();
            $stmt = $connection->query("SHOW DATABASES");
            return $stmt !== false ? $stmt->fetchAll(\PDO::FETCH_COLUMN) : [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get database connection
     */
    private function getConnection(): MySQLConnection
    {
        $host = $this->config['services']['MySQL']['host'] ?? 'localhost';
        $port = $this->config['services']['MySQL']['port'] ?? 3306;
        $user = $this->config['services']['MySQL']['user'] ?? 'root';
        $pass = $this->config['services']['MySQL']['password'] ?? '';

        return new MySQLConnection($host, $port, $user, $pass, 'mysql');
    }

    /**
     * Format file size for display
     */
    private function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Get backup information and statistics
     */
    public function getBackupInfo(): string
    {
        $backupFiles = glob($this->backupDir . DIRECTORY_SEPARATOR . '*.sql');
        $totalSize = 0;
        $newestFile = '';
        $newestTime = 0;

        foreach ($backupFiles as $file) {
            $fileSize = filesize($file);
            $fileTime = filemtime($file);
            $totalSize += $fileSize;
            
            if ($fileTime > $newestTime) {
                $newestTime = $fileTime;
                $newestFile = basename($file);
            }
        }

        $output = $this->formatter->formatInfo("=== MySQL Backup Information ===") . PHP_EOL . PHP_EOL;
        $output .= $this->formatter->formatSuccess("▲ Statistics:") . PHP_EOL;
        $output .= "  " . $this->formatter->formatInfo("• Total Backups: ") . $this->colors['CYAN'] . count($backupFiles) . $this->colors['RESET'] . PHP_EOL;
        $output .= "  " . $this->formatter->formatInfo("• Total Size: ") . $this->colors['GREEN'] . $this->formatFileSize($totalSize) . $this->colors['RESET'] . PHP_EOL;
        $output .= "  " . $this->formatter->formatInfo("• Newest Backup: ") . $this->colors['YELLOW'] . ($newestFile ?: 'None') . $this->colors['RESET'] . PHP_EOL;
        $output .= "  " . $this->formatter->formatInfo("• Backup Location: ") . $this->colors['WHITE'] . $this->backupDir . $this->colors['RESET'] . PHP_EOL;

        $output .= PHP_EOL . $this->formatter->formatSuccess("▲ Tools:") . PHP_EOL;
        $output .= "  " . $this->formatter->formatInfo("• mysqldump: ") . $this->colors['WHITE'] . (file_exists($this->getMySQLDumpPath()) ? 'Available ✅' : 'Not found ❌') . $this->colors['RESET'] . PHP_EOL;
        $output .= "  " . $this->formatter->formatInfo("• mysql: ") . $this->colors['WHITE'] . (file_exists($this->getMySQLPath()) ? 'Available ✅' : 'Not found ❌') . $this->colors['RESET'] . PHP_EOL;

        return $output;
    }
}