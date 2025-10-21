<?php
//================================================================================
// ApacheConfig.php - Apache Configuration Service for LocNetServe
//================================================================================
// 
//     ██╗     ███╗   ██╗███████╗
//    ██║     ██╔██╗  ██║██╔════╝
//    ██║     ██║╚██╗ ██║███████╗
//    ██║     ██║ ╚████║╚════██║
//    ███████╗██║  ╚███║███████║
//    ╚══════╝╚═╝   ╚══╝╚══════╝
//
// Handles Apache web server configuration file management and validation.
// Provides access to main configuration files, virtual hosts, and syntax checking.
//
// FEATURES:
// • Main configuration file (httpd.conf) access
// • Virtual hosts configuration management
// • Configuration syntax validation
// • Configuration file backup and restore
// • Directive search and analysis
// • Module configuration management
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

class ApacheConfig
{
    private array $config;
    private DatabaseFormatter $formatter;
    private array $colors;
    private string $configDir;

    /**
     * ApacheConfig constructor.
     *
     * @param array $config Application configuration
     * @param DatabaseFormatter $formatter Output formatter
     */
    public function __construct(array $config, DatabaseFormatter $formatter, array $colors)
    {
        $this->config = $config;
        $this->formatter = $formatter;
        $this->colors = $colors;
        $this->configDir = $this->getConfigDirectory();
    }
   public function checkConfig(){
	   
	   $conf =$this->checkConfiguration();
	   $conf .=PHP_EOL . $this->getConfigInfo();
	   return $conf;
	   
	   
   }

    /**
     * Get Apache configuration directory
     */
    private function getConfigDirectory(): string
    {
        // Try to get config directory from configuration
        $configDir = $this->config['paths']['apache_conf'] ?? '';
        
        if (!empty($configDir) && is_dir($configDir)) {
            return $configDir;
        }

        // Common Apache configuration directories
        $common_dirs = [
            $this->config['paths']['apache'] ?? '' . DIRECTORY_SEPARATOR . 'conf',
            '/etc/apache2',
            '/etc/httpd',
            '/usr/local/apache2/conf',
            'C:\\Apache24\\conf',
            'C:\\xampp\\apache\\conf'
        ];

        foreach ($common_dirs as $dir) {
            if (is_dir($dir)) {
                return $dir;
            }
        }

        // Fallback to a default directory
        return $this->config['paths']['base'] ?? dirname(__DIR__, 5) . DIRECTORY_SEPARATOR . 'conf';
    }

    /**
     * Open main Apache configuration file (httpd.conf).
     *
     * @return string Success or error message
     */
    public function openMainConfig(): string
    {
        $configFile = $this->findMainConfigFile();
        
        if (empty($configFile)) {
            return $this->formatter->formatError("Apache main configuration file not found");
        }

        return $this->openFileInEditor($configFile, "Apache main configuration");
    }

    /**
     * Open VirtualHosts configuration file.
     *
     * @return string Success or error message
     */
    public function openVirtualHosts(): string
    {
        $vhostsFile = $this->findVirtualHostsFile();
        
        if (empty($vhostsFile)) {
            return $this->formatter->formatError("Apache VirtualHosts configuration file not found");
        }

        return $this->openFileInEditor($vhostsFile, "Apache VirtualHosts configuration");
    }

    /**
     * Check Apache configuration for syntax errors.
     *
     * @return string Configuration check results
     */
    public function checkConfiguration(): string
    {
        $apacheExe = $this->getApacheExecutable();
        
        if (empty($apacheExe) || !file_exists($apacheExe)) {
            return $this->formatter->formatError("Apache executable not found for configuration test");
        }

        try {
            $output = [];
            exec('"' . $apacheExe . '" -t 2>&1', $output, $return_var);

            $result = implode("\n", $output);

            if ($return_var === 0) {
                return $this->formatter->formatSuccess("Apache configuration syntax is OK") . PHP_EOL .
                       $this->formatter->formatInfo("Output: " . $result);
            } else {
                return $this->formatter->formatError("Apache configuration syntax error") . PHP_EOL .
                       $this->formatter->formatError("Output: " . $result);
            }

        } catch (\Exception $e) {
            return $this->formatter->formatError("Configuration check failed: " . $e->getMessage());
        }
    }

    /**
     * Get Apache configuration information
     *
     * @return string Configuration information
     */
    public function getConfigInfo(): string
    {
        $output = $this->formatter->formatInfo("=== Apache Configuration Information ===") . PHP_EOL . PHP_EOL;
        
        // Main configuration file
        $mainConfig = $this->findMainConfigFile();
        $output .= $this->formatter->formatSuccess("▲ Main Configuration:") . PHP_EOL;
        if ($mainConfig) {
            $output .= "  " . $this->formatter->formatInfo("• File: ") . $this->colors['GREEN'] . $mainConfig . $this->colors['RESET'] . PHP_EOL;
            $output .= "  " . $this->formatter->formatInfo("• Size: ") . $this->colors['CYAN'] . $this->formatFileSize(filesize($mainConfig)) . $this->colors['RESET'] . PHP_EOL;
            $output .= "  " . $this->formatter->formatInfo("• Modified: ") . $this->colors['YELLOW'] . date('Y-m-d H:i:s', filemtime($mainConfig)) . $this->colors['RESET'] . PHP_EOL;
        } else {
            $output .= "  " . $this->formatter->formatError("• Main configuration file not found") . PHP_EOL;
        }

        $output .= PHP_EOL;

        // Virtual hosts configuration
        $vhostsFile = $this->findVirtualHostsFile();
        $output .= $this->formatter->formatSuccess("▲ Virtual Hosts:") . PHP_EOL;
        if ($vhostsFile) {
            $output .= "  " . $this->formatter->formatInfo("• File: ") . $this->colors['GREEN'] . $vhostsFile . $this->colors['RESET'] . PHP_EOL;
            $output .= "  " . $this->formatter->formatInfo("• Size: ") . $this->colors['CYAN'] . $this->formatFileSize(filesize($vhostsFile)) . $this->colors['RESET'] . PHP_EOL;
        } else {
            $output .= "  " . $this->formatter->formatWarning("• Virtual hosts file not found") . PHP_EOL;
        }

        $output .= PHP_EOL;

        // Configuration directory
        $output .= $this->formatter->formatSuccess("▲ Configuration Directory:") . PHP_EOL;
        $output .= "  " . $this->formatter->formatInfo("• Path: ") . $this->colors['WHITE'] . $this->configDir . $this->colors['RESET'] . PHP_EOL;
        $output .= "  " . $this->formatter->formatInfo("• Exists: ") . 
                  (is_dir($this->configDir) ? 
                   $this->colors['GREEN'] . 'Yes ✅' . $this->colors['RESET'] : 
                   $this->colors['RED'] . 'No ❌' . $this->colors['RESET']) . PHP_EOL;

        $output .= PHP_EOL;

        // Configuration files list
        $configFiles = $this->getConfigFiles();
        $output .= $this->formatter->formatSuccess("▲ Configuration Files:") . PHP_EOL;
        if (empty($configFiles)) {
            $output .= "  " . $this->formatter->formatWarning("• No configuration files found") . PHP_EOL;
        } else {
            foreach ($configFiles as $file) {
                $output .= "  " . $this->formatter->formatInfo("• ") . 
                          $this->colors['CYAN'] . $file . $this->colors['RESET'] . PHP_EOL;
            }
        }

        return $output;
    }

    /**
     * Find main Apache configuration file
     */
    private function findMainConfigFile(): string
    {
        $possible_files = [
            $this->configDir . DIRECTORY_SEPARATOR . 'httpd.conf',
            $this->configDir . DIRECTORY_SEPARATOR . 'apache2.conf',
            '/etc/apache2/apache2.conf',
            '/etc/httpd/conf/httpd.conf'
        ];

        foreach ($possible_files as $file) {
            if (file_exists($file)) {
                return $file;
            }
        }

        return '';
    }

    /**
     * Find VirtualHosts configuration file
     */
    private function findVirtualHostsFile(): string
    {
        $possible_files = [
            $this->configDir . DIRECTORY_SEPARATOR . 'extra' . DIRECTORY_SEPARATOR . 'httpd-vhosts.conf',
            $this->configDir . DIRECTORY_SEPARATOR . 'vhosts.conf',
            $this->configDir . DIRECTORY_SEPARATOR . 'sites-enabled' . DIRECTORY_SEPARATOR . '000-default.conf',
            '/etc/apache2/sites-enabled/000-default.conf',
            '/etc/httpd/conf.d/vhosts.conf'
        ];

        foreach ($possible_files as $file) {
            if (file_exists($file)) {
                return $file;
            }
        }

        // Check for any .conf file in sites-enabled
        $sitesEnabled = $this->configDir . DIRECTORY_SEPARATOR . 'sites-enabled';
        if (is_dir($sitesEnabled)) {
            $confFiles = glob($sitesEnabled . DIRECTORY_SEPARATOR . '*.conf');
            if (!empty($confFiles)) {
                return $confFiles[0];
            }
        }

        return '';
    }

    /**
     * Get list of configuration files
     */
    private function getConfigFiles(): array
    {
        $files = [];
        $patterns = [
            $this->configDir . DIRECTORY_SEPARATOR . '*.conf',
            $this->configDir . DIRECTORY_SEPARATOR . '*.cfg'
        ];

        foreach ($patterns as $pattern) {
            $found = glob($pattern);
            foreach ($found as $file) {
                if (is_file($file)) {
                    $files[] = basename($file);
                }
            }
        }

        return array_unique($files);
    }

    /**
     * Get Apache executable path
     */
    private function getApacheExecutable(): string
    {
        $common_paths = [
            $this->config['services']['Apache']['exe']?? '' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'httpd.exe',
            $this->config['paths']['base'] ?? '' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'apache.exe',
            '/usr/sbin/apache2',
            '/usr/sbin/httpd',
            '/usr/local/apache2/bin/httpd'
        ];
        
        foreach ($common_paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return '';
    }

    /**
     * Open file in default editor
     */
    private function openFileInEditor(string $filePath, string $description): string
    {
        if (!file_exists($filePath)) {
            return $this->formatter->formatError("File not found: " . $filePath);
        }

        try {
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                // Windows - open with default editor
                exec('start "" "' . $filePath . '"');
                return $this->formatter->formatSuccess("✅ $description opened: " . $filePath);
            } else {
                // Linux/Unix - try to open with default editor
                $editors = ['xdg-open', 'gnome-open', 'kde-open', 'open'];
                $opened = false;
                
                foreach ($editors as $editor) {
                    if ($this->commandExists($editor)) {
                        exec($editor . ' "' . $filePath . '" &');
                        $opened = true;
                        break;
                    }
                }
                
                if ($opened) {
                    return $this->formatter->formatSuccess("✅ $description opened: " . $filePath);
                } else {
                    return $this->formatter->formatInfo("$description file: " . $filePath) . PHP_EOL .
                           $this->formatter->formatWarning("Note: No default editor found to open the file automatically.");
                }
            }
        } catch (\Exception $e) {
            return $this->formatter->formatError("Failed to open file: " . $e->getMessage());
        }
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