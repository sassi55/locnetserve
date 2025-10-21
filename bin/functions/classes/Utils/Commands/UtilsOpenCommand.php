<?php
//-------------------------------------------------------------
// UtilsOpenCommand.php - Open Command Handler for LocNetServe
//-------------------------------------------------------------
// Handles "-u open ..." and "-u config edit" commands.
// Opens folders, local URLs, and configuration files.
//
// Author  : Sassi Souid
// Email   : locnetserve@gmail.com
// Project : LocNetServe
// Version : 1.0.0
//-------------------------------------------------------------

namespace Utils\Commands;

use Core\Commands\Command;

class UtilsOpenCommand implements Command
{
    private array $colors;
    private array $config;

    public function __construct(array $colors = [], array $config = [])
    {
        $this->colors = $colors;
        $this->config = $config;
    }

    /**
     * Execute open-related commands.
     */
    public function execute(string $action, array $args = []): string
    {
        
		
		switch ($action) {
            case 'www':
                return $this->openWWW();

            case 'localhost':
                return $this->openLocalhost();

            case 'dashboard':
                return $this->openDashboard();

            case 'edit':
                return $this->openConfig();

            default:
                return $this->colors['RED'] . "Unknown open command: $action" . $this->colors['RESET'];
        }
    }

    /**
     * Open the www root folder in Windows Explorer.
     */
    private function openWWW(): string
    {
        $path = $this->config['paths']['base'] . DIRECTORY_SEPARATOR . 'www';
        if (!is_dir($path)) {
            return $this->colors['RED'] . "WWW folder not found at: $path" . $this->colors['RESET'];
        }
        exec('start "" "' . $path . '"');
        return $this->colors['GREEN'] . "Opened www project root folder." . $this->colors['RESET'];
    }

    /**
     * Open http://localhost in browser.
     */
    private function openLocalhost(): string
    {
        exec('start "" "http://localhost"');
        return $this->colors['GREEN'] . "Opened localhost in browser." . $this->colors['RESET'];
    }

    /**
     * Open http://localhost/dashboard in browser.
     */
    private function openDashboard(): string
    {
        exec('start "" "http://localhost/dashboard"');
        return $this->colors['GREEN'] . "Opened local dashboard in browser." . $this->colors['RESET'];
    }

    /**
     * Handle -u config edit
     */
    private function openConfig(): string
    {
        
		
		

        $file = $this->config['paths']['base'] . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.json';

        if (!file_exists($file)) {
            return $this->colors['RED'] . "Configuration file not found: $file" . $this->colors['RESET'];
        }

        exec('start "" "' . $file . '"');
        return $this->colors['GREEN'] . "Opened config.json for editing." . $this->colors['RESET'];
    }

    /**
     * Display help for open commands.
     */
    public function getHelp(): string
    {
        return <<<HELP
{$this->colors['CYAN']}Open Commands (-u open):{$this->colors['RESET']}

  -u open www           Open the project root folder (www)
  -u open localhost     Open localhost in browser
  -u open dashboard     Open the local dashboard
  -u config edit        Open config.json for editing
HELP;
    }
}
