<?php
//-------------------------------------------------------------
// PHPConfigCommand.php - PHP Configuration Command for LocNetServe
//-------------------------------------------------------------
// Handles PHP configuration file management.
// Provides access to php.ini and other configuration files.
//
// Author : Sassi Souid
// Project: LocNetServe
// Version: 1.0.0
//-------------------------------------------------------------

namespace Web\Commands;

use Core\Commands\Command;

class PHPConfigCommand implements Command
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
        return $this->openPHPIni();
    }

    public function openPHPIni(): string
    {
        $php_exe = $this->getPHPExecutable();
        
        if (empty($php_exe) || !file_exists($php_exe)) {
            return $this->colors['RED'] . "PHP executable not found." . $this->colors['RESET'];
        }

        // Get php.ini path
        exec('"' . $php_exe . '" --ini', $output, $return_var);

        if ($return_var !== 0 || empty($output)) {
            return $this->colors['RED'] . "Failed to locate php.ini file." . $this->colors['RESET'];
        }

        $ini_path = '';
        foreach ($output as $line) {
            if (strpos($line, 'Loaded Configuration File') !== false) {
                preg_match('/:\s*(.+)$/', $line, $matches);
                $ini_path = $matches[1] ?? '';
                break;
            }
        }

        if (empty($ini_path) || !file_exists($ini_path)) {
            // Fallback: common locations
            $php_dir = dirname($php_exe);
            $common_locations = [
                $php_dir . '\\php.ini',
                $php_dir . '\\..\\php.ini',
                $this->config['paths']['php'] . '\\php.ini',
                'C:\\Windows\\php.ini'
            ];

            foreach ($common_locations as $location) {
                if (file_exists($location)) {
                    $ini_path = $location;
                    break;
                }
            }
        }

        if (empty($ini_path) || !file_exists($ini_path)) {
            return $this->colors['RED'] . "php.ini file not found." . $this->colors['RESET'];
        }

        // Open php.ini in default editor
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            exec("start notepad++ \"$ini_path\" 2>nul || start notepad \"$ini_path\"");
        } else {
            exec("xdg-open \"$ini_path\" >/dev/null 2>&1 &");
        }

        $result = $this->colors['GREEN'] . "PHP configuration opened: " . $this->colors['RESET'] . PHP_EOL;
        $result .= $this->colors['CYAN'] . $ini_path . $this->colors['RESET'] . PHP_EOL . PHP_EOL;
        
        // Show additional configuration info
        $result .= $this->colors['YELLOW'] . "Configuration Scan:" . $this->colors['RESET'] . PHP_EOL;
        foreach ($output as $line) {
            if (trim($line) && strpos($line, 'Scan') === false) {
                $result .= "  " . trim($line) . PHP_EOL;
            }
        }

        return $result;
    }

    private function getPHPExecutable(): string
    {
        return $this->config['services']['PHP']['exe'] ?? 
               $this->config['paths']['php'] . DIRECTORY_SEPARATOR . 'php.exe' ?? 
               '';
    }

    public function getHelp(): string
    {
        return $this->colors['GREEN'] . "php ini" . $this->colors['RESET'] . 
               " - Open php.ini configuration file in editor" . PHP_EOL .
               $this->colors['CYAN'] . "Usage: lns php ini" . $this->colors['RESET'] . PHP_EOL .
               "Opens the main PHP configuration file for editing" . PHP_EOL .
               $this->colors['YELLOW'] . "Note: Changes may require restarting Apache to take effect" . $this->colors['RESET'];
    }
}