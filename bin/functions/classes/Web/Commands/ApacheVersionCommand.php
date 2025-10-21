<?php
//-------------------------------------------------------------
// ApacheVersionCommand.php - Apache Version Command for LocNetServe
//-------------------------------------------------------------
// Handles Apache version detection and display.
// Provides version information from Apache executable.
//
// Author : Sassi Souid
// Project: LocNetServe
// Version: 1.0.0
//-------------------------------------------------------------

namespace Web\Commands;

use Core\Commands\Command;

class ApacheVersionCommand implements Command
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
        return $this->getVersion();
    }

    public function getVersion(): string
    {
        $httpd = $this->config['services']['Apache']['exe'] ?? '';
        
        if (empty($httpd) || !file_exists($httpd)) {
            return $this->colors['RED'] . "Apache executable not found or not configured." . $this->colors['RESET'];
        }

        $output = [];
        exec('"' . $httpd . '" -v', $output, $return_var);

        if ($return_var !== 0 || empty($output)) {
            return $this->colors['RED'] . "Failed to get Apache version." . $this->colors['RESET'];
        }

        $versionString = "";
        foreach ($output as $line) {
            if (preg_match('/Server version:\s*(.+)/i', $line, $matches)) {
                $versionString = trim($matches[1]);
                break;
            }
        }

        if (empty($versionString)) {
            $versionString = trim($output[0] ?? "Version information not available");
        }

        $output = $this->colors['GREEN'] . "Apache Version:" . $this->colors['RESET'] . PHP_EOL;
        $output .= $this->colors['CYAN'] . $versionString . $this->colors['RESET'] . PHP_EOL;
        
        // Extract version number for additional info
        if (preg_match('/Apache\/(\d+\.\d+\.\d+)/', $versionString, $matches)) {
            $versionNumber = $matches[1];
            $output .= $this->colors['YELLOW'] . "Version: $versionNumber" . $this->colors['RESET'] . PHP_EOL;
        }

        $output .= $this->colors['WHITE'] . "Executable: " . $httpd . $this->colors['RESET'];

        return $output;
    }

    public function getHelp(): string
    {
        return $this->colors['GREEN'] . "apache version" . $this->colors['RESET'] . 
               " - Display Apache server version information" . PHP_EOL .
               $this->colors['CYAN'] . "Usage: lns apache version" . $this->colors['RESET'] . PHP_EOL .
               "Shows detailed version information from Apache executable";
    }
}