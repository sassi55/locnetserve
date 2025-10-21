<?php
/**
 * -------------------------------------------------------------
 *  MySQLStatusCommand.php - MySQL Status Command for LocNetServe
 * -------------------------------------------------------------
 *  Monitors MySQL service status and process information by:
 *    - Detecting MySQL process ID and service state
 *    - Providing detailed status information and health checks
 *    - Monitoring service availability and responsiveness
 *    - Supporting process management and diagnostics
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

class MySQLStatusCommand implements Command
{
    private array $colors;
    private array $config;
    private string $pid_file;
    private array $services;

    /**
     * MySQLStatusCommand constructor.
     *
     * @param array $colors Color configuration for CLI output
     * @param array $config Application configuration
     */
    public function __construct(array $colors, array $config)
    {
        $this->colors = $colors;
        $this->config = $config;
        $this->services = $GLOBALS['services'] ?? [];
        $this->pid_file = $GLOBALS['paths']['config'] . 'mysql.pid';
    }

    /**
     * Execute MySQL status command.
     *
     * @param string $action The action to perform
     * @param array $args Command arguments
     * @return string Status message
     */
    public function execute(string $action, array $args = []): string
    {
        return $this->getStatus();
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
            $status = $this->colors['GREEN'] . " MySQL is running (PID: $pid)" . $this->colors['RESET'];
            
            // Add additional details if available
            $port = $this->getPort();
            $version = $this->getVersion();
            
            $details = [];
            if ($port !== "Port not detected") {
                $details[] = "Port: $port";
            }
            if ($version !== "Version not detected") {
                $details[] = "Version: $version";
            }
            
            if (!empty($details)) {
                $status .= PHP_EOL . $this->colors['CYAN'] . "Details: " . implode(", ", $details) . $this->colors['RESET'];
            }
            
            return $status;
        }
        
        return $this->colors['RED'] . " MySQL is not running" . $this->colors['RESET'];
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
        }

        // 3. Check Unix/Linux processes
        else {
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
    private function getPort(): string
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

        return "Port not detected";
    }

    /**
     * Get MySQL version from executable.
     *
     * @return string Version information
     */
    private function getVersion(): string
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

    /**
     * Get help information for status command.
     *
     * @return string Help message
     */
    public function getHelp(): string
    {
        return $this->colors['GREEN'] . "mysql status" . $this->colors['RESET'] . 
               " - Check MySQL service status, PID, port, and version" . PHP_EOL .
               $this->colors['CYAN'] . "Usage: lns mysql status" . $this->colors['RESET'] . PHP_EOL .
               "Displays: Service state, Process ID, Listening port, MySQL version";
    }
}