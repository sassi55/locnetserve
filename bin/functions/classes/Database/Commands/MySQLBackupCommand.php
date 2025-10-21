<?php
/**
 * -------------------------------------------------------------
 *  MySQLBackupCommand.php - MySQL Backup Command for LocNetServe
 * -------------------------------------------------------------
 *  Manages MySQL database backup and restore operations by:
 *    - Creating secure database dumps with mysqldump
 *    - Handling database import and restoration processes
 *    - Providing backup compression and file management
 *    - Ensuring data integrity during backup operations
 *
 *  Author : Sassi Souid
 *  Email  : locnetserve@gmail.com
 *  Project: LocNetServe
 *  Version: 1.0.0
 * -------------------------------------------------------------
 */

namespace Database\Commands;

use Core\Commands\Command;

class MySQLBackupCommand implements Command
{
    private array $colors;
    private array $config;

    /**
     * MySQLBackupCommand constructor.
     *
     * @param array $colors Color configuration for CLI output
     * @param array $config Application configuration
     */
    public function __construct(array $colors, array $config)
    {
        $this->colors = $colors;
        $this->config = $config;
    }

    /**
     * Execute MySQL backup/import command.
     *
     * @param string $action The action to perform (dump/import)
     * @param array $args Command arguments
     * @return string Result message
     */
    public function execute(string $action, array $args = []): string
    {
        switch ($action) {
            case 'dump':
                if (empty($args)) {
                    return $this->colors['RED'] . "Error: Missing database name. Use: lns mysql dump <db>" . $this->colors['RESET'];
                }
                return $this->dumpDatabase($args[0]);

            case 'import':
                if (count($args) < 2) {
                    return $this->colors['RED'] . "Error: Missing arguments. Use: lns mysql import <db> <file.sql>" . $this->colors['RESET'];
                }
                return $this->importDatabase($args[0], $args[1]);

            default:
                return $this->colors['RED'] . "Unknown backup action: $action" . $this->colors['RESET'];
        }
    }

    /**
     * Export database to SQL file.
     *
     * @param string $databaseName Database to export
     * @return string Success or error message
     */
    public function dumpDatabase(string $databaseName): string
    {
        $mysql_bin = rtrim($this->config['paths']['mysql'] ?? '', DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'bin';
        $mysqldump_exe = $mysql_bin . DIRECTORY_SEPARATOR . 'mysqldump' . (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? '.exe' : '');

        if (!file_exists($mysqldump_exe)) {
            return $this->colors['RED'] . "mysqldump not found: $mysqldump_exe" . $this->colors['RESET'];
        }

        // Connection details
        $host = $this->config['services']['MySQL']['host'] ?? '127.0.0.1';
        $port = $this->config['services']['MySQL']['port'] ?? 3306;
        $user = $this->config['services']['MySQL']['user'] ?? 'root';
        $pass = $this->config['services']['MySQL']['password'] ?? '';

        // Generate output filename with timestamp
        $timestamp = date('Y-m-d_His');
        $outputFile = BASE_DIR . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . "{$databaseName}_backup_{$timestamp}.sql";

        // Ensure backups directory exists
        $backupDir = dirname($outputFile);
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        // Build mysqldump command
        $cmd = '"' . $mysqldump_exe . '"'
             . ' -h ' . escapeshellarg($host)
             . ' -P ' . escapeshellarg($port)
             . ' -u ' . escapeshellarg($user)
             . ($pass !== '' ? ' -p' . escapeshellarg($pass) : '')
             . ' --skip-comments --complete-insert --single-transaction'
             . ' ' . escapeshellarg($databaseName)
             . ' > "' . $outputFile . '" 2>&1';

        exec($cmd, $output, $return_var);

        if ($return_var !== 0) {
            return $this->colors['RED'] . "Error dumping database '$databaseName': " . implode("\n", $output) . $this->colors['RESET'];
        }

        $fileSize = filesize($outputFile) ? round(filesize($outputFile) / 1024, 2) . ' KB' : '0 KB';

        return $this->colors['GREEN'] . "Database '$databaseName' dumped successfully to: " . $this->colors['RESET'] . PHP_EOL .
               $this->colors['CYAN'] . $outputFile . $this->colors['RESET'] . PHP_EOL .
               $this->colors['YELLOW'] . "File size: $fileSize" . $this->colors['RESET'];
    }

    /**
     * Import SQL file into database.
     *
     * @param string $databaseName Target database
     * @param string $sqlFile SQL file to import
     * @return string Success or error message
     */
    public function importDatabase(string $databaseName, string $sqlFile): string
    {
        if (!file_exists($sqlFile)) {
            return $this->colors['RED'] . "Error: SQL file not found: $sqlFile" . $this->colors['RESET'];
        }

        $mysql_bin = rtrim($this->config['paths']['mysql'] ?? '', DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'bin';
        $mysql_exe = $mysql_bin . DIRECTORY_SEPARATOR . 'mysql' . (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? '.exe' : '');

        if (!file_exists($mysql_exe)) {
            return $this->colors['RED'] . "MySQL client not found: $mysql_exe" . $this->colors['RESET'];
        }

        // Connection details
        $host = $this->config['services']['MySQL']['host'] ?? '127.0.0.1';
        $port = $this->config['services']['MySQL']['port'] ?? 3306;
        $user = $this->config['services']['MySQL']['user'] ?? 'root';
        $pass = $this->config['services']['MySQL']['password'] ?? '';

        // Build mysql import command
        $cmd = '"' . $mysql_exe . '"'
             . ' -h ' . escapeshellarg($host)
             . ' -P ' . escapeshellarg($port)
             . ' -u ' . escapeshellarg($user)
             . ($pass !== '' ? ' -p' . escapeshellarg($pass) : '')
             . ' ' . escapeshellarg($databaseName)
             . ' < "' . $sqlFile . '" 2>&1';

        exec($cmd, $output, $return_var);

        if ($return_var !== 0) {
            return $this->colors['RED'] . "Error importing into database '$databaseName': " . implode("\n", $output) . $this->colors['RESET'];
        }

        $fileSize = filesize($sqlFile) ? round(filesize($sqlFile) / 1024, 2) . ' KB' : '0 KB';

        return $this->colors['GREEN'] . "SQL file imported successfully into database '$databaseName'" . $this->colors['RESET'] . PHP_EOL .
               $this->colors['YELLOW'] . "Source file: $sqlFile ($fileSize)" . $this->colors['RESET'];
    }

    /**
     * Get help information for backup commands.
     *
     * @return string Help message
     */
    public function getHelp(): string
    {
        return $this->colors['GREEN'] . "mysql dump <db>" . $this->colors['RESET'] . 
               " - Export database to SQL backup file" . PHP_EOL .
               $this->colors['GREEN'] . "mysql import <db> <file.sql>" . $this->colors['RESET'] . 
               " - Import SQL file into database" . PHP_EOL .
               $this->colors['CYAN'] . "Usage examples:" . $this->colors['RESET'] . PHP_EOL .
               "  lns mysql dump mydatabase" . PHP_EOL .
               "  lns mysql import mydatabase backup.sql";
    }
}