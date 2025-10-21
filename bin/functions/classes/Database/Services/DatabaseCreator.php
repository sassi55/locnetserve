<?php
//-------------------------------------------------------------
// DatabaseCreator.php - Database Creation Service for LocNetServe
//-------------------------------------------------------------
// Handles MySQL database creation with validation and fallback methods.
// Provides secure database creation functionality.
//
// Author : Sassi Souid
// Project: LocNetServe
// Version: 1.0.0
//-------------------------------------------------------------

namespace Database\Services;

use Database\Connection\ConnectionManager;
use Database\Formatters\DatabaseFormatter;

class DatabaseCreator
{
    private array $config;
    private ConnectionManager $connectionManager;
    private DatabaseFormatter $formatter;
    private DatabaseLister $databaseLister;

    /**
     * DatabaseCreator constructor.
     *
     * @param array $config Application configuration
     * @param DatabaseFormatter $formatter Output formatter
     * @param DatabaseLister $databaseLister Database lister for validation
     */
    public function __construct(array $config, DatabaseFormatter $formatter, DatabaseLister $databaseLister)
    {
        $this->config = $config;
        $this->connectionManager = ConnectionManager::getInstance($this->getConnectionConfig());
        $this->formatter = $formatter;
        $this->databaseLister = $databaseLister;
    }

    /**
     * Create a new MySQL database.
     *
     * @param string $dbName Database name to create
     * @return string Success or error message
     */
    public function createDatabase(string $dbName): string
    {
        // Validate database name
        $validationResult = $this->validateDatabaseName($dbName);
        if ($validationResult !== true) {
            return $validationResult;
        }

        // Check if database already exists
        if ($this->databaseLister->databaseExists($dbName)) {
            return $this->formatter->formatWarning("Database '$dbName' already exists.");
        }

        try {
            $connection = $this->connectionManager->getConnection();
            $result = $connection->query("CREATE DATABASE `$dbName`");
            
            if ($result === false) {
                return $this->formatter->formatError("Failed to create database '$dbName'");
            }

            return $this->formatter->formatSuccess("Database '$dbName' created successfully.");

        } catch (\Exception $e) {
            // Fallback to executable method
            return $this->createDatabaseExec($dbName);
        }
    }

    /**
     * Validate database name
     *
     * @param string $dbName Database name to validate
     * @return string|true Error message or true if valid
     */
    private function validateDatabaseName(string $dbName)
    {
        $dbName = trim($dbName);
        
        if (empty($dbName)) {
            return $this->formatter->formatError("Database name cannot be empty.");
        }

        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $dbName)) {
            return $this->formatter->formatError("Invalid database name. Use only letters, numbers, underscore (_) or dash (-).");
        }

        // Prevent creating reserved system databases
        $reserved = ['mysql', 'information_schema', 'performance_schema', 'sys'];
        if (in_array(strtolower($dbName), $reserved)) {
            return $this->formatter->formatError("Cannot create reserved database name '$dbName'.");
        }

        // Additional MySQL restrictions
        if (strlen($dbName) > 64) {
            return $this->formatter->formatError("Database name cannot exceed 64 characters.");
        }

        if (preg_match('/[\\\\\/\.]/', $dbName)) {
            return $this->formatter->formatError("Database name cannot contain backslashes, slashes, or dots.");
        }

        return true;
    }

    /**
     * Fallback method for database creation using executable
     */
    private function createDatabaseExec(string $dbName): string
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

            $query = "CREATE DATABASE `$dbName`;";
            $cmd = "\"$mysql_exe\" -u$user " . ($pass !== '' ? "-p$pass " : "") . "-h$host -e \"$query\" 2>&1";

            exec($cmd, $output, $return_var);

            if ($return_var !== 0) {
                return $this->formatter->formatError("Error creating database '$dbName': " . implode("\n", $output));
            }

            return $this->formatter->formatSuccess("Database '$dbName' created successfully.");

        } catch (\Exception $e) {
            return $this->formatter->formatError("Exception: " . $e->getMessage());
        }
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