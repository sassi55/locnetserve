<?php
/**
 * -------------------------------------------------------------
 *  MySQLVersionCommand.php - MySQL Version Command for LocNetServe
 * -------------------------------------------------------------
 *  Retrieves and displays MySQL version information by:
 *    - Detecting MySQL executable version and build details
 *    - Providing version compatibility information
 *    - Supporting multiple version detection methods
 *    - Displaying comprehensive version metadata
 *
 *  Author : Sassi Souid
 *  Email  : locnetserve@gmail.com
 *  Project: LocNetServe
 *  Version: 1.0.0
 * -------------------------------------------------------------
 */

namespace Database\Commands;

use Core\Commands\Command;

class MySQLVersionCommand implements Command
{
    private array $colors;
    private array $config;

    /**
     * MySQLVersionCommand constructor.
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
     * Execute MySQL version command.
     *
     * @param string $action The action to perform
     * @param array $args Command arguments
     * @return string Version information
     */
    public function execute(string $action, array $args = []): string
    {
        return $this->getVersion();
    }

    /**
     * Get MySQL version information.
     *
     * @return string Formatted version information
     */
    public function getVersion(): string
    {
        $mysqld = $this->config['services']['MySQL']['exe'] ?? '';
        
        if (empty($mysqld) || !file_exists($mysqld)) {
            return $this->colors['RED'] . "MySQL executable not found or not configured." . $this->colors['RESET'];
        }

        $output = [];
        exec('"' . $mysqld . '" --version', $output, $return_var);

        if ($return_var !== 0 || empty($output)) {
            return $this->colors['RED'] . "Failed to get MySQL version." . $this->colors['RESET'];
        }

        $versionString = trim($output[0]);
        
        // Clean up the version string (remove executable path if present)
        $versionString = str_replace($mysqld, '', $versionString);
        $versionString = trim($versionString);

        $output = $this->colors['GREEN'] . "MySQL Version:" . $this->colors['RESET'] . PHP_EOL;
        $output .= $this->colors['CYAN'] . $versionString . $this->colors['RESET'] . PHP_EOL;
        
        // Extract version number for additional info
        if (preg_match('/(\d+\.\d+\.\d+)/', $versionString, $matches)) {
            $versionNumber = $matches[1];
            $output .= $this->colors['YELLOW'] . "Version: $versionNumber" . $this->colors['RESET'] . PHP_EOL;
            
            // Add some helpful info based on version
            $output .= $this->colors['WHITE'] . "Executable: " . $mysqld . $this->colors['RESET'];
        }

        return $output;
    }

    /**
     * Get help information for version command.
     *
     * @return string Help message
     */
    public function getHelp(): string
    {
        return $this->colors['GREEN'] . "mysql version" . $this->colors['RESET'] . 
               " - Display MySQL server version information" . PHP_EOL .
               $this->colors['CYAN'] . "Usage: lns mysql version" . $this->colors['RESET'] . PHP_EOL .
               "Shows detailed version information from MySQL executable";
    }
}