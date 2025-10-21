<?php
//-------------------------------------------------------------
// DatabaseLister.php - Database Listing Service for LocNetServe
//-------------------------------------------------------------
// Handles listing of MySQL databases with multiple fallback methods.
// Provides consistent database listing functionality.
//
// Author : Sassi Souid
// Project: LocNetServe
// Version: 1.0.0
//-------------------------------------------------------------

namespace Database\Services;

use Database\Connection\ConnectionManager;
use Database\Formatters\DatabaseFormatter;

class DatabaseLister
{
    private array $config;
    private ConnectionManager $connectionManager;
    private DatabaseFormatter $formatter;

    /**
     * DatabaseLister constructor.
     *
     * @param array $config Application configuration
     * @param DatabaseFormatter $formatter Output formatter
     */
    public function __construct(array $config, DatabaseFormatter $formatter)
    {
        $this->config = $config;
        $this->connectionManager = ConnectionManager::getInstance($this->getConnectionConfig());
        $this->formatter = $formatter;
    }

    /**
     * List all MySQL databases with formatted output.
     *
     * @return string Formatted database list or error message
     */
    public function listDatabases(): string
    {
        try {
            $connection = $this->connectionManager->getConnection();
            $result = $connection->query("SHOW DATABASES");
            
            if ($result === false) {
                return $this->formatter->formatError("Failed to list databases");
            }

            $databases = $result->fetchAll(\PDO::FETCH_COLUMN);
            return $this->formatter->formatDatabaseList($databases);

        } catch (\Exception $e) {
            // Fallback to executable method if PDO fails
            return $this->listDatabasesExec();
        }
    }

    /**
     * Fallback method using MySQL executable
     */
    private function listDatabasesExec(): string
    {
        try {
            $mysql_bin = rtrim($this->config['paths']['mysql'] ?? '', DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'bin';
            $mysql_exe = $mysql_bin . DIRECTORY_SEPARATOR . 'mysql' . (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? '.exe' : '');

            if (!file_exists($mysql_exe)) {
                return $this->formatter->formatError("MySQL client not found at $mysql_exe");
            }

            $user = 'root';
            $pass = $this->config['services']['MySQL']['password'] ?? '';
            $host = 'localhost';

            $query = "SHOW DATABASES;";
            $cmd = "\"$mysql_exe\" -u$user " . ($pass !== '' ? "-p$pass " : "") . "-h$host -e \"$query\" 2>&1";
            exec($cmd, $output, $return_var);

            if ($return_var !== 0 || empty($output)) {
                return $this->formatter->formatError("Error executing MySQL command:\n" . implode("\n", $output));
            }

            // Process and format output
            $databases = [];
            foreach ($output as $line) {
                $dbName = trim($line);
                if (!empty($dbName) && $dbName !== 'Database') {
                    $databases[] = $dbName;
                }
            }

            return $this->formatter->formatDatabaseList($databases);

        } catch (\Exception $e) {
            return $this->formatter->formatError("Exception: " . $e->getMessage());
        }
    }

    /**
     * Get raw database list as array
     *
     * @return array Array of database names or empty array on error
     */
    public function getDatabaseArray(): array
    {
        try {
            $connection = $this->connectionManager->getConnection();
            $result = $connection->query("SHOW DATABASES");
            
            if ($result === false) {
                return [];
            }

            return $result->fetchAll(\PDO::FETCH_COLUMN);

        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Check if a database exists
     *
     * @param string $dbName Database name to check
     * @return bool True if database exists
     */
    public function databaseExists(string $dbName): bool
    {
        $databases = $this->getDatabaseArray();
        return in_array($dbName, $databases);
    }

    /**
     * Get connection configuration
     */
    private function getConnectionConfig(): array
    {
        return [
            'default' => [
                'host' => $this->config['services']['MySQL']['host'] ?? 'localhost',
                'port' => $this->config['services']['MySQL']['port'] ?? 3306,
                'user' => $this->config['services']['MySQL']['user'] ?? 'root',
                'pass' => $this->config['services']['MySQL']['password'] ?? '',
                'dbname' => 'mysql',
                'charset' => 'utf8mb4',
                'timeout' => 5
            ]
        ];
    }
}