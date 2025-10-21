<?php
//-------------------------------------------------------------
// PortLister.php - MySQL Port Listing Service for LocNetServe
//-------------------------------------------------------------
// Handles MySQL port detection and network information.
// Provides detailed port and network connection information.
//
// Author : Sassi Souid
// Project: LocNetServe
// Version: 1.0.0
//-------------------------------------------------------------

namespace Database\Services;

use Database\Formatters\DatabaseFormatter;

class PortLister
{
    private array $config;
    private DatabaseFormatter $formatter;
    private array $colors;

    /**
     * PortLister constructor.
     *
     * @param array $config Application configuration
     * @param DatabaseFormatter $formatter Output formatter
     */
    public function __construct(array $config, DatabaseFormatter $formatter)
    {
        $this->config = $config;
        $this->formatter = $formatter;
        $this->colors = $this->getDefaultColors();
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
     * Get detailed MySQL port information.
     *
     * @return string Formatted port information
     */
    public function getPortInfo(): string
    {
        $port = $this->getPort();
        $ip = $this->getIP();
        $allConnections = $this->getAllConnections();
        
        $output = $this->formatter->formatInfo("=== MySQL Port Information ===") . PHP_EOL . PHP_EOL;
        
        // Informations principales
        $output .= $this->formatter->formatSuccess("▲ Main Listening Port:") . PHP_EOL;
        $output .= "  " . $this->formatter->formatInfo("• Port: ") . $this->colors['GREEN'] . $port . $this->colors['RESET'] . PHP_EOL;
        $output .= "  " . $this->formatter->formatInfo("• IP: ") . $this->colors['CYAN'] . $ip . $this->colors['RESET'] . PHP_EOL;
        $output .= "  " . $this->formatter->formatInfo("• Full Address: ") . $this->colors['WHITE'] . "$ip:$port" . $this->colors['RESET'] . PHP_EOL;
        
        $output .= PHP_EOL;

        // Détails de connexion
        $output .= $this->formatter->formatSuccess("▲ Connection Details:") . PHP_EOL;
        $output .= "  " . $this->formatter->formatInfo("• Host: ") . $this->colors['WHITE'] . "localhost" . $this->colors['RESET'] . PHP_EOL;
        $output .= "  " . $this->formatter->formatInfo("• Default Port: ") . $this->colors['WHITE'] . "3306" . $this->colors['RESET'] . PHP_EOL;
        $output .= "  " . $this->formatter->formatInfo("• Config Port: ") . $this->colors['WHITE'] . ($this->config['services']['MySQL']['port'] ?? '3306') . $this->colors['RESET'] . PHP_EOL;
        
        $output .= PHP_EOL;

        // Toutes les connexions réseau
        $output .= $this->formatter->formatSuccess("▲ Network Connections:") . PHP_EOL;
        if (empty($allConnections)) {
            $output .= "  " . $this->formatter->formatWarning("No network connections found.") . PHP_EOL;
        } else {
            foreach ($allConnections as $connection) {
                $status = $connection['status'] === 'LISTENING' ? 'LISTENING' : '🔴 ' . $connection['status'];
                $output .= "  " . $this->formatter->formatInfo("• ") . 
                          $this->colors['CYAN'] . $connection['local_address'] . $this->colors['RESET'] . " → " .
                          $this->colors['YELLOW'] . $connection['foreign_address'] . $this->colors['RESET'] . " • " .
                          $this->colors['GREEN'] . $status . $this->colors['RESET'] . PHP_EOL;
            }
        }

        return $output;
    }

    /**
     * Get MySQL server port from process network connections.
     *
     * @return string Detected port or error message
     */
    public function getPort(): string
    {
        $pids = $this->getAllPids();
        
        foreach ($pids as $pid) {
            $port = $this->findPortByPid($pid);
            if ($port !== null) {
                return $port;
            }
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
     * Find port by process ID.
     *
     * @param int $pid Process ID to check
     * @return string|null Port number or null if not found
     */
    private function findPortByPid(int $pid): ?string
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            exec("netstat -ano | findstr $pid", $output, $return_var);
        } else {
            exec("netstat -tlnp | grep $pid", $output, $return_var);
        }
        
        if ($return_var === 0 && !empty($output)) {
            foreach ($output as $line) {
                // Windows: TCP    127.0.0.1:3306    0.0.0.0:0    LISTENING    5320
                if (preg_match('/:(\d+)\s+.*\s+' . $pid . '$/', $line, $matches)) {
                    return $matches[1];
                }
                // Linux: tcp6   0   0 :::3306    :::*    LISTEN    1234/mysqld
                if (preg_match('/:(\d+)\s+.*\s+' . $pid . '\\/mysqld/', $line, $matches)) {
                    return $matches[1];
                }
            }
        }
        
        return null;
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
                // Windows: TCP    127.0.0.1:3306    0.0.0.0:0    LISTENING    5320
                if (preg_match('/\s+([\d\.]+):\d+\s+.*\s+' . $pid . '$/', $line, $matches)) {
                    return $matches[1];
                }
                // Linux: tcp6   0   0 :::3306    :::*    LISTEN    1234/mysqld
                if (preg_match('/\s+([\d\.]+|::):\d+\s+.*\s+' . $pid . '\\/mysqld/', $line, $matches)) {
                    return $matches[1] === '::' ? 'localhost' : $matches[1];
                }
            }
        }
        
        return null;
    }

    /**
     * Get all network connections for MySQL
     *
     * @return array Array of connection information
     */
    private function getAllConnections(): array
    {
        $connections = [];
        $pids = $this->getAllPids();

        foreach ($pids as $pid) {
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                exec("netstat -ano | findstr $pid", $output, $return_var);
            } else {
                exec("netstat -tlnp | grep $pid", $output, $return_var);
            }

            if ($return_var === 0 && !empty($output)) {
                foreach ($output as $line) {
                    $connection = $this->parseConnectionLine($line, $pid);
                    if ($connection) {
                        $connections[] = $connection;
                    }
                }
            }
            $output = [];
        }

        return $connections;
    }

    /**
     * Parse a netstat connection line
     *
     * @param string $line Netstat output line
     * @param int $pid Process ID
     * @return array|null Connection information or null
     */
    private function parseConnectionLine(string $line, int $pid): ?array
    {
        $line = trim($line);
        
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows format: TCP    127.0.0.1:3306    0.0.0.0:0    LISTENING    5320
            if (preg_match('/^(TCP|UDP)\s+([\d\.]+):(\d+)\s+([\d\.]+):(\d+)\s+(\w+)\s+' . $pid . '$/', $line, $matches)) {
                return [
                    'protocol' => $matches[1],
                    'local_address' => $matches[2] . ':' . $matches[3],
                    'foreign_address' => $matches[4] . ':' . $matches[5],
                    'status' => $matches[6],
                    'pid' => $pid
                ];
            }
        } else {
            // Linux format: tcp6   0   0 :::3306    :::*    LISTEN    1234/mysqld
            if (preg_match('/^(tcp|tcp6|udp)\s+\d+\s+\d+\s+([\d\.]+|::):(\d+)\s+([\d\.\*]+|:::\*)\s+(\w+)\s+' . $pid . '\\/mysqld/', $line, $matches)) {
                return [
                    'protocol' => $matches[1],
                    'local_address' => ($matches[2] === '::' ? 'localhost' : $matches[2]) . ':' . $matches[3],
                    'foreign_address' => $matches[4],
                    'status' => $matches[5],
                    'pid' => $pid
                ];
            }
        }
        
        return null;
    }

    /**
     * Get all MySQL PIDs from process list.
     *
     * @return array Array of process IDs
     */
    private function getAllPids(): array
    {
        $pids = [];

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            exec('tasklist /FI "IMAGENAME eq mysqld.exe" /FO CSV /NH', $output);
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
     * Check if MySQL is listening on expected port
     *
     * @return bool True if port matches configuration
     */
    public function isPortCorrect(): bool
    {
        $detectedPort = $this->getPort();
        $configuredPort = $this->config['services']['MySQL']['port'] ?? 3306;
        
        return $detectedPort === (string)$configuredPort;
    }

    /**
     * Get port status summary
     *
     * @return string Port status summary
     */
    public function getPortStatus(): string
    {
        $port = $this->getPort();
        $ip = $this->getIP();
        $isCorrect = $this->isPortCorrect();
        $configuredPort = $this->config['services']['MySQL']['port'] ?? 3306;

        if ($port === "Not detected") {
            return $this->formatter->formatError("MySQL is not listening on any port");
        }

        if ($isCorrect) {
            return $this->formatter->formatSuccess("MySQL is listening on port $port ($ip) - Matches configuration");
        } else {
            return $this->formatter->formatWarning("MySQL is listening on port $port ($ip) - Configured for port $configuredPort");
        }
    }
}