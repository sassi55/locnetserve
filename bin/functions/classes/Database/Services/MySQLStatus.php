<?php
//-------------------------------------------------------------
// MySQLStatus.php - MySQL Status Service for LocNetServe
//-------------------------------------------------------------
// Handles MySQL status monitoring, PID detection, and service state.
// Provides detailed status information about MySQL service.
//
// Author : Sassi Souid
// Project: LocNetServe
// Version: 1.0.0
//-------------------------------------------------------------

namespace Database\Services;

use Database\Connection\MySQLConnection;
use Database\Formatters\DatabaseFormatter;

class MySQLStatus
{
    private array $config;
    private DatabaseFormatter $formatter;
    private array $colors;
    private string $pid_file;
    private array $services;

    /**
     * MySQLStatus constructor.
     *
     * @param array $config Application configuration
     * @param DatabaseFormatter $formatter Output formatter
     */
    public function __construct(array $config, DatabaseFormatter $formatter)
    {
        $this->config = $config;
        $this->formatter = $formatter;
        $this->colors = $this->getDefaultColors();
        $this->services = $GLOBALS['services'] ?? [];
        $this->pid_file = $GLOBALS['paths']['config'] . 'mysql.pid';
    }

    /**
     * Get default colors array
     */
    private function getDefaultColors(): array
    {
        if (method_exists($this->formatter, 'getColors')) {
            return $this->formatter->getColors();
        }
        
        return [
            'RESET' => "\033[0m",
            'RED' => "\033[31m",
            'GREEN' => "\033[32m",
            'YELLOW' => "\033[33m",
            'BLUE' => "\033[34m",
            'MAGENTA' => "\033[35m",
            'CYAN' => "\033[36m",
            'WHITE' => "\033[37m"
        ];
    }

    /**
     * Get MySQL service status with detailed information.
     *
     * @return string Formatted status message
     */
    public function getStatus(): string
    {
        $pid = $this->getPid();
        
        if ($pid) {
            $status = $this->formatter->formatSuccess("MySQL is running (PID: $pid)");
            
            // Add additional details if available
            $port = $this->getPort();
            $version = $this->getVersion();
            $connectionInfo = $this->getConnectionInfo();
            
            $details = [];
            if ($port !== "Not detected") {
                $details[] = "Port: $port";
            }
            if ($version !== "Version not detected") {
                $details[] = "Version: $version";
            }
            if (!empty($connectionInfo) && $connectionInfo['connected']) {
                $details[] = "Threads: " . $connectionInfo['threads_connected'];
            }
            
            if (!empty($details)) {
                $status .= PHP_EOL . $this->formatter->formatInfo("Details: " . implode(", ", $details));
            }
            
            // Add connection status if available
            if (!empty($connectionInfo) && $connectionInfo['connected']) {
                $status .= PHP_EOL . $this->formatter->formatSuccess("Database connection: OK");
            } elseif (!empty($connectionInfo)) {
                $status .= PHP_EOL . $this->formatter->formatWarning("Database connection: Failed");
            }
            $status .= PHP_EOL . $this->getDetailedStatus();
            return $status;
        }
        
        return $this->formatter->formatError("MySQL is not running");
    }

    /**
     * Get detailed status information
     *
     * @return string Detailed status report
     */
    public function getDetailedStatus(): string
    {
        $output = $this->formatter->formatInfo("=== MySQL Detailed Status ===") . PHP_EOL . PHP_EOL;
        
        // Service Status
        $pid = $this->getPid();
        $output .= $this->formatter->formatSuccess("▲ Service Status:") . PHP_EOL;
        if ($pid) {
            $output .= "  " . $this->formatter->formatInfo("• Status: ") . $this->colors['GREEN'] . "RUNNING" . $this->colors['RESET'] . PHP_EOL;
            $output .= "  " . $this->formatter->formatInfo("• Process ID: ") . $this->colors['CYAN'] . $pid . $this->colors['RESET'] . PHP_EOL;
        } else {
            $output .= "  " . $this->formatter->formatInfo("• Status: ") . $this->colors['RED'] . "STOPPED" . $this->colors['RESET'] . PHP_EOL;
            return $output; // Return early if service is stopped
        }

        $output .= PHP_EOL;

        // Network Information
        $port = $this->getPort();
        $ip = $this->getIP();
        $output .= $this->formatter->formatSuccess("▲ Network Information:") . PHP_EOL;
        $output .= "  " . $this->formatter->formatInfo("• Listening Port: ") . $this->colors['GREEN'] . $port . $this->colors['RESET'] . PHP_EOL;
        $output .= "  " . $this->formatter->formatInfo("• IP Address: ") . $this->colors['CYAN'] . $ip . $this->colors['RESET'] . PHP_EOL;
        $output .= "  " . $this->formatter->formatInfo("• Connection: ") . $this->colors['WHITE'] . "$ip:$port" . $this->colors['RESET'] . PHP_EOL;

        $output .= PHP_EOL;

        // Version Information
        $version = $this->getVersion();
        $output .= $this->formatter->formatSuccess("▲ Version Information:") . PHP_EOL;
        $output .= "  " . $this->formatter->formatInfo("• MySQL Version: ") . $this->colors['YELLOW'] . $version . $this->colors['RESET'] . PHP_EOL;

        $output .= PHP_EOL;

        // Database Connection
        $connectionInfo = $this->getConnectionInfo();
        $output .= $this->formatter->formatSuccess("▲ Database Connection:") . PHP_EOL;
        if (!empty($connectionInfo) && $connectionInfo['connected']) {
            $output .= "  " . $this->formatter->formatInfo("• Status: ") . $this->colors['GREEN'] . "CONNECTED" . $this->colors['RESET'] . PHP_EOL;
            $output .= "  " . $this->formatter->formatInfo("• Active Threads: ") . $this->colors['CYAN'] . $connectionInfo['threads_connected'] . $this->colors['RESET'] . PHP_EOL;
            $output .= "  " . $this->formatter->formatInfo("• Server Version: ") . $this->colors['YELLOW'] . $connectionInfo['version'] . $this->colors['RESET'] . PHP_EOL;
            $output .= "  " . $this->formatter->formatInfo("• Character Set: ") . $this->colors['WHITE'] . $connectionInfo['charset'] . $this->colors['RESET'] . PHP_EOL;
        } else {
            $output .= "  " . $this->formatter->formatInfo("• Status: ") . $this->colors['RED'] . "DISCONNECTED" . $this->colors['RESET'] . PHP_EOL;
            if (!empty($connectionInfo['error'])) {
                $output .= "  " . $this->formatter->formatInfo("• Error: ") . $this->colors['RED'] . $connectionInfo['error'] . $this->colors['RESET'] . PHP_EOL;
            }
        }

        $output .= PHP_EOL;

        // Process Information
        $allPids = $this->getAllPids();
        $output .= $this->formatter->formatSuccess("▲ Process Information:") . PHP_EOL;
        $output .= "  " . $this->formatter->formatInfo("• Total Processes: ") . $this->colors['CYAN'] . count($allPids) . $this->colors['RESET'] . PHP_EOL;
        if (!empty($allPids)) {
            $output .= "  " . $this->formatter->formatInfo("• Process IDs: ") . $this->colors['WHITE'] . implode(', ', $allPids) . $this->colors['RESET'] . PHP_EOL;
        }

        return $output;
    }

    /**
     * Get MySQL PID from file or process list.
     *
     * @return int|false Process ID or false if not running
     */
    public function getPid(): int|false
    {
        // 1. Check PID file first
        if (file_exists($this->pid_file)) {
            $pid_content = trim(file_get_contents($this->pid_file));
            $pid_content = $this->removeBom($pid_content);
            if (is_numeric($pid_content)) {
                return (int)$pid_content;
            }
        }

        // 2. Check process list
        $process_name = $this->services['MySQL']['process'] ?? 'mysqld.exe';
        
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            exec("tasklist /fi \"imagename eq $process_name\" /fo csv", $output);
            if (count($output) > 1) {
                $parts = str_getcsv($output[1]);
                if (isset($parts[1]) && is_numeric($parts[1])) {
                    return (int)$parts[1];
                }
            }
        } else {
            exec("ps -e -o pid,comm | grep mysqld", $output);
            foreach ($output as $line) {
                if (preg_match('/^\s*(\d+)\s+mysqld/', $line, $matches)) {
                    return (int)$matches[1];
                }
            }
        }

        return false;
    }

    /**
     * Get all MySQL PIDs from process list.
     *
     * @return array Array of process IDs
     */
    public function getAllPids(): array
    {
        $pids = [];
        $process_name = $this->services['MySQL']['process'] ?? 'mysqld.exe';

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            exec("tasklist /FI \"IMAGENAME eq $process_name\" /FO CSV /NH", $output);
            foreach ($output as $line) {
                $line = trim($line, "\" \t\n\r\0\x0B");
                if (!empty($line)) {
                    $cols = str_getcsv($line);
                    if (isset($cols[1]) && is_numeric($cols[1])) {
                        $pids[] = (int)$cols[1];
                    }
                }
            }
        } else {
            exec("ps -e -o pid,comm | grep mysqld", $output);
            foreach ($output as $line) {
                if (preg_match('/^\s*(\d+)\s+mysqld/', $line, $matches)) {
                    $pids[] = (int)$matches[1];
                }
            }
        }

        return $pids;
    }

    /**
     * Get MySQL server port.
     *
     * @return string Detected port or error message
     */
    public function getPort(): string
    {
        $pids_to_check = $this->getAllPids();
        
        foreach ($pids_to_check as $pid) {
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                exec("netstat -ano | findstr $pid", $output, $return_var);
                if ($return_var === 0 && !empty($output)) {
                    foreach ($output as $line) {
                        if (preg_match('/:(\d+)\s+.*\s+' . $pid . '$/', $line, $matches)) {
                            return $matches[1];
                        }
                    }
                }
            } else {
                exec("netstat -tlnp | grep $pid", $output, $return_var);
                if ($return_var === 0 && !empty($output)) {
                    foreach ($output as $line) {
                        if (preg_match('/:(\d+)\s+.*\s+' . $pid . '\\/mysqld/', $line, $matches)) {
                            return $matches[1];
                        }
                    }
                }
            }
            $output = [];
        }

        return "Not detected";
    }

    /**
     * Get MySQL server IP address.
     *
     * @return string IP address or error message
     */
    public function getIP(): string
    {
        $pids = $this->getAllPids();
        
        foreach ($pids as $pid) {
            $ip = $this->findIPByPid($pid);
            if ($ip !== null) {
                return $ip;
            }
        }

        return "Unknown";
    }

    /**
     * Find IP address by process ID.
     *
     * @param int $pid Process ID to check
     * @return string|null IP address or null if not found
     */
    private function findIPByPid(int $pid): ?string
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            exec("netstat -ano | findstr $pid", $output, $return_var);
        } else {
            exec("netstat -tlnp | grep $pid", $output, $return_var);
        }
        
        if ($return_var === 0 && !empty($output)) {
            foreach ($output as $line) {
                if (preg_match('/\s+([\d\.]+):\d+\s+.*\s+' . $pid . '$/', $line, $matches)) {
                    return $matches[1];
                }
            }
        }
        
        return null;
    }

    /**
     * Get MySQL version from executable.
     *
     * @return string Version information
     */
    public function getVersion(): string
    {
        $mysqld = $this->services['MySQL']['exe'] ?? '';
        
        if (!file_exists($mysqld)) {
            return "MySQL executable not found";
        }

        $output = [];
        exec('"' . $mysqld . '" --version', $output);
        return !empty($output) ? trim(str_replace($mysqld, '', $output[0])) : "Version not detected";
    }

    /**
     * Get database connection information
     *
     * @return array Connection information
     */
    public function getConnectionInfo(): array
    {
        try {
            $host = $this->config['services']['MySQL']['host'] ?? 'localhost';
            $port = $this->config['services']['MySQL']['port'] ?? 3306;
            $user = $this->config['services']['MySQL']['user'] ?? 'root';
            $pass = $this->config['services']['MySQL']['password'] ?? '';

            $connection = new MySQLConnection($host, $port, $user, $pass);
            $pdo = $connection->getPDO();

            $version = $pdo->query("SELECT VERSION()")->fetchColumn();
            $threads = $pdo->query("SHOW STATUS LIKE 'Threads_connected'")->fetchColumn(1);

            return [
                'connected' => true,
                'host' => $host,
                'port' => $port,
                'database' => 'mysql',
                'version' => $version,
                'threads_connected' => $threads,
                'charset' => 'utf8mb4'
            ];
        } catch (\Exception $e) {
            return [
                'connected' => false,
                'error' => $e->getMessage(),
                'host' => $this->config['services']['MySQL']['host'] ?? 'localhost',
                'port' => $this->config['services']['MySQL']['port'] ?? 3306
            ];
        }
    }

    /**
     * Check if MySQL service is running
     *
     * @return bool True if service is running
     */
    public function isRunning(): bool
    {
        return $this->getPid() !== false;
    }

    /**
     * Check if database connection is working
     *
     * @return bool True if connection is successful
     */
    public function isConnected(): bool
    {
        $info = $this->getConnectionInfo();
        return $info['connected'] ?? false;
    }

    /**
     * Get service health status
     *
     * @return string Health status message
     */
    public function getHealthStatus(): string
    {
        $isRunning = $this->isRunning();
        $isConnected = $this->isConnected();

        if (!$isRunning) {
            return $this->formatter->formatError("Service is not running");
        }

        if (!$isConnected) {
            return $this->formatter->formatWarning("Service is running but database connection failed");
        }

        return $this->formatter->formatSuccess("Service is healthy and responsive");
    }

    /**
     * Remove BOM from string.
     *
     * @param string $text Input text
     * @return string Clean text without BOM
     */
    private function removeBom(string $text): string
    {
        $bom = pack('H*', 'EFBBBF');
        return preg_replace("/^$bom/", '', $text);
    }
}