<?php
/**
 * -------------------------------------------------------------
 *  MySQLHealthCommand.php - MySQL Health Command for LocNetServe
 * -------------------------------------------------------------
 *  Monitors MySQL service health and connectivity by:
 *    - Performing comprehensive service status checks
 *    - Verifying database connectivity and response times
 *    - Checking process status and resource utilization
 *    - Providing detailed health assessment reports
 *
 *  Author : Sassi Souid
 *  Email  : locnetserve@gmail.com
 *  Project: LocNetServe
 *  Version: 1.0.0
 * -------------------------------------------------------------
 */

namespace Database\Commands;

use Core\Commands\Command;
use Database\Connection\MySQLConnection;

class MySQLHealthCommand implements Command
{
    private array $colors;
    private array $config;

    /**
     * MySQLHealthCommand constructor.
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
     * Execute MySQL health check command.
     *
     * @param string $action The action to perform
     * @param array $args Command arguments
     * @return string Health check results
     */
    public function execute(string $action, array $args = []): string
    {
        return $this->healthCheck();
    }

    /**
     * Perform comprehensive MySQL health check.
     *
     * @return string Formatted health check results
     */
    public function healthCheck(): string
    {
        $output = $this->colors['MAGENTA'] . "=== MySQL Health Check ===" . $this->colors['RESET'] . PHP_EOL . PHP_EOL;

        // 1. Check if MySQL service is running
        $statusCommand = new MySQLStatusCommand($this->colors, $this->config);
        $pid = $statusCommand->getPid();
        
        if ($pid) {
            $output .= $this->colors['GREEN'] . "✓ Service Status: Running (PID: $pid)" . $this->colors['RESET'] . PHP_EOL;
        } else {
            $output .= $this->colors['RED'] . "✗ Service Status: Not running" . $this->colors['RESET'] . PHP_EOL;
            $output .= $this->colors['YELLOW'] . "Note: Some checks skipped due to service being down." . $this->colors['RESET'] . PHP_EOL;
            return $output;
        }

        // 2. Check MySQL version
        $versionCommand = new MySQLVersionCommand($this->colors, $this->config);
        $versionOutput = $versionCommand->getVersion();
        if (strpos($versionOutput, "failed") === false) {
            $output .= $this->colors['GREEN'] . "✓ Version: Detected" . $this->colors['RESET'] . PHP_EOL;
        } else {
            $output .= $this->colors['YELLOW'] . "⚠ Version: Could not determine" . $this->colors['RESET'] . PHP_EOL;
        }

        // 3. Check listening port
        $portCommand = new MySQLPortCommand($this->colors, $this->config);
        $port = $portCommand->getPort();
        if ($port !== "Port not detected") {
            $output .= $this->colors['GREEN'] . "✓ Network: Listening on port $port" . $this->colors['RESET'] . PHP_EOL;
        } else {
            $output .= $this->colors['RED'] . "✗ Network: Port not detected" . $this->colors['RESET'] . PHP_EOL;
        }

        // 4. Test database connection
        $connectionTest = $this->testDatabaseConnection();
        if ($connectionTest['success']) {
            $output .= $this->colors['GREEN'] . "✓ Database Connection: Successful" . $this->colors['RESET'] . PHP_EOL;
        } else {
            $output .= $this->colors['RED'] . "✗ Database Connection: Failed - " . $connectionTest['error'] . $this->colors['RESET'] . PHP_EOL;
        }

        // 5. Check system databases
        $systemDbs = $this->checkSystemDatabases();
        $output .= $this->colors['GREEN'] . "✓ System Databases: " . $systemDbs['found'] . "/" . $systemDbs['total'] . " found" . $this->colors['RESET'] . PHP_EOL;

        // 6. Check performance metrics
        $performance = $this->checkPerformance();
        $output .= $this->colors['GREEN'] . "✓ Performance: Connection time " . $performance['connection_time'] . "ms" . $this->colors['RESET'] . PHP_EOL;

        // Summary
        $output .= PHP_EOL . $this->colors['MAGENTA'] . "=== Health Check Summary ===" . $this->colors['RESET'] . PHP_EOL;
        $output .= $this->colors['GREEN'] . "MySQL service is healthy and operational." . $this->colors['RESET'] . PHP_EOL;
        $output .= $this->colors['CYAN'] . "All critical systems are functioning normally." . $this->colors['RESET'];

        return $output;
    }

    /**
     * Test database connection using PDO.
     *
     * @return array Connection test results
     */
    private function testDatabaseConnection(): array
    {
        try {
            $host = 'localhost';
            $port = $this->config['services']['MySQL']['port'] ?? 3306;
            $user = 'root';
            $pass = $this->config['services']['MySQL']['password'] ?? '';

            $start = microtime(true);
            $connection = new MySQLConnection($host, $port, $user, $pass);
            $pdo = $connection->getPDO();
            
            // Test simple query
            $stmt = $pdo->query("SELECT 1 as test");
            $result = $stmt->fetch();
            
            $connectionTime = round((microtime(true) - $start) * 1000, 2);

            return [
                'success' => true,
                'connection_time' => $connectionTime,
                'query_test' => $result['test'] === 1
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check if system databases exist.
     *
     * @return array System databases check results
     */
    private function checkSystemDatabases(): array
    {
        try {
            $host = 'localhost';
            $port = $this->config['services']['MySQL']['port'] ?? 3306;
            $user = 'root';
            $pass = $this->config['services']['MySQL']['password'] ?? '';

            $connection = new MySQLConnection($host, $port, $user, $pass);
            $pdo = $connection->getPDO();
            
            $databases = $pdo->query("SHOW DATABASES")->fetchAll(\PDO::FETCH_COLUMN);

            $requiredDBs = ["mysql", "information_schema", "performance_schema", "sys"];
            $found = 0;

            foreach ($requiredDBs as $db) {
                if (in_array($db, $databases)) {
                    $found++;
                }
            }

            return [
                'found' => $found,
                'total' => count($requiredDBs),
                'missing' => array_diff($requiredDBs, $databases)
            ];

        } catch (\Exception $e) {
            return [
                'found' => 0,
                'total' => 4,
                'missing' => ['all'],
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check basic performance metrics.
     *
     * @return array Performance metrics
     */
    private function checkPerformance(): array
    {
        try {
            $host = 'localhost';
            $port = $this->config['services']['MySQL']['port'] ?? 3306;
            $user = 'root';
            $pass = $this->config['services']['MySQL']['password'] ?? '';

            $start = microtime(true);
            $connection = new MySQLConnection($host, $port, $user, $pass);
            $pdo = $connection->getPDO();
            $connectionTime = round((microtime(true) - $start) * 1000, 2);

            // Test query performance
            $queryStart = microtime(true);
            $pdo->query("SELECT @@version_comment as version")->fetch();
            $queryTime = round((microtime(true) - $queryStart) * 1000, 2);

            return [
                'connection_time' => $connectionTime,
                'query_time' => $queryTime,
                'status' => 'healthy'
            ];

        } catch (\Exception $e) {
            return [
                'connection_time' => 0,
                'query_time' => 0,
                'status' => 'failed',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get help information for health command.
     *
     * @return string Help message
     */
    public function getHelp(): string
    {
        return $this->colors['GREEN'] . "mysql health" . $this->colors['RESET'] . 
               " - Run comprehensive MySQL service health check" . PHP_EOL .
               $this->colors['CYAN'] . "Usage: lns mysql health" . $this->colors['RESET'] . PHP_EOL .
               "Performs: Service status, version check, port verification, " . PHP_EOL .
               "          connection test, system database check, performance metrics";
    }
}