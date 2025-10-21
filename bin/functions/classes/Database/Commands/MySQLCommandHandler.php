<?php
/**
 * -------------------------------------------------------------
 *  MySQLCommandHandler.php - MySQL Command Handler for LocNetServe
 * -------------------------------------------------------------
 *  Manages MySQL database operations and service control by:
 *    - Handling service status, ports, version, and health checks
 *    - Providing process monitoring and IP configuration
 *    - Routing commands to specialized sub-command handlers
 *    - Offering comprehensive MySQL management interface
 *
 *  Author : Sassi Souid
 *  Email  : locnetserve@gmail.com
 *  Project: LocNetServe
 *  Version: 1.0.0
 * -------------------------------------------------------------
 */
namespace Database\Commands;

use Core\Commands\Command;

class MySQLCommandHandler implements Command
{
    private array $colors;
    private array $config;
    private array $commands;

    public function __construct(array $colors, array $config)
    {
        $this->colors = $colors;
        $this->config = $config;
        $this->initializeCommands();
    }

    private function initializeCommands(): void
    {
        $this->commands = [
            'status' => new MySQLStatusCommand($this->colors, $this->config),
            'ports' => new MySQLPortCommand($this->colors, $this->config),
            'version' => new MySQLVersionCommand($this->colors, $this->config),
            'health' => new MySQLHealthCommand($this->colors, $this->config),
            // Add other MySQL commands...
        ];
    }

    public function execute(string $action, array $args = []): string
    {
        // Handle sub-commands
        if (isset($this->commands[$action])) {
            return $this->commands[$action]->execute($action, $args);
        }

        // Handle direct actions
        switch ($action) {
            case 'getpid':
                return $this->getMySQLPid();
            case 'getip':
                return $this->getMySQLIP();
            case 'ports':
                return $this->getMySQLPorts();
            case 'version':
                return $this->getMySQLVersion();
            default:
                return $this->colors['RED'] . "Unknown MySQL action: $action" . $this->colors['RESET'];
        }
    }

    private function getMySQLPid(): string
    {
        $statusCommand = new MySQLStatusCommand($this->colors, $this->config);
        $pid = $statusCommand->getPid();
        return $pid ? (string)$pid : 'N/A';
    }

    private function getMySQLIP(): string
    {
        return '127.0.0.1';
    }

    private function getMySQLPorts(): string
    {
        $statusCommand = new MySQLStatusCommand($this->colors, $this->config);
        $port = $statusCommand->getPort();
        return $port !== "Port not detected" ? $port : '3306';
    }

    private function getMySQLVersion(): string
    {
        $statusCommand = new MySQLStatusCommand($this->colors, $this->config);
        $version = $statusCommand->getVersion();
        return $version !== "Version not detected" ? $version : 'Unknown';
    }

    public function getHelp(): string
    {
        $help = $this->colors['GREEN'] . "MySQL Commands:" . $this->colors['RESET'] . PHP_EOL;
        
        $commands = [
            'status' => 'Check MySQL service status',
            'ports' => 'Show MySQL listening ports',
            'version' => 'Show MySQL version',
            'health' => 'Run MySQL health check',
            'getpid' => 'Get MySQL process ID',
            'getip' => 'Get MySQL IP address',
        ];
        
        foreach ($commands as $action => $description) {
            $help .= "  " . $this->colors['GREEN'] . "lns mysql $action" . $this->colors['RESET'] . 
                    " - $description" . PHP_EOL;
        }
        
        return $help;
    }
}