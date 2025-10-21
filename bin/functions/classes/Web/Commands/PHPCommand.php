<?php
//-------------------------------------------------------------
// PHPCommand.php - PHP Command Handler for LocNetServe
//-------------------------------------------------------------
// Routes PHP commands to appropriate specialized command classes.
// Provides unified interface for all PHP-related operations.
//
// Author : Sassi Souid
// Email  : locnetserve@gmail.com
// Project: LocNetServe
// Version: 1.0.0
//-------------------------------------------------------------

namespace Web\Commands;

use Core\Commands\Command;
use Web\Commands\PHPVersionCommand;
use Web\Commands\PHPInfoCommand;
use Web\Commands\PHPConfigCommand;
use Web\Commands\PHPExtensionCommand;
use Web\Commands\PHPComposerCommand;
use Web\Commands\PhpUpdateChecker;

class PHPCommand implements Command
{
    private array $colors;
    private array $config;

    /**
     * PHPCommand constructor.
     *
     * @param array $colors Color configuration for CLI output
     * @param array $config Application configuration
     */
    public function __construct(array $colors, array $config)
    {
        $this->colors = $colors;
        $this->config = $config;
    }

    /**
     * Execute PHP command by routing to appropriate specialized command class.
     *
     * @param string $action The PHP action to execute
     * @param array $args Additional arguments for the command
     * @return string The result message from command execution
     */
    public function execute(string $action, array $args = []): string
    {
        // Remove any empty arguments
        $args = array_filter($args, function($value) {
            return $value !== null && $value !== '';
        });

        try {
            switch (strtolower($action)) {
                // Version and info commands
                case 'version':
                    $command = new PHPVersionCommand($this->colors, $this->config);
                    return $command->execute($action, $args);

                case 'info':
                    $command = new PHPInfoCommand($this->colors, $this->config);
                    return $command->execute($action, $args);

                // Configuration commands
                case 'ini':
                    $command = new PHPConfigCommand($this->colors, $this->config);
                    return $command->execute('ini', $args);

                case 'update':
                    $command = new PhpUpdateChecker($this->colors, $this->config);
                    return $command->execute('update', $args);

                // Extension management
                case 'ext':
                    if (empty($args)) {
                        return $this->colors['RED'] . "Error: Missing extension sub-command. Use: lns php ext list|enable|disable|dispo" . $this->colors['RESET'];
                    }
                    
                    $subAction = strtolower($args[0]);
                    $command = new PHPExtensionCommand($this->colors, $this->config);
                    
                    switch ($subAction) {
                        case 'list':
                            return $command->execute('list', array_slice($args, 1));
                        case 'enable':
                            return $command->execute('enable', array_slice($args, 1));
                        case 'disable':
                            return $command->execute('disable', array_slice($args, 1));
                        case 'available':
                            return $command->execute('available', array_slice($args, 1));
                        default:
                            return $this->colors['RED'] . "Unknown extension action: $subAction" . $this->colors['RESET'];
                    }

                // Composer commands
                case 'composer':
                    if (empty($args)) {
                        return $this->colors['RED'] . "Error: Missing composer command. Use: lns php composer <install|update|etc>" . $this->colors['RESET'];
                    }
                    $command = new PHPComposerCommand($this->colors, $this->config);
                    return $command->execute($args[0], array_slice($args, 1));

                // Update commands
                case 'update':
                    $command = new PHPVersionCommand($this->colors, $this->config);
                    return $command->execute('update', $args);

                default:
                    return $this->colors['RED'] . "Unknown PHP action: $action" . $this->colors['RESET'];
            }
        } catch (\Exception $e) {
            return $this->colors['RED'] . "Error executing PHP command: " . $e->getMessage() . $this->colors['RESET'];
        }
    }

    /**
     * Get help information for PHP commands.
     *
     * @return string Help message describing available PHP commands
     */
    public function getHelp(): string
    {
        $help = $this->colors['MAGENTA'] . "=== PHP Commands ===" . $this->colors['RESET'] . PHP_EOL;
        
        $commands = [
            'version' => 'Show PHP version information',
            'info' => 'Open phpinfo() in browser',
            'ini' => 'Open php.ini configuration file',
            'ext list' => 'List all PHP extensions with status',
            'ext enable <ext>' => 'Enable a PHP extension',
            'ext disable <ext>' => 'Disable a PHP extension',
            'ext available' => 'List available disabled extensions',
            'update' => 'Check for PHP updates',
            'composer <cmd>' => 'Run composer command (install, update, etc.)'
        ];

        foreach ($commands as $cmd => $desc) {
            $help .= $this->colors['GREEN'] . "php $cmd" . $this->colors['RESET'] . " - $desc" . PHP_EOL;
        }

        $help .= PHP_EOL . $this->colors['CYAN'] . "Usage examples:" . $this->colors['RESET'] . PHP_EOL;
        $help .= "  lns php version" . PHP_EOL;
        $help .= "  lns php ext list" . PHP_EOL;
        $help .= "  lns php ext enable curl" . PHP_EOL;
        $help .= "  lns php composer install" . PHP_EOL;

        return $help;
    }
}