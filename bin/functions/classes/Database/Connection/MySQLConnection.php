<?php
/**
 * -------------------------------------------------------------
 *  MySQLConnection.php - PDO Connection Handler for MySQL
 * -------------------------------------------------------------
 *  Manages MySQL database connections using PDO by:
 *    - Providing reusable PDO instances for database operations
 *    - Handling connection errors and configurable timeouts
 *    - Ensuring full compatibility with ConnectionManager
 *    - Supporting secure and efficient database connectivity
 *
 *  Author : Sassi Souid
 *  Email  : locnetserve@gmail.com
 *  Project: LocNetServe
 *  Version: 1.0.0
 * -------------------------------------------------------------
 */

namespace Database\Connection;

class MySQLConnection
{
    private string $host;
    private int $port;
    private string $user;
    private string $pass;
    private string $dbname;
    private string $charset;
    private int $timeout;
    private ?\PDO $pdo = null;

    /**
     * MySQLConnection constructor.
     *
     * @param string $host     MySQL host (default: localhost)
     * @param int    $port     MySQL port (default: 3306)
     * @param string $user     MySQL username (default: root)
     * @param string $pass     MySQL password (default: empty)
     * @param string $dbname   Database name (default: mysql)
     * @param string $charset  Character set (default: utf8mb4)
     * @param int    $timeout  Connection timeout in seconds (default: 5)
     */
    public function __construct(
        string $host = 'localhost',
        int $port = 3306,
        string $user = 'root',
        string $pass = '',
        string $dbname = 'mysql',
        string $charset = 'utf8mb4',
        int $timeout = 5
    ) {
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->pass = $pass;
        $this->dbname = $dbname;
        $this->charset = $charset;
        $this->timeout = $timeout;
    }

    /**
     * Get PDO instance (creates connection if needed)
     *
     * @return \PDO
     * @throws \PDOException
     */
    public function getPDO(): \PDO
    {
        if ($this->pdo === null) {
            $this->connect();
        }
        return $this->pdo;
    }

    /**
     * Establish database connection
     */
    private function connect(): void
    {
        $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->dbname};charset={$this->charset}";

        try {
            $this->pdo = new \PDO($dsn, $this->user, $this->pass, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_TIMEOUT => $this->timeout,
                \PDO::ATTR_PERSISTENT => false,
                \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->charset}"
            ]);
        } catch (\PDOException $e) {
            throw new \PDOException(
                "MySQL connection failed to {$this->host}:{$this->port}/{$this->dbname}: " . $e->getMessage(),
                (int)$e->getCode()
            );
        }
    }

    /**
     * Test connection
     */
    public function testConnection(): bool
    {
        try {
            $pdo = $this->getPDO();
            $pdo->query("SELECT 1");
            return true;
        } catch (\PDOException $e) {
            error_log("[MySQLConnection] Test failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Return connection information
     */
    public function getConnectionInfo(): array
    {
        try {
            $pdo = $this->getPDO();

            $version = $pdo->query("SELECT VERSION()")->fetchColumn();
            $threads = $pdo->query("SHOW STATUS LIKE 'Threads_connected'")->fetchColumn(1);

            return [
                'connected' => true,
                'host' => $this->host,
                'port' => $this->port,
                'database' => $this->dbname,
                'version' => $version,
                'threads_connected' => $threads,
                'charset' => $this->charset
            ];
        } catch (\PDOException $e) {
            return [
                'connected' => false,
                'error' => $e->getMessage(),
                'host' => $this->host,
                'port' => $this->port
            ];
        }
    }

    /**
     * Close PDO connection
     */
    public function close(): void
    {
        $this->pdo = null;
    }

    /**
     * Check if connection is active
     */
    public function isConnected(): bool
    {
        if ($this->pdo === null) {
            return false;
        }

        try {
            $this->pdo->query("SELECT 1");
            return true;
        } catch (\PDOException) {
            return false;
        }
    }

    /**
     * Execute a SQL query safely
     */
    public function query(string $sql, array $params = []): \PDOStatement|false
    {
        try {
            $stmt = $this->getPDO()->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (\PDOException $e) {
            error_log("[MySQLConnection] Query error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get last inserted ID
     */
    public function lastInsertId(): string
    {
        return $this->pdo?->lastInsertId() ?? '0';
    }

    /**
     * Transaction management
     */
    public function beginTransaction(): bool
    {
        return $this->pdo?->beginTransaction() ?? false;
    }

    public function commit(): bool
    {
        return $this->pdo?->commit() ?? false;
    }

    public function rollback(): bool
    {
        return $this->pdo?->rollBack() ?? false;
    }

    /**
     * Destructor closes the connection
     */
    public function __destruct()
    {
        $this->close();
    }
}
?>
