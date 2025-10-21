<?php
//================================================================================
// MySQLDatabaseCommand.php - MySQL Database Command Handler for LocNetServe
//================================================================================
// 
// ████████╗███████╗██████╗ ███╗   ███╗██╗   ██╗███╗   ██╗ █████╗ ██╗     
// ╚══██╔══╝██╔════╝██╔══██╗████╗ ████║██║   ██║████╗  ██║██╔══██╗██║     
//    ██║   █████╗  ██████╔╝██╔████╔██║██║   ██║██╔██╗ ██║███████║██║     
//    ██║   ██╔══╝  ██╔══██╗██║╚██╔╝██║██║   ██║██║╚██╗██║██╔══██║██║     
//    ██║   ███████╗██║  ██║██║ ╚═╝ ██║╚██████╔╝██║ ╚████║██║  ██║███████╗
//    ╚═╝   ╚══════╝╚═╝  ╚═╝╚═╝     ╚═╝ ╚═════╝ ╚═╝  ╚═══╝╚═╝  ╚═╝╚══════╝
//
// Handles comprehensive MySQL database management operations using specialized 
// services. Provides a clean and unified command interface for all MySQL-related
// operations including database management, user administration, backups, and 
// service monitoring.
//
// FEATURES:
// • Database creation, listing, and deletion
// • User management with secure validation
// • Database export/import functionality
// • Service status monitoring and health checks
// • Interactive MySQL shell access
// • Port and network configuration display
// • Version information and compatibility checks
//
// Author      : Sassi Souid
// Email       : locnetserve@gmail.com
// Project     : LocNetServe
// Version     : 1.0.0
// Created     : 2025
// Last Update : 2025
// License     : MIT
//================================================================================

namespace Database\Commands;

use Core\Commands\Command;
use Database\Formatters\DatabaseFormatter;
use Database\Services\DatabaseLister;
use Database\Services\DatabaseCreator;
use Database\Services\DatabaseDropper;
use Database\Services\UserLister;
use Database\Services\PortLister;
use Database\Services\MySQLStatus;
use Database\Services\MySQLShell;
use Database\Services\MySQLVersion;
use Database\Services\MySQLUser;
use Database\Services\MySQLDump;

class MySQLDatabaseCommand implements Command
{
    private array $colors;
    private array $config;
    private DatabaseFormatter $formatter;
    private DatabaseLister $databaseLister;
    private DatabaseCreator $databaseCreator;
    private DatabaseDropper $databaseDropper;
    private UserLister $userLister;
    private PortLister $portLister;
    private MySQLStatus $mysqlStatus;
    private MySQLShell $mysqlShell;
    private MySQLVersion $mysqlVersion;
    private MySQLUser $mysqlUser;
    private MySQLDump $mysqlDump;

    /**
     * MySQLDatabaseCommand constructor.
     *
     * @param array $colors Color configuration for CLI output
     * @param array $config Application configuration
     */
    public function __construct(array $colors, array $config)
    {
        $this->colors = $colors;
        $this->config = $config;
        $this->formatter = new DatabaseFormatter($colors);
        $this->databaseLister = new DatabaseLister($config, $this->formatter);
        $this->databaseCreator = new DatabaseCreator($config, $this->formatter, $this->databaseLister);
        $this->databaseDropper = new DatabaseDropper($config, $this->formatter, $this->databaseLister);
        $this->userLister = new UserLister($config, $this->formatter,$this->colors);
        $this->portLister = new PortLister($config, $this->formatter);
        $this->mysqlStatus = new MySQLStatus($config, $this->formatter);
        $this->mysqlShell = new MySQLShell($config, $this->formatter);
        $this->mysqlVersion = new MySQLVersion($config, $this->formatter,$this->colors);
        $this->mysqlUser = new MySQLUser($config, $this->formatter,$this->colors);
        $this->mysqlDump = new MySQLDump($config, $this->formatter,$this->colors);
    }

    /**
     * Execute MySQL database command.
     *
     * @param string $action The action to perform (list/create/drop)
     * @param array $args Command arguments
     * @return string Result message
     */
    public function execute(string $action, array $args = []): string
    {
        // Vérifier si MySQL est en cours d'exécution pour les commandes qui le nécessitent
        if (in_array($action, ["import", "create", "drop", "data", "export", "user" ]) && !$this->check_mysql()) {
            return $this->formatter->formatError("Error. MySQL is not running yet. Use: lns mysql start");
        }
       
        switch ($action) {
            case 'version':
                return $this->mysqlVersion->getVersion();
                
            case 'users':
                if (empty($args)) {
                    return $this->userLister->listUsers();
                } elseif (!empty($args) && !in_array($args[0], ["info"])) {
                    return $this->formatter->formatError("Error. Use: lns mysql users info <username>");
                } elseif (!empty($args) && in_array($args[0], ["info"]) && !isset($args[1])) {
                    return $this->formatter->formatError("Error. Use: lns mysql users info <username>");
                } elseif (!empty($args) && in_array($args[0], ["info"]) && isset($args[1])) {
                    return $this->userLister->getUserInfo($args[1]);
                }
                break;
            
            case 'user':
			
			
			   switch ($args[0]) {
				   
				   case 'add':
					  $host = $args[3] ?? '%';
					   return $this->mysqlUser->addUser($args[1], $args[2], $host);
				
				   break;
				   
				   case 'del':
					   $host = '%';
					   return $this->mysqlUser->removeUser($args[1], $host);
				   
				   break;
				   
				   
				   
			   }
                
                 break;
             case 'export':
	            $outputFile = $args[1] ?? '';
				return $this->mysqlDump->exportDatabase($args[0], $outputFile);
	         break;
            case 'ports':
                return $this->portLister->getPortInfo();
                
            case 'shell':
                return $this->mysqlShell->openShell();
                
            case 'status':
                return $this->mysqlStatus->getStatus();
                
            case 'health':
                return $this->mysqlStatus->getHealthStatus();
                
            case 'data':
                if (!empty($args) && !in_array($args[0], ["list"])) {
                    return $this->formatter->formatError("Error: Use: lns mysql data list");
                }
                return $this->databaseLister->listDatabases();

            case 'create':
                if (empty($args)) {
                    return $this->formatter->formatError("Missing database name. Use: lns mysql create <db_name>");
                }
                return $this->databaseCreator->createDatabase($args[0]);

            case 'drop':
                if (empty($args)) {
                    return $this->formatter->formatError("Missing database name. Use: lns mysql drop <db_name>");
                }
                return $this->databaseDropper->dropDatabase($args[0]);

            default:
                return $this->formatter->formatError("Unknown database action: $action") . PHP_EOL . $this->getHelp();
        }
        
        return $this->formatter->formatError("Unexpected error processing command: $action");
    }
   
    /**
     * Check if MySQL service is running
     */
    public function check_mysql(): bool
    {
        return $this->mysqlStatus->isRunning();
    }

    /**
     * Get help information for database commands.
     *
     * @return string Help message
     */
    public function getHelp(): string
    {
        return $this->colors['GREEN'] . "mysql data list" . $this->colors['RESET'] . 
               " - List all MySQL databases" . PHP_EOL .
               $this->colors['GREEN'] . "mysql create <name>" . $this->colors['RESET'] . 
               " - Create a new database" . PHP_EOL .
               $this->colors['GREEN'] . "mysql drop <name>" . $this->colors['RESET'] . 
               " - Drop a database" . PHP_EOL .
               $this->colors['GREEN'] . "mysql users" . $this->colors['RESET'] . 
               " - List MySQL users" . PHP_EOL .
               $this->colors['GREEN'] . "mysql users info <username>" . $this->colors['RESET'] . 
               " - Show user information" . PHP_EOL .
               $this->colors['GREEN'] . "mysql ports" . $this->colors['RESET'] . 
               " - Show port information" . PHP_EOL .
               $this->colors['GREEN'] . "mysql shell" . $this->colors['RESET'] . 
               " - Open MySQL shell" . PHP_EOL .
               $this->colors['GREEN'] . "mysql status" . $this->colors['RESET'] . 
               " - Check MySQL status" . PHP_EOL .
               $this->colors['GREEN'] . "mysql health" . $this->colors['RESET'] . 
               " - Check MySQL health" . PHP_EOL .
               $this->colors['GREEN'] . "mysql version" . $this->colors['RESET'] . 
               " - Show MySQL version" . PHP_EOL .
               $this->colors['CYAN'] . "Usage examples:" . $this->colors['RESET'] . PHP_EOL .
               "  lns mysql data list" . PHP_EOL .
               "  lns mysql create my_new_database" . PHP_EOL .
               "  lns mysql drop old_database" . PHP_EOL .
               "  lns mysql users" . PHP_EOL .
               "  lns mysql users info root" . PHP_EOL .
               "  lns mysql ports" . PHP_EOL .
               "  lns mysql shell" . PHP_EOL .
               "  lns mysql status" . PHP_EOL .
               "  lns mysql version" . PHP_EOL .
               $this->colors['YELLOW'] . "Note: Database names can contain letters, numbers, underscore and dash." . $this->colors['RESET'];
    }
}