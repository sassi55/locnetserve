<?php
//-------------------------------------------------------------
// MySQLShell.php - MySQL Shell Service for LocNetServe
//-------------------------------------------------------------
// Handles MySQL interactive shell opening with proper connection settings.
// Launches MySQL client in a new terminal window.
//
// Author : Sassi Souid
// Project: LocNetServe
// Version: 1.0.0
//-------------------------------------------------------------

namespace Database\Services;

use Database\Formatters\DatabaseFormatter;

class MySQLShell
{
    private array $config;
    private DatabaseFormatter $formatter;
    private array $colors;

    /**
     * MySQLShell constructor.
     *
     * @param array $config Application configuration
     * @param DatabaseFormatter $formatter Output formatter
     */
    public function __construct(array $config, DatabaseFormatter $formatter)
    {
        $this->config = $config;
        $this->formatter = $formatter;
        $this->colors = $this->getDefaultColors();
    }

    /**
     * Get default colors array
     */
    private function getDefaultColors(): array
    {
        if (method_exists($this->formatter, 'getColors')) {
            return $this->formatter->getColors();
        }
        
        return [
            'RESET' => "\033[0m",
            'RED' => "\033[31m",
            'GREEN' => "\033[32m",
            'YELLOW' => "\033[33m",
            'BLUE' => "\033[34m",
            'MAGENTA' => "\033[35m",
            'CYAN' => "\033[36m",
            'WHITE' => "\033[37m"
        ];
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
            return $this->formatter->formatError("MySQL client not found: $mysql_exe");
        }

        // Connection details from config
        $host = $this->config['services']['MySQL']['host'] ?? '127.0.0.1';
        $port = $this->config['services']['MySQL']['port'] ?? 3306;
        $user = $this->config['services']['MySQL']['user'] ?? 'root';
        $pass = $this->config['services']['MySQL']['password'] ?? '';

        // Build command based on OS
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $cmd = $this->buildWindowsCommand($mysql_exe, $host, $port, $user, $pass);
        } else {
            $cmd = $this->buildUnixCommand($mysql_exe, $host, $port, $user, $pass);
        }

        exec($cmd, $output, $return_var);

        if ($return_var !== 0) {
            return $this->formatter->formatError("Failed to open MySQL shell. Return code: $return_var");
        }

        return $this->formatter->formatSuccess("MySQL shell opened (user: $user, host: $host:$port)");
    }

    /**
     * Build Windows command for MySQL shell
     */
    private function buildWindowsCommand(string $mysql_exe, string $host, int $port, string $user, string $pass): string
    {
        $connectionString = $this->buildConnectionString($host, $port, $user, $pass);
        
        // Windows command with proper escaping
        $cmd = 'start "MySQL Shell" cmd /K "' . $mysql_exe . ' ' . $connectionString . '"';
        
        return $cmd;
    }

    /**
     * Build Unix/Linux command for MySQL shell
     */
    private function buildUnixCommand(string $mysql_exe, string $host, int $port, string $user, string $pass): string
    {
        $connectionString = $this->buildConnectionString($host, $port, $user, $pass);
        
        // Try different terminal emulators
        $terminals = ['gnome-terminal', 'xterm', 'konsole', 'xfce4-terminal', 'terminator'];
        $terminal_cmd = '';
        
        foreach ($terminals as $terminal) {
            if ($this->commandExists($terminal)) {
                $terminal_cmd = $terminal;
                break;
            }
        }
        
        if (empty($terminal_cmd)) {
            $terminal_cmd = 'x-terminal-emulator'; // Fallback
        }
        
        // Build command for Unix systems
        $cmd = $terminal_cmd . ' -e "' . $mysql_exe . ' ' . $connectionString . '" &';
        
        return $cmd;
    }

    /**
     * Build MySQL connection string
     */
    private function buildConnectionString(string $host, int $port, string $user, string $pass): string
    {
        $connectionParams = [
            '-h ' . escapeshellarg($host),
            '-P ' . escapeshellarg($port),
            '-u ' . escapeshellarg($user)
        ];
        
        if (!empty($pass)) {
            $connectionParams[] = '-p' . escapeshellarg($pass);
        }
        
        return implode(' ', $connectionParams);
    }

    /**
     * Check if a command exists in the system
     */
    private function commandExists(string $command): bool
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            exec("where $command 2>nul", $output, $return_var);
        } else {
            exec("which $command 2>/dev/null", $output, $return_var);
        }
        
        return $return_var === 0;
    }

    /**
     * Get connection information for display
     */
    public function getConnectionInfo(): string
    {
        $host = $this->config['services']['MySQL']['host'] ?? '127.0.0.1';
        $port = $this->config['services']['MySQL']['port'] ?? 3306;
        $user = $this->config['services']['MySQL']['user'] ?? 'root';
        
        $output = $this->formatter->formatInfo("=== MySQL Connection Information ===") . PHP_EOL . PHP_EOL;
        $output .= $this->formatter->formatSuccess("▲ Connection Details:") . PHP_EOL;
        $output .= "  " . $this->formatter->formatInfo("• Host: ") . $this->colors['GREEN'] . $host . $this->colors['RESET'] . PHP_EOL;
        $output .= "  " . $this->formatter->formatInfo("• Port: ") . $this->colors['CYAN'] . $port . $this->colors['RESET'] . PHP_EOL;
        $output .= "  " . $this->formatter->formatInfo("• User: ") . $this->colors['YELLOW'] . $user . $this->colors['RESET'] . PHP_EOL;
        $output .= "  " . $this->formatter->formatInfo("• Connection: ") . $this->colors['WHITE'] . "$user@$host:$port" . $this->colors['RESET'] . PHP_EOL;
        
        $output .= PHP_EOL;
        $output .= $this->formatter->formatSuccess("▲ Available Commands:") . PHP_EOL;
        $output .= "  " . $this->formatter->formatInfo("• SHOW DATABASES;") . " - List all databases" . PHP_EOL;
        $output .= "  " . $this->formatter->formatInfo("• USE database_name;") . " - Switch to a database" . PHP_EOL;
        $output .= "  " . $this->formatter->formatInfo("• SHOW TABLES;") . " - List tables in current database" . PHP_EOL;
        $output .= "  " . $this->formatter->formatInfo("• EXIT or Ctrl+D") . " - Exit MySQL shell" . PHP_EOL;
        
        return $output;
    }

    /**
     * Test MySQL connection without opening shell
     */
    public function testConnection(): string
    {
        $mysql_bin = rtrim($this->config['paths']['mysql'] ?? '', DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'bin';
        $mysql_exe = $mysql_bin . DIRECTORY_SEPARATOR . 'mysql' . (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? '.exe' : '');

        if (!file_exists($mysql_exe)) {
            return $this->formatter->formatError("MySQL client not found: $mysql_exe");
        }

        $host = $this->config['services']['MySQL']['host'] ?? '127.0.0.1';
        $port = $this->config['services']['MySQL']['port'] ?? 3306;
        $user = $this->config['services']['MySQL']['user'] ?? 'root';
        $pass = $this->config['services']['MySQL']['password'] ?? '';

        // Test connection with a simple query
        $connectionString = $this->buildConnectionString($host, $port, $user, $pass);
        $cmd = '"' . $mysql_exe . '" ' . $connectionString . ' -e "SELECT 1 as test;" 2>&1';
        
        exec($cmd, $output, $return_var);

        if ($return_var === 0) {
            return $this->formatter->formatSuccess("MySQL connection successful (user: $user, host: $host:$port)");
        } else {
            $error = implode("\n", $output);
            return $this->formatter->formatError("MySQL connection failed: " . $error);
        }
    }

    /**
     * Execute a MySQL query and return results
     */
    public function executeQuery(string $query): string
    {
        $mysql_bin = rtrim($this->config['paths']['mysql'] ?? '', DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'bin';
        $mysql_exe = $mysql_bin . DIRECTORY_SEPARATOR . 'mysql' . (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? '.exe' : '');

        if (!file_exists($mysql_exe)) {
            return $this->formatter->formatError("MySQL client not found: $mysql_exe");
        }

        $host = $this->config['services']['MySQL']['host'] ?? '127.0.0.1';
        $port = $this->config['services']['MySQL']['port'] ?? 3306;
        $user = $this->config['services']['MySQL']['user'] ?? 'root';
        $pass = $this->config['services']['MySQL']['password'] ?? '';

        $connectionString = $this->buildConnectionString($host, $port, $user, $pass);
        $cmd = '"' . $mysql_exe . '" ' . $connectionString . ' -e "' . addslashes($query) . '" 2>&1';
        
        exec($cmd, $output, $return_var);

        if ($return_var === 0) {
            $result = implode("\n", $output);
            return $this->formatter->formatSuccess("Query executed successfully:") . PHP_EOL . $result;
        } else {
            $error = implode("\n", $output);
            return $this->formatter->formatError("Query failed: " . $error);
        }
    }

    /**
     * Get MySQL client version
     */
    public function getClientVersion(): string
    {
        $mysql_bin = rtrim($this->config['paths']['mysql'] ?? '', DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'bin';
        $mysql_exe = $mysql_bin . DIRECTORY_SEPARATOR . 'mysql' . (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? '.exe' : '');

        if (!file_exists($mysql_exe)) {
            return $this->formatter->formatError("MySQL client not found: $mysql_exe");
        }

        exec('"' . $mysql_exe . '" --version', $output, $return_var);

        if ($return_var === 0 && !empty($output)) {
            $versionString = trim($output[0]);
            $versionString = str_replace($mysql_exe, '', $versionString);
            $versionString = trim($versionString);
            
            return $this->formatter->formatSuccess("MySQL Client Version:") . PHP_EOL . $versionString;
        } else {
            return $this->formatter->formatError("Failed to get MySQL client version");
        }
    }
}