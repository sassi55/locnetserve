<?php
//================================================================================
// ApacheLog.php - Apache Log Service for LocNetServe
//================================================================================
// 
//     ██╗     ███╗   ██╗███████╗
//    ██║     ██╔██╗  ██║██╔════╝
//    ██║     ██║╚██╗ ██║███████╗
//    ██║     ██║ ╚████║╚════██║
//    ███████╗██║  ╚███║███████║
//    ╚══════╝╚═╝   ╚══╝╚══════╝
//
// Handles Apache web server log file access and management.
// Provides convenient access to Apache log files and directories for
// troubleshooting and monitoring purposes.
//
// FEATURES:
// • Log directory navigation and access
// • Error log file viewing
// • Access log file monitoring
// • Log file size and rotation information
// • Real-time log tailing capabilities
// • Log file search and filtering
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

class ApacheLog
{
    private array $config;
    private DatabaseFormatter $formatter;
    private array $colors;
    private string $logDir;

    /**
     * ApacheLog constructor.
     *
     * @param array $config Application configuration
     * @param DatabaseFormatter $formatter Output formatter
     */
    public function __construct(array $config, DatabaseFormatter $formatter, array $colors)
    {
        $this->config = $config;
        $this->formatter = $formatter;
         $this->colors = $colors;
        $this->logDir = $this->getLogDirectory();
    }


    /**
     * Get Apache log directory
     */
    private function getLogDirectory(): string
    {
        // Try to get log directory from configuration
        $logDir = $this->config['paths']['apache_logs'] ?? '';
        
        if (!empty($logDir) && is_dir($logDir)) {
            return $logDir;
        }

        // Common Apache log directories
        $common_dirs = [
            $this->config['paths']['apache'] ?? '' . DIRECTORY_SEPARATOR . 'logs',
            '/var/log/apache2',
            '/var/log/httpd',
            '/usr/local/apache2/logs',
            'C:\\Apache24\\logs',
            'C:\\xampp\\apache\\logs'
        ];

        foreach ($common_dirs as $dir) {
            if (is_dir($dir)) {
                return $dir;
            }
        }

        // Fallback to a default directory
        return $this->config['paths']['base'] ?? dirname(__DIR__, 5) . DIRECTORY_SEPARATOR . 'logs';
    }

    /**
     * Open Apache log folder in file explorer.
     *
     * @return string Success or error message
     */
    public function openLogFolder(): string
    {
        if (!is_dir($this->logDir)) {
            return $this->formatter->formatError("Apache log directory not found: " . $this->logDir);
        }

        try {
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                // Windows - open in Explorer
                exec('explorer "' . $this->logDir . '"');
                return $this->formatter->formatSuccess("✅ Apache log folder opened: " . $this->logDir);
            } else {
                // Linux/Unix - try to open in default file manager
                $file_managers = ['nautilus', 'dolphin', 'thunar', 'pcmanfm', 'nemo'];
                $opened = false;
                
                foreach ($file_managers as $manager) {
                    if ($this->commandExists($manager)) {
                        exec($manager . ' "' . $this->logDir . '" &');
                        $opened = true;
                        break;
                    }
                }
                
                if ($opened) {
                    return $this->formatter->formatSuccess("✅ Apache log folder opened: " . $this->logDir);
                } else {
                    // Fallback - just show the path
                    return $this->formatter->formatInfo("Apache log directory: " . $this->logDir) . PHP_EOL .
                           $this->formatter->formatWarning("Note: No file manager found to open the directory automatically.");
                }
            }
        } catch (\Exception $e) {
            return $this->formatter->formatError("Failed to open log folder: " . $e->getMessage());
        }
    }

    /**
     * Get log directory information
     *
     * @return string Log directory information
     */
    public function getLogInfo(): string
    {
        if (!is_dir($this->logDir)) {
            return $this->formatter->formatError("Apache log directory not found: " . $this->logDir);
        }

        $output = $this->formatter->formatInfo("=== Apache Log Information ===") . PHP_EOL . PHP_EOL;
        
        $output .= $this->formatter->formatSuccess("▲ Log Directory:") . PHP_EOL;
        $output .= "  " . $this->formatter->formatInfo("• Path: ") . $this->colors['GREEN'] . $this->logDir . $this->colors['RESET'] . PHP_EOL;

        $output .= PHP_EOL;

        // List log files
        $logFiles = $this->getLogFiles();
        $output .= $this->formatter->formatSuccess("▲ Log Files:") . PHP_EOL;
        
        if (empty($logFiles)) {
            $output .= "  " . $this->formatter->formatWarning("• No log files found in directory") . PHP_EOL;
        } else {
            foreach ($logFiles as $fileInfo) {
                $status = $fileInfo['size'] > 0 ? '📄' : '📝';
                $output .= "  " . $this->formatter->formatInfo("• $status ") . 
                          $this->colors['CYAN'] . $fileInfo['name'] . $this->colors['RESET'] . 
                          " (" . $this->colors['YELLOW'] . $fileInfo['size_formatted'] . $this->colors['RESET'] . ") - " .
                          $this->colors['WHITE'] . $fileInfo['modified'] . $this->colors['RESET'] . PHP_EOL;
            }
        }

        $output .= PHP_EOL;

        // Common log file locations
        $output .= $this->formatter->formatSuccess("▲ Common Log Files:") . PHP_EOL;
        $common_logs = [
            'error.log' => 'Error and diagnostic messages',
            'access.log' => 'Client access records',
            'ssl_error.log' => 'SSL/TLS error messages',
            'ssl_access.log' => 'HTTPS access records'
        ];

        foreach ($common_logs as $logFile => $description) {
            $fullPath = $this->logDir . DIRECTORY_SEPARATOR . $logFile;
            $exists = file_exists($fullPath) ? '✅' : '❌';
            $output .= "  " . $this->formatter->formatInfo("• $exists ") . 
                      $this->colors['WHITE'] . $logFile . $this->colors['RESET'] . 
                      " - " . $description . PHP_EOL;
        }

        return $output;
    }

    /**
     * Get list of log files in the log directory
     *
     * @return array Array of log file information
     */
    private function getLogFiles(): array
    {
        $logFiles = [];
        $files = glob($this->logDir . DIRECTORY_SEPARATOR . '*.log');
        
        foreach ($files as $file) {
            if (is_file($file)) {
                $logFiles[] = [
                    'name' => basename($file),
                    'path' => $file,
                    'size' => filesize($file),
                    'size_formatted' => $this->formatFileSize(filesize($file)),
                    'modified' => date('Y-m-d H:i:s', filemtime($file))
                ];
            }
        }

        // Sort by modification time (newest first)
        usort($logFiles, function($a, $b) {
            return filemtime($b['path']) - filemtime($a['path']);
        });

        return $logFiles;
    }

    /**
     * Show last lines of a specific log file
     *
     * @param string $logFile Log file name
     * @param int $lines Number of lines to show
     * @return string Log content or error message
     */
    public function showLogTail(string $logFile = 'error.log', int $lines = 50): string
    {
        $fullPath = $this->logDir . DIRECTORY_SEPARATOR . $logFile;
        
        if (!file_exists($fullPath)) {
            return $this->formatter->formatError("Log file not found: " . $fullPath);
        }

        if (!is_readable($fullPath)) {
            return $this->formatter->formatError("Log file is not readable: " . $fullPath);
        }

        try {
            $content = $this->tailFile($fullPath, $lines);
            
            $output = $this->formatter->formatInfo("=== Last $lines lines of $logFile ===") . PHP_EOL . PHP_EOL;
            
            if (empty($content)) {
                $output .= $this->formatter->formatWarning("Log file is empty");
            } else {
                $output .= $content;
            }
            
            $output .= PHP_EOL . $this->formatter->formatInfo("File: " . $fullPath);
            $output .= PHP_EOL . $this->formatter->formatInfo("Size: " . $this->formatFileSize(filesize($fullPath)));
            
            return $output;

        } catch (\Exception $e) {
            return $this->formatter->formatError("Failed to read log file: " . $e->getMessage());
        }
    }

    /**
     * Read last lines from a file
     */
    private function tailFile(string $filepath, int $lines = 50): string
    {
        $data = '';
        $fp = fopen($filepath, 'r');
        if (!$fp) {
            return '';
        }

        fseek($fp, -1, SEEK_END);
        $pos = ftell($fp);
        $buffer = '';
        $lineCount = 0;

        // Read backwards until we have enough lines
        while ($lineCount < $lines + 1 && $pos > 0) {
            $char = fgetc($fp);
            if ($char === "\n") {
                $lineCount++;
            }
            $buffer = $char . $buffer;
            fseek($fp, --$pos);
        }

        fclose($fp);
        return $buffer;
    }

    /**
     * Check if a command exists in the system
     */
    private function commandExists(string $command): bool
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            exec("where $command 2>nul", $output, $return_var);
        } else {
            exec("which $command 2>/dev/null", $output, $return_var);
        }
        
        return $return_var === 0;
    }

    /**
     * Format file size for display
     */
    private function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}