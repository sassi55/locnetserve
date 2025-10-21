<?php
/**
 * -------------------------------------------------------------
 *  MySQLShellCommand.php - MySQL Shell Command for LocNetServe
 * -------------------------------------------------------------
 *  Provides interactive MySQL shell access by:
 *    - Launching MySQL client with proper connection settings
 *    - Opening terminal sessions in new windows
 *    - Handling authentication and connection parameters
 *    - Supporting interactive database administration
 *
 *  Author : Sassi Souid
 *  Email  : locnetserve@gmail.com
 *  Project: LocNetServe
 *  Version: 1.0.0
 * -------------------------------------------------------------
 */

namespace Database\Commands;

use Core\Commands\Command;

class MySQLShellCommand implements Command
{
    private array $colors;
    private array $config;

    /**
     * MySQLShellCommand constructor.
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
     * Execute MySQL shell command.
     *
     * @param string $action The action to perform
     * @param array $args Command arguments
     * @return string Result message
     */
    public function execute(string $action, array $args = []): string
    {
        return $this->openShell();
    }

    /**
     * Open MySQL interactive shell in new terminal window.
     *
     * @return string Success or error message
     */
    public function openShell(): string
    {
        // MySQL executable path
        $mysql_bin = rtrim($this->config['paths']['mysql'] ?? '', DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'bin';
        $mysql_exe = $mysql_bin . DIRECTORY_SEPARATOR . 'mysql' . (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? '.exe' : '');

        if (!file_exists($mysql_exe)) {
            return $this->colors['RED'] . "MySQL client not found: $mysql_exe" . $this->colors['RESET'];
        }

        // Connection details from config
        $host = $this->config['services']['MySQL']['host'] ?? '127.0.0.1';
        $port = $this->config['services']['MySQL']['port'] ?? 3306;
        $user = $this->config['services']['MySQL']['user'] ?? 'root';
        $pass = $this->config['services']['MySQL']['password'] ?? '';

        // Build command based on OS
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows command
            $cmd = 'start cmd /K "' . $mysql_exe
                 . ' -h ' . escapeshellarg($host)
                 . ' -P ' . escapeshellarg($port)
                 . ' -u ' . escapeshellarg($user)
                 . ($pass !== '' ? ' -p' . escapeshellarg($pass) : '')
                 . '"';
        } else {
            // Linux/Unix command
            $cmd = 'x-terminal-emulator -e "' . $mysql_exe
                 . ' -h ' . escapeshellarg($host)
                 . ' -P ' . escapeshellarg($port)
                 . ' -u ' . escapeshellarg($user)
                 . ($pass !== '' ? ' -p' . escapeshellarg($pass) : '')
                 . '" &';
        }

        exec($cmd, $output, $return_var);

        if ($return_var !== 0) {
            return $this->colors['RED'] . "Failed to open MySQL shell. Return code: $return_var" . $this->colors['RESET'];
        }

        return $this->colors['GREEN'] . "MySQL shell opened (user: $user, host: $host:$port)" . $this->colors['RESET'];
    }

    /**
     * Get help information for shell command.
     *
     * @return string Help message
     */
    public function getHelp(): string
    {
        return $this->colors['GREEN'] . "mysql shell" . $this->colors['RESET'] . 
               " - Open MySQL interactive command line interface" . PHP_EOL .
               $this->colors['CYAN'] . "Usage: lns mysql shell" . $this->colors['RESET'] . PHP_EOL .
               "Opens a new terminal window with MySQL client connected to local server";
    }
}