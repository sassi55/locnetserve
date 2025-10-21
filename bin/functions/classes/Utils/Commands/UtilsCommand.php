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
use Utils\Commands\UtilsBackupCommand;
use Utils\Commands\UtilsOpenCommand;
use Utils\Commands\UtilsSSLCommand;
use Utils\Commands\UtilsShowCommand;

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

            case 'show':
            return $this->handleShow($args);

			case 'config':
            return $this->handleOpen($args);
			
            default:
                return $this->colors['RED'] . "Unknown utils action: $action" . $this->colors['RESET'];
        }
    }

    /**
     * Handle "open" sub-commands: www, localhost, dashboard
     */
	private function handleOpen(array $args): string
	{
		
		
		// First argument = open target (www, localhost, dashboard, config)
		$subAction = $args[0] ?? '';
		$subArgs = array_slice($args, 1);

		$openHandler = new UtilsOpenCommand($this->colors, $this->config);
		return $openHandler->execute($subAction, $subArgs);
	}


    /**
     * Handle "backup" sub-commands
     */
	private function handleBackup(array $args): string
	{
		// First argument = backup action (all, mysql, list, restore, etc.)
		$subAction = $args[0] ?? '';
		$subArgs = array_slice($args, 1);

		$backup = new UtilsBackupCommand($this->colors, $this->config);
		return $backup->execute($subAction, $subArgs);
	}


    /**
     * Handle SSL generation command
     */
    private function handleSSL(array $args): string
	{
		$subAction = $args[0] ?? '';
		$subArgs = array_slice($args, 1);

		$sslHandler = new UtilsSSLCommand($this->colors, $this->config);
		return $sslHandler->execute($subAction, $subArgs);
	}

	/**
	 * Handle "show" sub-commands using UtilsShowCommand
	 */
	private function handleShow(array $args): string
	{
		// First argument = show action (ports, config, services)
		$subAction = $args[0] ?? '';
		$subArgs = array_slice($args, 1);

		$showHandler = new UtilsShowCommand($this->colors, $this->config);
		return $showHandler->execute($subAction, $subArgs);
	}
    
	
	/**
	 * Handle "config" sub-commands: edit, show, etc.
	 */
	private function handleConfig(array $args): string
	{
		$subAction = $args[0] ?? '';
		
		switch ($subAction) {
			case 'edit':
				return $this->editConfig();
				
			default:
				return $this->colors['RED'] . "Unknown config command: $subAction" . $this->colors['RESET'] . PHP_EOL .
					   "Available: edit" . PHP_EOL;
		}
	}
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
	  -u show ports          Show all active network ports
	  -u config edit         Edit configuration file

	HELP;
	}
}
