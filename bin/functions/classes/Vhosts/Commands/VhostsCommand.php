<?php
//-------------------------------------------------------------
// VhostsCommand.php - VirtualHosts management for LocNetServe
//-------------------------------------------------------------
// Provides commands to list and open Apache virtual hosts.
//
// Usage:
//   lns -vh show         → List all VirtualHosts
//   lns -vh open <name>  → Open specific vhost configuration
//
// Author : Sassi Souid
// Email   : locnetserve@gmail.com
// Project: LocNetServe
// Version: 1.0.0
//-------------------------------------------------------------

namespace Vhosts\Commands;

use Core\Commands\Command;

class VhostsCommand implements Command
{
    private array $colors;
    private array $config;
    private string $vhostsPath;

    /**
     * Constructor: Load global config and prepare vhosts path
     */
    public function __construct()
    {
        global $colors, $config;

        $this->colors = $colors;
        $this->config = $config;

        // Path to Apache vhosts configuration
        $this->vhostsPath = $config['paths']['apache'] .
            DIRECTORY_SEPARATOR . 'conf' .
            DIRECTORY_SEPARATOR . 'extra' .
            DIRECTORY_SEPARATOR . 'vhosts';
    }

    /**
     * Execute vhosts command
     *
     * @param string $action  Example: "open"
     * @param array  $args    Example: ["project"]
     * @return string
     */
    public function execute(string $action, array $args = []): string
    {
        
		switch ($action) {
            case 'open':
                return $this->openVhost($args[0]);

            case 'show':
            return $this->listVhosts();

			
			
            default:
                return $this->colors['RED'] . "Unknown vhosts action: $action" . $this->colors['RESET'];
        }

       
    }


/**
 * Show available vhosts from all .conf files in vhosts directory
 */
private function listVhosts(): string
{
    // Check if vhosts directory exists
    if (!is_dir($this->vhostsPath)) {
        return $this->colors['RED'] . 
            "VHosts directory not found: {$this->vhostsPath}" . 
            $this->colors['RESET'];
    }

    // Get all .conf files in vhosts directory
    $confFiles = glob($this->vhostsPath . DIRECTORY_SEPARATOR . '*.conf');
    
    // Count conf files
    $fileCount = count($confFiles);
    
    if ($fileCount === 0) {
        return $this->colors['YELLOW'] . 
            "No .conf files found in vhosts directory." . 
            $this->colors['RESET'];
    }

    $vhosts = [];
    
    // Process each .conf file
    foreach ($confFiles as $confFile) {
        $lines = @file($confFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) {
            continue; // Skip if file can't be read
        }

        $currentFileVhosts = [];
        foreach ($lines as $line) {
            // Look for ServerName directives
            if (preg_match('/ServerName\s+([^\s]+)/i', trim($line), $matches)) {
                $currentFileVhosts[] = $matches[1];
            }
        }
        
        // Add vhosts from this file to the main list
        $vhosts = array_merge($vhosts, $currentFileVhosts);
    }

    if (empty($vhosts)) {
        return $this->colors['YELLOW'] . 
            "No VirtualHosts found in {$fileCount} configuration file(s)." . 
            $this->colors['RESET'];
    }

    $output = $this->colors['MAGENTA'] . "Registered VirtualHosts " . 
              $this->colors['CYAN'] . "({$fileCount} config file(s)):" . 
              $this->colors['RESET'] . PHP_EOL;
    
    foreach ($vhosts as $host) {
        $output .= $this->colors['GREEN'] . " - " . $host . $this->colors['RESET'] . PHP_EOL;
    }

    return $output;
}

    /**
 * Open specific vhost definition and launch in browser
 */
private function openVhost(string $name): string
{
    // Check if file exists
    $configFile = $this->vhostsPath . DIRECTORY_SEPARATOR . $name . '.conf';
    
    if (!file_exists($configFile)) {
        return $this->colors['RED'] . 
            "VirtualHost configuration file not found: {$configFile}" . 
            $this->colors['RESET'];
    }

    $content = file_get_contents($configFile);
    if (!$content) {
        return $this->colors['RED'] . 
            "Error reading vhosts file: {$configFile}" . 
            $this->colors['RESET'];
    }

    // Extract ServerName from the configuration file
    if (preg_match('/ServerName\s+([^\s]+)/i', $content, $matches)) {
        $hostname = $matches[1];
        $url = "http://" . $hostname;
        
        // Try to open in default browser
        $this->openInBrowser($url);
        
        return $this->colors['GREEN'] . "Opening VirtualHost: " . 
               $this->colors['CYAN'] . $hostname . 
               $this->colors['GREEN'] . " in browser: " . 
               $this->colors['YELLOW'] . $url . 
               $this->colors['RESET'];
    }

    return $this->colors['RED'] . 
        "No ServerName found in VirtualHost configuration for '{$name}'" . 
        $this->colors['RESET'];
}

/**
 * Open URL in default browser (cross-platform)
 */
private function openInBrowser(string $url): void
{
    $url = escapeshellarg($url);
    
    exec('start "" "'.$url.'"');
}

    /**
     * Display Help information for this command
     */
    public function getHelp(): string

    {
        return $this->colors['YELLOW'] . "Usage examples:" . $this->colors['RESET'] . PHP_EOL .
            $this->colors['CYAN'] . "  lns -vh show" . $this->colors['RESET'] . "     → List all VirtualHosts" . PHP_EOL .
            $this->colors['CYAN'] . "  lns -vh open <vhost>" . $this->colors['RESET'] . "  → Open a specific VirtualHost" . PHP_EOL;
    }
}
