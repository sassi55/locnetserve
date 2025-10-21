<?php
//================================================================================
// ApacheStatus.php - Apache Status Service for LocNetServe
//================================================================================
// 
//     ██╗     ███╗   ██╗███████╗
//    ██║     ██╔██╗  ██║██╔════╝
//    ██║     ██║╚██╗ ██║███████╗
//    ██║     ██║ ╚████║╚════██║
//    ███████╗██║  ╚███║███████║
//    ╚══════╝╚═╝   ╚══╝╚══════╝
//
// Handles Apache web server status monitoring and service state detection.
// Provides detailed information about Apache service status, process management,
// and health checks.
//
// FEATURES:
// • Service status detection (running/stopped)
// • Process ID (PID) identification
// • Port and network binding verification
// • Service health assessment
// • Process list monitoring
// • Error log monitoring
//
// Author      : Sassi Souid
// Email       : locnetserve@gmail.com
// Project     : LocNetServe
// Version     : 1.0.0
// Created     : 2025
// Last Update : 2025
// License     : MIT
//================================================================================

namespace Web\Services;

use Database\Formatters\DatabaseFormatter;

class ApacheStatus
{
    private array $config;
    private DatabaseFormatter $formatter;
    private array $colors;
    private string $pid_file;
    private array $services;

    /**
     * ApacheStatus constructor.
     *
     * @param array $config Application configuration
     * @param DatabaseFormatter $formatter Output formatter
     */
    public function __construct(array $config, DatabaseFormatter $formatter, array $colors)
    {
        $this->config = $config;
        $this->formatter = $formatter;
         $this->colors = $colors;
        $this->services = $GLOBALS['services'] ?? [];
        $this->pid_file = $GLOBALS['paths']['config'] . 'apache.pid';
    }


    /**
     * Get Apache service status with detailed information.
     *
     * @return string Formatted status message
     */
    public function getStatus(): string
    {
        $pid = $this->getPid();
        
        if ($pid) {
            $status = $this->formatter->formatSuccess("Apache is running (PID: $pid)");
            
            // Add additional details if available
            $port = $this->getPort();
            $version = $this->getVersion();
            
            $details = [];
            if ($port !== "Not detected") {
                $details[] = "Port: $port";
            }
            if ($version !== "Version not detected") {
                $details[] = "Version: $version";
            }
            
            if (!empty($details)) {
                $status .= PHP_EOL . $this->formatter->formatInfo("Details: " . implode(", ", $details));
            }
            $status .=  PHP_EOL . $this->getDetailedStatus();
            return $status;
        }
        
        return $this->formatter->formatError("Apache is not running");
    }

    /**
     * Get detailed status information
     *
     * @return string Detailed status report
     */
    public function getDetailedStatus(): string
    {
        $output = $this->formatter->formatInfo("=== Apache Detailed Status ===") . PHP_EOL . PHP_EOL;
        
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
        $output .= $this->formatter->formatSuccess("▲ Network Information:") . PHP_EOL;
        $output .= "  " . $this->formatter->formatInfo("• Listening Port: ") . $this->colors['GREEN'] . $port . $this->colors['RESET'] . PHP_EOL;
        $output .= "  " . $this->formatter->formatInfo("• Default Port: ") . $this->colors['WHITE'] . "80" . $this->colors['RESET'] . PHP_EOL;
        $output .= "  " . $this->formatter->formatInfo("• SSL Port: ") . $this->colors['WHITE'] . "443" . $this->colors['RESET'] . PHP_EOL;

        $output .= PHP_EOL;

        // Version Information
        $version = $this->getVersion();
        $output .= $this->formatter->formatSuccess("▲ Version Information:") . PHP_EOL;
        $output .= "  " . $this->formatter->formatInfo("• Apache Version: ") . $this->colors['YELLOW'] . $version . $this->colors['RESET'] . PHP_EOL;

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
     * Get Apache PID from file or process list.
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
        $process_names = ['httpd.exe', 'apache.exe', 'httpd', 'apache2'];
        
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            foreach ($process_names as $process_name) {
                exec("tasklist /fi \"imagename eq $process_name\" /fo csv", $output);
                if (count($output) > 1) {
                    $parts = str_getcsv($output[1]);
                    if (isset($parts[1]) && is_numeric($parts[1])) {
                        return (int)$parts[1];
                    }
                }
            }
        } else {
            foreach ($process_names as $process_name) {
                exec("ps -e -o pid,comm | grep $process_name", $output);
                foreach ($output as $line) {
                    if (preg_match('/^\s*(\d+)\s+' . $process_name . '/', $line, $matches)) {
                        return (int)$matches[1];
                    }
                }
            }
        }

        return false;
    }

    /**
     * Get all Apache PIDs from process list.
     *
     * @return array Array of process IDs
     */
    public function getAllPids(): array
    {
        $pids = [];
        $process_names = ['httpd.exe', 'apache.exe', 'httpd', 'apache2'];

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            foreach ($process_names as $process_name) {
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
            }
        } else {
            foreach ($process_names as $process_name) {
                exec("ps -e -o pid,comm | grep $process_name", $output);
                foreach ($output as $line) {
                    if (preg_match('/^\s*(\d+)\s+' . $process_name . '/', $line, $matches)) {
                        $pids[] = (int)$matches[1];
                    }
                }
            }
        }

        return array_unique($pids);
    }

    /**
     * Get Apache server port.
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
                        if (preg_match('/:(\d+)\s+.*\s+' . $pid . '\\/httpd/', $line, $matches)) {
                            return $matches[1];
                        }
                        if (preg_match('/:(\d+)\s+.*\s+' . $pid . '\\/apache2/', $line, $matches)) {
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
     * Get Apache version from executable.
     *
     * @return string Version information
     */
    public function getVersion(): string
    {
        $apache_exe = $this->services['Apache']['exe'] ?? '';
        
        if (empty($apache_exe) || !file_exists($apache_exe)) {
            // Try common Apache executable names
            $common_paths = [
                $this->config['services']['Apache']['exe']?? '' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'httpd.exe',
                $this->config['paths']['apache'] ?? '' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'apache.exe',
                '/usr/sbin/apache2',
                '/usr/sbin/httpd',
                '/usr/local/apache2/bin/httpd'
            ];
            
            foreach ($common_paths as $path) {
                if (file_exists($path)) {
                    $apache_exe = $path;
                    break;
                }
            }
        }

        if (empty($apache_exe) || !file_exists($apache_exe)) {
            return "Apache executable not found";
        }

        $output = [];
        exec('"' . $apache_exe . '" -v', $output);
        return !empty($output) ? trim($output[0]) : "Version not detected";
    }

    /**
     * Check if Apache service is running
     *
     * @return bool True if service is running
     */
    public function isRunning(): bool
    {
        return $this->getPid() !== false;
    }

    /**
     * Get service health status
     *
     * @return string Health status message
     */
    public function getHealthStatus(): string
    {
        $isRunning = $this->isRunning();
        $port = $this->getPort();

        if (!$isRunning) {
            return $this->formatter->formatError("❌ Service is not running");
        }

        if ($port === "Not detected") {
            return $this->formatter->formatWarning("⚠️ Service is running but no listening port detected");
        }

        return $this->formatter->formatSuccess("✅ Service is healthy and responsive on port $port");
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