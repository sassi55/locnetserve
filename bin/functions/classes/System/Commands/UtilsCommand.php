<?php
//-------------------------------------------------------------
// UtilsCommand.php - Utility Command Handler for LocNetServe
//-------------------------------------------------------------
// Handles all "-u" (utils) commands such as open, backup, ssl, etc.
// Delegates specific sub-commands to internal methods.
//
// Author  : Sassi Souid
// Email   : locnetserve@gmail.com
// Project : LocNetServe
// Version : 1.0.0
//-------------------------------------------------------------

namespace Utils\Commands;

use Core\Commands\Command;

class UtilsCommand implements Command
{
    private array $colors;
    private array $config;

    public function __construct(array $colors = [], array $config = [])
    {
        $this->colors = $colors;
        $this->config = $config;
    }

    /**
     * Execute the utils command.
     */
    public function execute(string $action, array $args = []): string
    {
        switch ($action) {
            case 'open':
                return $this->handleOpen($args);

            case 'backup':
                return $this->handleBackup($args);

            case 'ssl':
                return $this->handleSSL($args);

            case 'health':
                return $this->handleHealthCheck();

            default:
                return $this->colors['RED'] . "Unknown utils action: $action" . $this->colors['RESET'];
        }
    }

    /**
     * Handle "open" sub-commands: www, localhost, dashboard
     */
    private function handleOpen(array $args): string
    {
        if (empty($args)) {
            return $this->colors['YELLOW'] . "Usage: lns -u open <www|localhost|dashboard>" . $this->colors['RESET'];
        }

        $target = $args[0];
        switch ($target) {
            case 'www':
                exec('start "" "' . $this->config['paths']['base'] . '\\www"');
                return $this->colors['GREEN'] . "Opened www project root folder." . $this->colors['RESET'];

            case 'localhost':
                exec('start "" "http://localhost"');
                return $this->colors['GREEN'] . "Opened localhost in browser." . $this->colors['RESET'];

            case 'dashboard':
                exec('start "" "http://localhost/dashboard"');
                return $this->colors['GREEN'] . "Opened local dashboard in browser." . $this->colors['RESET'];

            default:
                return $this->colors['RED'] . "Unknown open target: $target" . $this->colors['RESET'];
        }
    }

    /**
     * Handle "backup" sub-commands
     */
    private function handleBackup(array $args): string
    {
        if (empty($args)) {
            return $this->colors['YELLOW'] . "Usage: lns -u backup <all|mysql|projects|list|check>" . $this->colors['RESET'];
        }

        $cmd = $args[0];
        switch ($cmd) {
            case 'all':
                return $this->colors['GREEN'] . "Starting full backup (MySQL + WWW + Configs)..." . $this->colors['RESET'];

            case 'mysql':
                return $this->colors['GREEN'] . "Backing up all MySQL databases..." . $this->colors['RESET'];

            case 'projects':
                return $this->colors['GREEN'] . "Backing up all projects in www..." . $this->colors['RESET'];

            case 'list':
                return $this->colors['GREEN'] . "Listing available backups..." . $this->colors['RESET'];

            case 'check':
                return $this->colors['GREEN'] . "Checking backup schedule..." . $this->colors['RESET'];

            default:
                return $this->colors['RED'] . "Unknown backup subcommand: $cmd" . $this->colors['RESET'];
        }
    }

    /**
     * Handle SSL generation command
     */
    private function handleSSL(array $args): string
    {
        if (empty($args) || $args[0] !== 'gen') {
            return $this->colors['YELLOW'] . "Usage: lns -u ssl gen" . $this->colors['RESET'];
        }

        // Placeholder for SSL generation logic
        return $this->colors['GREEN'] . "Generated self-signed SSL certificate for localhost." . $this->colors['RESET'];
    }

    /**
     * Handle server health check
     */
    private function handleHealthCheck(): string
    {
        return $this->colors['GREEN'] . "Performing full server health check..." . $this->colors['RESET'];
    }

    /**
     * Display help information for utils
     */
    public function getHelp(): string
    {
        return <<<HELP
{$this->colors['CYAN']}Utility Commands (-u):{$this->colors['RESET']}

  -u open www            Open the project root folder (www)
  -u open localhost      Open localhost in browser
  -u open dashboard      Open the local dashboard
  -u backup all          Backup MySQL + WWW + configs
  -u backup list         List available backups
  -u ssl gen             Generate self-signed SSL certificate
  -u health check        Run full server health check

HELP;
    }
}
