<?php
//-------------------------------------------------------------
// DatabaseDropper.php - Database Deletion Service for LocNetServe
//-------------------------------------------------------------
// Handles MySQL database deletion with safety checks and validation.
// Provides secure database removal functionality.
//
// Author : Sassi Souid
// Project: LocNetServe
// Version: 1.0.0
//-------------------------------------------------------------

namespace Database\Services;

use Database\Connection\ConnectionManager;
use Database\Formatters\DatabaseFormatter;

class DatabaseDropper
{
    private array $config;
    private ConnectionManager $connectionManager;
    private DatabaseFormatter $formatter;
    private DatabaseLister $databaseLister;

    /**
     * DatabaseDropper constructor.
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
     * Drop a MySQL database.
     *
     * @param string $dbName Database name to drop
     * @return string Success or error message
     */
    public function dropDatabase(string $dbName): string
    {
        $dbName = trim($dbName);
        if (empty($dbName)) {
            return $this->formatter->formatError("Database name cannot be empty.");
        }

        // Prevent dropping system databases
        $reserved = ['mysql', 'information_schema', 'performance_schema', 'sys'];
        if (in_array(strtolower($dbName), $reserved)) {
            return $this->formatter->formatError("Cannot drop system database '$dbName'.");
        }

        // Check if database exists
        if (!$this->databaseLister->databaseExists($dbName)) {
            return $this->formatter->formatWarning("Database '$dbName' does not exist.");
        }

        try {
            $connection = $this->connectionManager->getConnection();
            $result = $connection->query("DROP DATABASE `$dbName`");
            
            if ($result === false) {
                return $this->formatter->formatError("Failed to drop database '$dbName'");
            }

            return $this->formatter->formatSuccess("Database '$dbName' dropped successfully.");

        } catch (\Exception $e) {
            return $this->formatter->formatError("Error dropping database: " . $e->getMessage());
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