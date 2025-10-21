<?php
//-------------------------------------------------------------
// ApacheLogCommand.php - Apache Log Command for LocNetServe
//-------------------------------------------------------------
// Handles Apache log directory access and management.
// Provides easy access to Apache log files and directories.
//
// Author : Sassi Souid
// Project: LocNetServe
// Version: 1.0.0
//-------------------------------------------------------------

namespace Web\Commands;

use Core\Commands\Command;

class ApacheLogCommand implements Command
{
    private array $colors;
    private array $config;

    public function __construct(array $colors, array $config)
    {
        $this->colors = $colors;
        $this->config = $config;
    }

    public function execute(string $action, array $args = []): string
    {
        return $this->openLogFolder();
    }

    /**
     * Open Apache log folder in file explorer.
     */
    public function openLogFolder(): string
    {
        $log_dir = $this->getLogDirectory();

        if (!$log_dir || !is_dir($log_dir)) {
            return $this->colors['RED'] . "Apache log folder not found: " . ($log_dir ?? 'undefined') . $this->colors['RESET'];
        }

        // Open the log directory
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $cmd = 'explorer.exe ' . escapeshellarg($log_dir);
            exec($cmd);
        } else {
            exec("xdg-open \"$log_dir\" >/dev/null 2>&1 &");
        }

        return $this->colors['GREEN'] . "Apache log folder opened: $log_dir" . $this->colors['RESET'];
    }

    /**
     * Get Apache log directory path.
     */
    private function getLogDirectory(): string
    {
        // First try: get from Apache path configuration
        $apache_path = $this->config['paths']['apache'] ?? '';
        if ($apache_path) {
            $log_dir = $apache_path . DIRECTORY_SEPARATOR . 'logs';
            if (is_dir($log_dir)) {
                return $log_dir;
            }
        }

        // Second try: parse from httpd.conf
        $httpd = $this->config['services']['Apache']['exe'] ?? '';
        if ($httpd && file_exists($httpd)) {
            $httpd_conf = dirname(dirname($httpd)) . DIRECTORY_SEPARATOR . 'conf' . DIRECTORY_SEPARATOR . 'httpd.conf';
            
            if (file_exists($httpd_conf)) {
                $content = file_get_contents($httpd_conf);
                
                // Look for CustomLog directive
                if (preg_match('/CustomLog\s+"?(.+?access\.\w+)"?\s+/', $content, $matches)) {
                    $log_file = $matches[1];
                    $log_dir = dirname($log_file);
                    if (is_dir($log_dir)) {
                        return $log_dir;
                    }
                }
                
                // Look for ErrorLog directive
                if (preg_match('/ErrorLog\s+"?(.+?)"?\s*$/', $content, $matches)) {
                    $log_file = $matches[1];
                    $log_dir = dirname($log_file);
                    if (is_dir($log_dir)) {
                        return $log_dir;
                    }
                }
            }
        }

        // Third try: common locations
        $common_locations = [
            $apache_path . '\\logs',
            dirname($httpd ?? '') . '\\..\\logs',
            'C:\\Apache\\logs',
            'C:\\Apache24\\logs',
            'C:\\xampp\\apache\\logs',
            '/var/log/apache2',
            '/var/log/httpd'
        ];

        foreach ($common_locations as $location) {
            if (is_dir($location)) {
                return $location;
            }
        }

        return '';
    }

    /**
     * List available log files in the log directory.
     */
    public function listLogFiles(): string
    {
        $log_dir = $this->getLogDirectory();

        if (!$log_dir || !is_dir($log_dir)) {
            return $this->colors['RED'] . "Apache log folder not found." . $this->colors['RESET'];
        }

        $log_files = glob($log_dir . DIRECTORY_SEPARATOR . '*.log');
        
        if (empty($log_files)) {
            return $this->colors['YELLOW'] . "No log files found in: $log_dir" . $this->colors['RESET'];
        }

        $output = $this->colors['GREEN'] . "Apache Log Files in $log_dir:" . $this->colors['RESET'] . PHP_EOL;
        
        foreach ($log_files as $file) {
            $filename = basename($file);
            $file_size = filesize($file) ? round(filesize($file) / 1024, 2) . ' KB' : '0 KB';
            $output .= "  " . $this->colors['CYAN'] . $filename . $this->colors['RESET'] . 
                      " (" . $this->colors['YELLOW'] . $file_size . $this->colors['RESET'] . ")" . PHP_EOL;
        }

        return $output;
    }

    public function getHelp(): string
    {
        return $this->colors['GREEN'] . "apache log" . $this->colors['RESET'] . 
               " - Open Apache log folder in file explorer" . PHP_EOL .
               $this->colors['CYAN'] . "Usage: lns apache log" . $this->colors['RESET'] . PHP_EOL .
               "Opens the Apache logs directory for easy access to log files" . PHP_EOL . PHP_EOL .
               $this->colors['YELLOW'] . "Common log files:" . $this->colors['RESET'] . PHP_EOL .
               "  • access.log - HTTP request logs" . PHP_EOL .
               "  • error.log - Error and warning logs" . PHP_EOL .
               "  • ssl_request.log - SSL/TLS connection logs";
    }
}