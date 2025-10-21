<?php
/**
 * -------------------------------------------------------------
 *  ConnectionManager.php - MySQL Connection Manager for LocNetServe
 * -------------------------------------------------------------
 *  Manages MySQL database connections and pooling by:
 *    - Providing connection pooling for efficient resource usage
 *    - Handling configuration-based connection settings
 *    - Supporting connection reuse and lifecycle management
 *    - Centralizing database connectivity across the application
 *
 *  Author : Sassi Souid
 *  Email  : locnetserve@gmail.com
 *  Project: LocNetServe
 *  Version: 1.0.0
 * -------------------------------------------------------------
 */
namespace Database\Connection;

use Database\Connection\MySQLConnection;

class ConnectionManager
{
    private static ?ConnectionManager $instance = null;
    private array $connections = [];
    private array $config;
    private string $defaultConnection = 'default';

    /**
     * Constructor (private for Singleton)
     */
    private function __construct(array $config = [])
    {
        $this->config = $this->initializeConfig($config);
    }

    /**
     * Singleton access
     */
    public static function getInstance(array $config = []): ConnectionManager
    {
        if (self::$instance === null) {
            self::$instance = new self($config);
        }

        if (!empty($config)) {
            self::$instance->updateConfig($config);
        }

        return self::$instance;
    }

    /**
     * Initialize configuration with default values
     */
    private function initializeConfig(array $config): array
    {
        $defaults = [
            'default' => [
                'host' => 'localhost',
                'port' => 3306,
                'user' => 'root',
                'pass' => '',
                'dbname' => 'mysql',
                'charset' => 'utf8mb4',
                'timeout' => 5,
                'persistent' => false
            ]
        ];

        return array_replace_recursive($defaults, $config);
    }

    /**
     * Update existing configuration
     */
    public function updateConfig(array $config): void
    {
        $this->config = array_replace_recursive($this->config, $config);

        // Refresh connections if critical parameters changed
        foreach ($this->connections as $name => $connection) {
            if (isset($config[$name]) && $this->isConfigChanged($name, $config[$name])) {
                $connection->close();
                unset($this->connections[$name]);
            }
        }
    }

    /**
     * Detect significant configuration changes
     */
    private function isConfigChanged(string $connectionName, array $newConfig): bool
    {
        if (!isset($this->config[$connectionName])) {
            return true;
        }

        $oldConfig = $this->config[$connectionName];
        $criticalKeys = ['host', 'port', 'user', 'pass', 'dbname'];

        foreach ($criticalKeys as $key) {
            if (($oldConfig[$key] ?? '') !== ($newConfig[$key] ?? '')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Retrieve or create a connection by name
     */
    public function getConnection(string $connectionName = null): MySQLConnection
    {
        $name = $connectionName ?? $this->defaultConnection;

        if (!isset($this->connections[$name])) {
            $this->connections[$name] = $this->createConnection($name);
        }

        return $this->connections[$name];
    }

    /**
     * Create a new MySQLConnection instance
     */
    private function createConnection(string $connectionName): MySQLConnection
    {
        if (!isset($this->config[$connectionName])) {
            throw new \InvalidArgumentException("MySQL connection configuration not found: '{$connectionName}'");
        }

        $cfg = $this->config[$connectionName];

        return new MySQLConnection(
            $cfg['host'] ?? 'localhost',
            $cfg['port'] ?? 3306,
            $cfg['user'] ?? 'root',
            $cfg['pass'] ?? '',
            $cfg['dbname'] ?? 'mysql',
            $cfg['charset'] ?? 'utf8mb4',
            $cfg['timeout'] ?? 5
        );
    }

    /**
     * Set default connection name
     */
    public function setDefaultConnection(string $connectionName): void
    {
        $this->defaultConnection = $connectionName;
    }

    /**
     * Get default connection name
     */
    public function getDefaultConnection(): string
    {
        return $this->defaultConnection;
    }

    /**
     * Test a specific connection
     */
    public function testConnection(string $connectionName = null): bool
    {
        try {
            $connection = $this->getConnection($connectionName);
            return $connection->testConnection();
        } catch (\Throwable $e) {
            error_log("[ConnectionManager] Test failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Test all configured connections
     */
    public function testAllConnections(): array
    {
        $results = [];

        foreach (array_keys($this->config) as $connectionName) {
            try {
                $connection = $this->getConnection($connectionName);
                $results[$connectionName] = [
                    'success' => $connection->testConnection(),
                    'info' => $connection->getConnectionInfo()
                ];
            } catch (\Throwable $e) {
                $results[$connectionName] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * Close specific connection
     */
    public function closeConnection(string $connectionName): bool
    {
        if (isset($this->connections[$connectionName])) {
            $this->connections[$connectionName]->close();
            unset($this->connections[$connectionName]);
            return true;
        }

        return false;
    }

    /**
     * Close all active connections
     */
    public function closeAllConnections(): void
    {
        foreach ($this->connections as $connection) {
            $connection->close();
        }

        $this->connections = [];
    }

    /**
     * Return connection statistics
     */
    public function getStatistics(): array
    {
        $stats = [
            'total_configured' => count($this->config),
            'total_active' => count($this->connections),
            'default_connection' => $this->defaultConnection,
            'connections' => []
        ];

        foreach ($this->connections as $name => $connection) {
            $stats['connections'][$name] = [
                'active' => $connection->isConnected(),
                'config' => $this->config[$name] ?? null
            ];
        }

        return $stats;
    }

    /**
     * Register a new connection configuration
     */
    public function registerConnection(string $connectionName, array $config): void
    {
        $this->config[$connectionName] = array_merge([
            'host' => 'localhost',
            'port' => 3306,
            'user' => 'root',
            'pass' => '',
            'dbname' => 'mysql',
            'charset' => 'utf8mb4',
            'timeout' => 5
        ], $config);

        $this->closeConnection($connectionName);
    }

    /**
     * Remove a connection configuration
     */
    public function removeConnection(string $connectionName): bool
    {
        $this->closeConnection($connectionName);

        if (isset($this->config[$connectionName])) {
            unset($this->config[$connectionName]);

            if ($connectionName === $this->defaultConnection) {
                $this->defaultConnection = 'default';
            }

            return true;
        }

        return false;
    }

    /** Prevent cloning */
    private function __clone() {}

    /** Prevent unserialization */
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize singleton");
    }

    /** Destructor closes all active connections */
    public function __destruct()
    {
        $this->closeAllConnections();
    }
}
?>
