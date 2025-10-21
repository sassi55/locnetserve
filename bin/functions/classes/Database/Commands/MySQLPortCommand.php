<?php
/**
 * -------------------------------------------------------------
 *  MySQLPortCommand.php - MySQL Port Command for LocNetServe
 * -------------------------------------------------------------
 *  Manages MySQL port configuration and monitoring by:
 *    - Detecting active MySQL ports and IP bindings
 *    - Monitoring port availability and connectivity
 *    - Providing detailed port information and status
 *    - Supporting network configuration diagnostics
 *
 *  Author : Sassi Souid
 *  Email  : locnetserve@gmail.com
 *  Project: LocNetServe
 *  Version: 1.0.0
 * -------------------------------------------------------------
 */

namespace Database\Commands;

use Core\Commands\Command;

class MySQLPortCommand implements Command
{
    private array $colors;
    private array $config;

    /**
     * MySQLPortCommand constructor.
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
     * Execute MySQL port command.
     *
     * @param string $action The action to perform
     * @param array $args Command arguments
     * @return string Port information message
     */
    public function execute(string $action, array $args = []): string
    {
        return $this->getPortInfo();
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
        
        $output = $this->colors['GREEN'] . "MySQL Port Information:" . $this->colors['RESET'] . PHP_EOL;
        $output .= "Port: " . $this->colors['CYAN'] . $port . $this->colors['RESET'] . PHP_EOL;
        $output .= "IP: " . $this->colors['CYAN'] . $ip . $this->colors['RESET'] . PHP_EOL;
        
        // Add connection details
        $output .= PHP_EOL . $this->colors['YELLOW'] . "Connection Details:" . $this->colors['RESET'] . PHP_EOL;
        $output .= "Host: " . $this->colors['WHITE'] . "localhost" . $this->colors['RESET'] . PHP_EOL;
        $output .= "Full Address: " . $this->colors['WHITE'] . "$ip:$port" . $this->colors['RESET'];
        
        return $output;
    }

    /**
     * Get MySQL server port from process network connections.
     *
     * @return string Detected port or error message
     */
    public function getPort(): string
    {
        // Get all MySQL PIDs
        $pids = $this->getAllPids();
        
        foreach ($pids as $pid) {
            $port = $this->findPortByPid($pid);
            if ($port !== null) {
                return $port;
            }
        }

        return "Port not detected";
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

        return "Unknown IP";
    }

    /**
     * Find port by process ID.
     *
     * @param int $pid Process ID to check
     * @return string|null Port number or null if not found
     */
    private function findPortByPid(int $pid): ?string
    {
        exec("netstat -ano | findstr $pid", $output, $return_var);
        
        if ($return_var === 0 && !empty($output)) {
            foreach ($output as $line) {
                // Match: TCP    127.0.0.1:3306    0.0.0.0:0    LISTENING    5320
                if (preg_match('/:(\d+)\s+.*\s+' . $pid . '$/', $line, $matches)) {
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
        exec("netstat -ano | findstr $pid", $output, $return_var);
        
        if ($return_var === 0 && !empty($output)) {
            foreach ($output as $line) {
                // Match: TCP    127.0.0.1:3306    0.0.0.0:0    LISTENING    5320
                if (preg_match('/\s+([\d\.]+):\d+\s+.*\s+' . $pid . '$/', $line, $matches)) {
                    return $matches[1];
                }
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
     * Get help information for port command.
     *
     * @return string Help message
     */
    public function getHelp(): string
    {
        return $this->colors['GREEN'] . "mysql ports" . $this->colors['RESET'] . 
               " - Display MySQL listening port and IP information" . PHP_EOL .
               $this->colors['CYAN'] . "Usage: lns mysql ports" . $this->colors['RESET'] . PHP_EOL .
               "Displays: Listening port, IP address, connection details";
    }
}