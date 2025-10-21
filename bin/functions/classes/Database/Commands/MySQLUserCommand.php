<?php
/**
 * -------------------------------------------------------------
 *  MySQLUserCommand.php - MySQL User Command for LocNetServe
 * -------------------------------------------------------------
 *  Manages MySQL user accounts and permissions by:
 *    - Creating and deleting user accounts with secure validation
 *    - Handling password management and authentication
 *    - Managing user privileges and access controls
 *    - Providing secure user administration interface
 *
 *  Author : Sassi Souid
 *  Email  : locnetserve@gmail.com
 *  Project: LocNetServe
 *  Version: 1.0.0
 * -------------------------------------------------------------
 */

namespace Database\Commands;

use Core\Commands\Command;

class MySQLUserCommand implements Command
{
    private array $colors;
    private array $config;

    /**
     * MySQLUserCommand constructor.
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
     * Execute MySQL user command.
     *
     * @param string $action The action to perform (list/add/del)
     * @param array $args Command arguments
     * @return string Result message
     */
    public function execute(string $action, array $args = []): string
    {
        switch ($action) {
            case 'list':
                return $this->listUsers();

            case 'add':
                if (count($args) < 2) {
                    return $this->colors['RED'] . "Error: Missing arguments. Use: lns mysql user add <username> <password>" . $this->colors['RESET'];
                }
                return $this->addUser($args[0], $args[1]);

            case 'del':
                if (empty($args)) {
                    return $this->colors['RED'] . "Error: Missing username. Use: lns mysql user del <username>" . $this->colors['RESET'];
                }
                return $this->removeUser($args[0]);

            default:
                return $this->colors['RED'] . "Unknown user action: $action" . $this->colors['RESET'];
        }
    }

    /**
     * List all MySQL users with formatted output.
     *
     * @return string Formatted user list
     */
    public function listUsers(): string
    {
        try {
            $mysql_bin = rtrim($this->config['paths']['mysql'] ?? '', DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'bin';
            $mysql_exe = $mysql_bin . DIRECTORY_SEPARATOR . 'mysql' . (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? '.exe' : '');

            if (!file_exists($mysql_exe)) {
                return $this->colors['RED'] . "Error: MySQL client not found at $mysql_exe" . $this->colors['RESET'];
            }

            $user = 'root';
            $pass = $this->config['services']['MySQL']['password'] ?? '';
            $host = 'localhost';

            $query = "SELECT User, Host FROM mysql.user ORDER BY User, Host;";
            $cmd = "\"$mysql_exe\" -u$user " . ($pass !== '' ? "-p$pass " : "") . "-h$host -e \"$query\" 2>&1";
            exec($cmd, $output, $return_var);

            if ($return_var !== 0 || empty($output)) {
                return $this->colors['RED'] . "Error executing MySQL command:\n" . implode("\n", $output) . $this->colors['RESET'];
            }

            return $this->formatUserList($output);

        } catch (\Exception $e) {
            return $this->colors['RED'] . "Exception: " . $e->getMessage() . $this->colors['RESET'];
        }
    }

    /**
     * Add a new MySQL user with password.
     *
     * @param string $username Username to create
     * @param string $password Password for the user
     * @return string Success or error message
     */
    public function addUser(string $username, string $password): string
    {
        // Validate username
        $username = trim($username);
        if (empty($username)) {
            return $this->colors['RED'] . "Error: Username cannot be empty." . $this->colors['RESET'];
        }

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            return $this->colors['RED'] . "Error: Invalid username. Only letters, numbers and underscore (_) allowed." . $this->colors['RESET'];
        }

        // Validate password
        $password = trim($password);
        if (empty($password)) {
            return $this->colors['RED'] . "Error: Password cannot be empty." . $this->colors['RESET'];
        }

        if (!preg_match('/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{8,}$/', $password)) {
            return $this->colors['RED'] . "Error: Password must be at least 8 characters long and include at least one letter and one number." . $this->colors['RESET'];
        }

        try {
            $mysql_bin = rtrim($this->config['paths']['mysql'] ?? '', DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'bin';
            $mysql_exe = $mysql_bin . DIRECTORY_SEPARATOR . 'mysql' . (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? '.exe' : '');

            if (!file_exists($mysql_exe)) {
                return $this->colors['RED'] . "Error: MySQL client not found at $mysql_exe" . $this->colors['RESET'];
            }

            $user = 'root';
            $pass = $this->config['services']['MySQL']['password'] ?? '';
            $host = 'localhost';

            // Create user and grant privileges
            $queries = [
                "CREATE USER '$username'@'%' IDENTIFIED BY '$password';",
                "GRANT ALL PRIVILEGES ON *.* TO '$username'@'%' WITH GRANT OPTION;",
                "FLUSH PRIVILEGES;"
            ];

            foreach ($queries as $query) {
                $cmd = "\"$mysql_exe\" -u$user " . ($pass !== '' ? "-p$pass " : "") . "-h$host -e \"$query\" 2>&1";
                exec($cmd, $output, $return_var);

                if ($return_var !== 0) {
                    return $this->colors['RED'] . "Error creating user '$username': " . implode("\n", $output) . $this->colors['RESET'];
                }
            }

            return $this->colors['GREEN'] . "MySQL user '$username' created successfully with full privileges." . $this->colors['RESET'];

        } catch (\Exception $e) {
            return $this->colors['RED'] . "Exception: " . $e->getMessage() . $this->colors['RESET'];
        }
    }

    /**
     * Remove a MySQL user.
     *
     * @param string $username Username to remove
     * @return string Success or error message
     */
    public function removeUser(string $username): string
    {
        $username = trim($username);
        if (empty($username)) {
            return $this->colors['RED'] . "Error: Username cannot be empty." . $this->colors['RESET'];
        }

        // Prevent deleting root user
        if (strtolower($username) === 'root') {
            return $this->colors['RED'] . "Error: Cannot delete root user." . $this->colors['RESET'];
        }

        try {
            $mysql_bin = rtrim($this->config['paths']['mysql'] ?? '', DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'bin';
            $mysql_exe = $mysql_bin . DIRECTORY_SEPARATOR . 'mysql' . (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? '.exe' : '');

            if (!file_exists($mysql_exe)) {
                return $this->colors['RED'] . "Error: MySQL client not found at $mysql_exe" . $this->colors['RESET'];
            }

            $user = 'root';
            $pass = $this->config['services']['MySQL']['password'] ?? '';
            $host = 'localhost';

            // Drop user from all hosts
            $query = "DROP USER IF EXISTS '$username'@'%', '$username'@'localhost';";
            $cmd = "\"$mysql_exe\" -u$user " . ($pass !== '' ? "-p$pass " : "") . "-h$host -e \"$query\" 2>&1";

            exec($cmd, $output, $return_var);

            if ($return_var !== 0) {
                return $this->colors['RED'] . "Error removing user '$username': " . implode("\n", $output) . $this->colors['RESET'];
            }

            return $this->colors['GREEN'] . "MySQL user '$username' removed successfully." . $this->colors['RESET'];

        } catch (\Exception $e) {
            return $this->colors['RED'] . "Exception: " . $e->getMessage() . $this->colors['RESET'];
        }
    }

    /**
     * Format user list with colors and table output.
     *
     * @param array $output Raw MySQL output
     * @return string Formatted user list
     */
    private function formatUserList(array $output): string
    {
        if (empty($output)) {
            return $this->colors['YELLOW'] . "No users found." . $this->colors['RESET'];
        }

        $users = [];
        foreach ($output as $line) {
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) >= 2 && $parts[0] !== 'User') {
                $users[] = ['user' => $parts[0], 'host' => $parts[1]];
            }
        }

        if (empty($users)) {
            return $this->colors['YELLOW'] . "No users found." . $this->colors['RESET'];
        }

        $output = $this->colors['MAGENTA'] . "=== MySQL Users ===" . $this->colors['RESET'] . PHP_EOL;
        
        // Calculate column widths
        $userWidth = max(array_map(fn($u) => strlen($u['user']), $users));
        $hostWidth = max(array_map(fn($u) => strlen($u['host']), $users));
        
        $userWidth = max($userWidth, 4) + 2; // "User" header
        $hostWidth = max($hostWidth, 4) + 2; // "Host" header

        // Header
        $output .= $this->colors['CYAN'] . str_pad('User', $userWidth) . str_pad('Host', $hostWidth) . $this->colors['RESET'] . PHP_EOL;
        $output .= str_repeat('-', $userWidth + $hostWidth) . PHP_EOL;

        // User rows
        foreach ($users as $user) {
            // System users in white, regular users in green
            if (preg_match('/^(mysql\.|root|sys)/i', $user['user'])) {
                $output .= $this->colors['WHITE'] . str_pad($user['user'], $userWidth) . str_pad($user['host'], $hostWidth) . $this->colors['RESET'];
            } else {
                $output .= $this->colors['GREEN'] . str_pad($user['user'], $userWidth) . str_pad($user['host'], $hostWidth) . $this->colors['RESET'];
            }
            $output .= PHP_EOL;
        }

        $output .= $this->colors['CYAN'] . "Total: " . count($users) . " users" . $this->colors['RESET'];
        
        return $output;
    }

    /**
     * Get help information for user commands.
     *
     * @return string Help message
     */
    public function getHelp(): string
    {
        return $this->colors['GREEN'] . "mysql users" . $this->colors['RESET'] . 
               " - List all MySQL users" . PHP_EOL .
               $this->colors['GREEN'] . "mysql user add <user> <pass>" . $this->colors['RESET'] . 
               " - Create a new MySQL user" . PHP_EOL .
               $this->colors['GREEN'] . "mysql user del <user>" . $this->colors['RESET'] . 
               " - Remove a MySQL user" . PHP_EOL .
               $this->colors['CYAN'] . "Usage examples:" . $this->colors['RESET'] . PHP_EOL .
               "  lns mysql users" . PHP_EOL .
               "  lns mysql user add john secret123" . PHP_EOL .
               "  lns mysql user del john" . PHP_EOL .
               $this->colors['YELLOW'] . "Password requirements: 8+ chars, at least 1 letter and 1 number" . $this->colors['RESET'];
    }
}