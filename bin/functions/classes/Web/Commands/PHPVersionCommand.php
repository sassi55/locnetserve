<?php
//-------------------------------------------------------------
// PHPVersionCommand.php - PHP Version Command for LocNetServe
//-------------------------------------------------------------
// Handles PHP version detection, display, and update checking.
// Provides detailed PHP version and configuration information.
//
// Author : Sassi Souid
// Project: LocNetServe
// Version: 1.0.0
//-------------------------------------------------------------

namespace Web\Commands;

use Core\Commands\Command;

class PHPVersionCommand implements Command
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
            case 'version':
                return $this->getVersion();
            case 'update':
                return $this->checkUpdate();
            default:
                return $this->colors['RED'] . "Unknown version action: $action" . $this->colors['RESET'];
        }
    }

    public function getVersion(): string
    {
        $php_exe = $this->getPHPExecutable();
        
        if (empty($php_exe) || !file_exists($php_exe)) {
            return $this->colors['RED'] . "PHP executable not found or not configured." . $this->colors['RESET'];
        }

        $output = [];
        exec('"' . $php_exe . '" -v', $output, $return_var);

        if ($return_var !== 0 || empty($output)) {
            return $this->colors['RED'] . "Failed to get PHP version." . $this->colors['RESET'];
        }

        $versionString = trim($output[0] ?? "Version information not available");
        
        // Extract version number
        preg_match('/PHP\s+(\d+\.\d+\.\d+)/', $versionString, $matches);
        $versionNumber = $matches[1] ?? 'unknown';

        $output = $this->colors['GREEN'] . "PHP Version Information:" . $this->colors['RESET'] . PHP_EOL;
        $output .= $this->colors['CYAN'] . $versionString . $this->colors['RESET'] . PHP_EOL . PHP_EOL;
        
        // Additional PHP information
        $output .= $this->colors['YELLOW'] . "Detailed Information:" . $this->colors['RESET'] . PHP_EOL;
        
        // Get more details
        exec('"' . $php_exe . '" -i | findstr "Thread Safety\|Compiler\|Architecture"', $details);
        foreach ($details as $detail) {
            if (trim($detail)) {
                $output .= "  " . trim($detail) . PHP_EOL;
            }
        }

        $output .= PHP_EOL . $this->colors['WHITE'] . "Executable: " . $php_exe . $this->colors['RESET'];

        return $output;
    }

    public function checkUpdate(): string
    {
        $php_exe = $this->getPHPExecutable();
        
        if (empty($php_exe) || !file_exists($php_exe)) {
            return $this->colors['RED'] . "PHP executable not found." . $this->colors['RESET'];
        }

        // Get current version
        exec('"' . $php_exe . '" -v', $output, $return_var);
        if ($return_var !== 0 || empty($output)) {
            return $this->colors['RED'] . "Failed to get current PHP version." . $this->colors['RESET'];
        }

        preg_match('/PHP\s+(\d+\.\d+\.\d+)/', $output[0], $matches);
        $currentVersion = $matches[1] ?? 'unknown';

        $output = $this->colors['MAGENTA'] . "PHP Update Check" . $this->colors['RESET'] . PHP_EOL;
        $output .= "Current Version: " . $this->colors['GREEN'] . $currentVersion . $this->colors['RESET'] . PHP_EOL;
        $output .= $this->colors['YELLOW'] . "Note: Manual update required. Download from php.net" . $this->colors['RESET'] . PHP_EOL;
        $output .= $this->colors['CYAN'] . "Visit: https://www.php.net/downloads.php" . $this->colors['RESET'];

        return $output;
    }

    private function getPHPExecutable(): string
    {
        return $this->config['services']['PHP']['exe'] ?? 
               $this->config['paths']['php'] . DIRECTORY_SEPARATOR . 'php.exe' ?? 
               '';
    }

    public function getHelp(): string
    {
        return $this->colors['GREEN'] . "php version" . $this->colors['RESET'] . 
               " - Show detailed PHP version information" . PHP_EOL .
               $this->colors['GREEN'] . "php update" . $this->colors['RESET'] . 
               " - Check for PHP updates" . PHP_EOL .
               $this->colors['CYAN'] . "Usage: lns php version" . $this->colors['RESET'] . PHP_EOL .
               "Displays: Version, thread safety, compiler, architecture, and executable path";
    }
}