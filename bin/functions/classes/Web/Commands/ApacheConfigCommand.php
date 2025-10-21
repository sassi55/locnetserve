<?php
//-------------------------------------------------------------
// ApacheConfigCommand.php - Apache Configuration Command for LocNetServe
//-------------------------------------------------------------
// Handles Apache configuration file management and validation.
// Provides access to main config and VirtualHosts configuration.
//
// Author : Sassi Souid
// Project: LocNetServe
// Version: 1.0.0
//-------------------------------------------------------------

namespace Web\Commands;

use Core\Commands\Command;

class ApacheConfigCommand implements Command
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
        switch ($action) {
            case 'vhosts':
                return $this->openVhostsConfig();
            case 'conf':
                return $this->openMainConfig();
            case 'check':
                return $this->configCheck();
            default:
                return $this->colors['RED'] . "Unknown config action: $action" . $this->colors['RESET'];
        }
    }

    /**
     * Open the Apache VirtualHosts configuration file.
     */
    public function openVhostsConfig(): string
    {
        $httpd = $this->config['services']['Apache']['exe'] ?? '';
        
        if (empty($httpd) || !file_exists($httpd)) {
            return $this->colors['RED'] . "Apache executable not found." . $this->colors['RESET'];
        }

        // Build the absolute path to httpd-vhosts.conf
        $vhosts_file = dirname(dirname($httpd)) 
            . DIRECTORY_SEPARATOR . 'conf' 
            . DIRECTORY_SEPARATOR . 'extra' 
            . DIRECTORY_SEPARATOR . 'httpd-vhosts.conf';

        // Check if the file exists
        if (!file_exists($vhosts_file)) {
            return $this->colors['RED'] . "VirtualHosts config not found: " . $vhosts_file . $this->colors['RESET'];
        }

        // Open the file with the default editor depending on the OS
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // On Windows → open with Notepad++ if available, else Notepad
            exec("start notepad++ \"$vhosts_file\" 2>nul || start notepad \"$vhosts_file\"");
        } else {
            // On Linux/Mac → open with default system editor
            exec("xdg-open \"$vhosts_file\" >/dev/null 2>&1 &");
        }

        return $this->colors['GREEN'] . "Opened VirtualHosts configuration: " . $vhosts_file . $this->colors['RESET'];
    }

    /**
     * Open the main Apache configuration file.
     */
    public function openMainConfig(): string
    {
        $httpd = $this->config['services']['Apache']['exe'] ?? '';
        
        if (empty($httpd) || !file_exists($httpd)) {
            return $this->colors['RED'] . "Apache executable not found." . $this->colors['RESET'];
        }

        // Build the absolute path to httpd.conf
        $conf_file = dirname(dirname($httpd)) 
            . DIRECTORY_SEPARATOR . 'conf' 
            . DIRECTORY_SEPARATOR . 'httpd.conf';

        // Check if the file exists
        if (!file_exists($conf_file)) {
            return $this->colors['RED'] . "Main configuration not found: " . $conf_file . $this->colors['RESET'];
        }

        // Open the file with the default editor
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            exec("start notepad++ \"$conf_file\" 2>nul || start notepad \"$conf_file\"");
        } else {
            exec("xdg-open \"$conf_file\" >/dev/null 2>&1 &");
        }

        return $this->colors['GREEN'] . "Opened main configuration: " . $conf_file . $this->colors['RESET'];
    }

    /**
     * Check Apache configuration for syntax errors.
     */
    public function configCheck(): string
    {
        $httpd = $this->config['services']['Apache']['exe'] ?? '';
        
        if (empty($httpd) || !file_exists($httpd)) {
            return $this->colors['RED'] . "Apache executable not found: $httpd" . $this->colors['RESET'];
        }

        $cmd = escapeshellarg($httpd) . " -t 2>&1"; 
        exec($cmd, $output, $return_var);

        // Filter out "Syntax OK" line for cleaner output
        $output_clean = array_filter($output, function($line) {
            return trim($line) !== 'Syntax OK';
        });

        if ($return_var === 0) {
            $result = $this->colors['GREEN'] . "Apache configuration OK" . $this->colors['RESET'];
            if (!empty($output_clean)) {
                $result .= "\n" . implode("\n", $output_clean);
            }
            return $result;
        } else {
            return $this->colors['RED'] . "Apache configuration errors:\n" . implode("\n", $output_clean) . $this->colors['RESET'];
        }
    }

    public function getHelp(): string
    {
        return $this->colors['GREEN'] . "apache vhosts" . $this->colors['RESET'] . 
               " - Open VirtualHosts configuration file" . PHP_EOL .
               $this->colors['GREEN'] . "apache conf" . $this->colors['RESET'] . 
               " - Open main httpd.conf configuration file" . PHP_EOL .
               $this->colors['GREEN'] . "apache config check" . $this->colors['RESET'] . 
               " - Check Apache configuration for syntax errors" . PHP_EOL .
               $this->colors['CYAN'] . "Usage examples:" . $this->colors['RESET'] . PHP_EOL .
               "  lns apache vhosts" . PHP_EOL .
               "  lns apache config check" . PHP_EOL .
               $this->colors['YELLOW'] . "Note: Configuration check helps identify syntax errors before restarting Apache." . $this->colors['RESET'];
    }
}