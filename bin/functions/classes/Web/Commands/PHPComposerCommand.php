<?php
//-------------------------------------------------------------
// PHPComposerCommand.php - PHP Composer Command for LocNetServe
//-------------------------------------------------------------
// Handles Composer package manager operations.
// Provides Composer command execution with proper PHP context.
//
// Author : Sassi Souid
// Project: LocNetServe
// Version: 1.0.0
//-------------------------------------------------------------

namespace Web\Commands;

use Core\Commands\Command;

class PHPComposerCommand implements Command
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
        return $this->runComposerCommand($action, $args);
    }

    public function runComposerCommand(string $command, array $args = []): string
    {
        $php_exe = $this->getPHPExecutable();
        $composer_phar = $this->findComposer();

        if (empty($php_exe) || !file_exists($php_exe)) {
            return $this->colors['RED'] . "PHP executable not found." . $this->colors['RESET'];
        }

        if (empty($composer_phar)) {
            return $this->colors['RED'] . "Composer not found. Please install Composer first." . $this->colors['RESET'] . PHP_EOL .
                   $this->colors['CYAN'] . "Download from: https://getcomposer.org/download/" . $this->colors['RESET'];
        }

        // Build composer command
        $cmd_args = escapeshellarg($composer_phar) . " " . $command;
        
        if (!empty($args)) {
            $cmd_args .= " " . implode(" ", array_map('escapeshellarg', $args));
        }

        $full_cmd = '"' . $php_exe . '" ' . $cmd_args . ' 2>&1';

        // Execute composer command
        exec($full_cmd, $output, $return_var);

        $result = $this->colors['GREEN'] . "Composer $command executed:" . $this->colors['RESET'] . PHP_EOL;
        $result .= $this->colors['CYAN'] . "Command: php " . $cmd_args . $this->colors['RESET'] . PHP_EOL;
        $result .= $this->colors['CYAN'] . "Return code: $return_var" . $this->colors['RESET'] . PHP_EOL . PHP_EOL;

        if (!empty($output)) {
            $result .= implode(PHP_EOL, $output);
        }

        return $result;
    }

    private function findComposer(): string
    {
        // Check common composer locations
        $possible_locations = [
            $this->config['paths']['php'] . DIRECTORY_SEPARATOR . 'composer.phar',
            $this->config['paths']['base'] . DIRECTORY_SEPARATOR . 'composer.phar',
            'composer.phar',
            'composer' // Global composer
        ];

        foreach ($possible_locations as $location) {
            if (file_exists($location)) {
                return $location;
            }
        }

        // Check if composer is in PATH
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            exec('where composer 2>nul', $output, $return_var);
        } else {
            exec('which composer 2>/dev/null', $output, $return_var);
        }

        if ($return_var === 0 && !empty($output)) {
            return trim($output[0]);
        }

        return '';
    }

    public function checkComposer(): string
    {
        $composer_phar = $this->findComposer();

        if (empty($composer_phar)) {
            return $this->colors['RED'] . "Composer not found." . $this->colors['RESET'] . PHP_EOL .
                   $this->colors['YELLOW'] . "Install Composer from: https://getcomposer.org/download/" . $this->colors['RESET'];
        }

        $php_exe = $this->getPHPExecutable();
        exec('"' . $php_exe . '" ' . escapeshellarg($composer_phar) . ' --version 2>&1', $output, $return_var);

        if ($return_var === 0 && !empty($output)) {
            $version = trim($output[0]);
            return $this->colors['GREEN'] . "Composer found:" . $this->colors['RESET'] . PHP_EOL .
                   $this->colors['CYAN'] . "Location: $composer_phar" . $this->colors['RESET'] . PHP_EOL .
                   $this->colors['CYAN'] . "Version: $version" . $this->colors['RESET'];
        }

        return $this->colors['RED'] . "Composer found but not working properly." . $this->colors['RESET'];
    }

    private function getPHPExecutable(): string
    {
        return $this->config['services']['PHP']['exe'] ?? 
               $this->config['paths']['php'] . DIRECTORY_SEPARATOR . 'php.exe' ?? 
               '';
    }

    public function getHelp(): string
    {
        return $this->colors['GREEN'] . "php composer <command>" . $this->colors['RESET'] . 
               " - Run Composer command with current PHP" . PHP_EOL .
               $this->colors['CYAN'] . "Common Composer commands:" . $this->colors['RESET'] . PHP_EOL .
               "  install    - Install dependencies from composer.json" . PHP_EOL .
               "  update     - Update dependencies to latest versions" . PHP_EOL .
               "  require    - Add new dependency" . PHP_EOL .
               "  remove     - Remove dependency" . PHP_EOL .
               "  dump-autoload - Regenerate autoloader" . PHP_EOL . PHP_EOL .
               $this->colors['YELLOW'] . "Usage examples:" . $this->colors['RESET'] . PHP_EOL .
               "  lns php composer install" . PHP_EOL .
               "  lns php composer update" . PHP_EOL .
               "  lns php composer require monolog/monolog" . PHP_EOL .
               $this->colors['YELLOW'] . "Note: Uses the PHP executable configured in LocNetServe" . $this->colors['RESET'];
    }
}