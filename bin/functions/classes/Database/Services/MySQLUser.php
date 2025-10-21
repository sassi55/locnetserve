<?php
//-------------------------------------------------------------
// MySQLUser.php - MySQL User Management Service for LocNetServe
//-------------------------------------------------------------
// Handles MySQL user management operations (add, delete, modify).
// Provides secure user management with proper validation.
//
// Author : Sassi Souid
// Project: LocNetServe
// Version: 1.0.0
//-------------------------------------------------------------

namespace Database\Services;

use Database\Connection\MySQLConnection;
use Database\Formatters\DatabaseFormatter;

class MySQLUser
{
    private array $config;
    private DatabaseFormatter $formatter;
    private array $colors;
    private UserLister $userLister;

    /**
     * MySQLUser constructor.
     *
     * @param array $config Application configuration
     * @param DatabaseFormatter $formatter Output formatter
     */
    public function __construct(array $config, DatabaseFormatter $formatter, array $colors)
    {
        $this->config = $config;
        $this->formatter = $formatter;
        $this->colors = $colors;
        $this->userLister = new UserLister($config, $formatter, $colors);
    }

   

    /**
     * Add a new MySQL user with password.
     *
     * @param string $username Username to create
     * @param string $password Password for the user
     * @param string $host Host access (default: '%')
     * @return string Success or error message
     */
    public function addUser(string $username, string $password, string $host = '%'): string
    {
        // Validate inputs
        $validationResult = $this->validateUserInputs($username, $password, $host);
        if ($validationResult !== true) {
            return $validationResult;
        }

        try {
            $connection = $this->getConnection();
            
            // Check if user already exists
            if ($this->userLister->userExists($username, $host)) {
                return $this->formatter->formatWarning("User '$username'@'$host' already exists.");
            }

            // Create user
            $createUserQuery = "CREATE USER ?@? IDENTIFIED BY ?";
            $stmt = $connection->query($createUserQuery, [$username, $host, $password]);
            
            if ($stmt === false) {
                return $this->formatter->formatError("Failed to create user '$username'@'$host'");
            }

            // Grant privileges (default: all privileges on all databases)
            $grantQuery = "GRANT ALL PRIVILEGES ON *.* TO ?@? WITH GRANT OPTION";
            $stmt = $connection->query($grantQuery, [$username, $host]);
            
            if ($stmt === false) {
                // If grant fails, try to drop the user to clean up
                $this->dropUser($username, $host);
                return $this->formatter->formatError("Failed to grant privileges to user '$username'@'$host'");
            }

            // Flush privileges
            $connection->query("FLUSH PRIVILEGES");

            return $this->formatter->formatSuccess("User '$username'@'$host' created successfully with full privileges.");

        } catch (\Exception $e) {
            // Fallback to executable method
            return $this->addUserExec($username, $password, $host);
        }
    }

    /**
     * Remove a MySQL user.
     *
     * @param string $username Username to remove
     * @param string $host Host (default: '%')
     * @return string Success or error message
     */
    public function removeUser(string $username, string $host = '%'): string
    {
        $username = trim($username);
        if (empty($username)) {
            return $this->formatter->formatError("Username cannot be empty.");
        }

        // Prevent deleting root user
        if (strtolower($username) === 'root') {
            return $this->formatter->formatError("Cannot delete root user.");
        }

        // Check if user exists
        if (!$this->userLister->userExists($username, $host)) {
            return $this->formatter->formatWarning("User '$username'@'$host' does not exist.");
        }

        try {
            $connection = $this->getConnection();
            
            // Drop user from all common hosts
            $hosts = ['%', 'localhost', '127.0.0.1', '::1'];
            $dropped = false;
            
            foreach ($hosts as $userHost) {
                if ($this->userLister->userExists($username, $userHost)) {
                    $dropQuery = "DROP USER ?@?";
                    $stmt = $connection->query($dropQuery, [$username, $userHost]);
                    
                    if ($stmt !== false) {
                        $dropped = true;
                    }
                }
            }

            if ($dropped) {
                $connection->query("FLUSH PRIVILEGES");
                return $this->formatter->formatSuccess("User '$username' removed successfully from all hosts.");
            } else {
                return $this->formatter->formatError("Failed to remove user '$username'");
            }

        } catch (\Exception $e) {
            return $this->formatter->formatError("Error removing user: " . $e->getMessage());
        }
    }

    /**
     * Change user password
     *
     * @param string $username Username
     * @param string $newPassword New password
     * @param string $host Host (default: '%')
     * @return string Success or error message
     */
    public function changePassword(string $username, string $newPassword, string $host = '%'): string
    {
        $username = trim($username);
        if (empty($username)) {
            return $this->formatter->formatError("Username cannot be empty.");
        }

        // Validate password
        $passwordValidation = $this->validatePassword($newPassword);
        if ($passwordValidation !== true) {
            return $passwordValidation;
        }

        // Check if user exists
        if (!$this->userLister->userExists($username, $host)) {
            return $this->formatter->formatError("User '$username'@'$host' does not exist.");
        }

        try {
            $connection = $this->getConnection();
            
            // Change password (MySQL 5.7.6+ syntax)
            $changePasswordQuery = "ALTER USER ?@? IDENTIFIED BY ?";
            $stmt = $connection->query($changePasswordQuery, [$username, $host, $newPassword]);
            
            if ($stmt === false) {
                // Fallback to SET PASSWORD for older MySQL versions
                $setPasswordQuery = "SET PASSWORD FOR ?@? = PASSWORD(?)";
                $stmt = $connection->query($setPasswordQuery, [$username, $host, $newPassword]);
                
                if ($stmt === false) {
                    return $this->formatter->formatError("Failed to change password for user '$username'@'$host'");
                }
            }

            return $this->formatter->formatSuccess("Password changed successfully for user '$username'@'$host'");

        } catch (\Exception $e) {
            return $this->formatter->formatError("Error changing password: " . $e->getMessage());
        }
    }

    /**
     * Grant privileges to a user
     *
     * @param string $username Username
     * @param string $privileges Privileges (e.g., "SELECT,INSERT", "ALL PRIVILEGES")
     * @param string $database Database (default: "*.*")
     * @param string $host Host (default: '%')
     * @return string Success or error message
     */
    public function grantPrivileges(string $username, string $privileges, string $database = "*.*", string $host = '%'): string
    {
        $username = trim($username);
        if (empty($username)) {
            return $this->formatter->formatError("Username cannot be empty.");
        }

        // Check if user exists
        if (!$this->userLister->userExists($username, $host)) {
            return $this->formatter->formatError("User '$username'@'$host' does not exist.");
        }

        try {
            $connection = $this->getConnection();
            
            $grantQuery = "GRANT $privileges ON $database TO ?@?";
            $stmt = $connection->query($grantQuery, [$username, $host]);
            
            if ($stmt === false) {
                return $this->formatter->formatError("Failed to grant privileges to user '$username'@'$host'");
            }

            $connection->query("FLUSH PRIVILEGES");

            return $this->formatter->formatSuccess("Privileges '$privileges' granted on '$database' to user '$username'@'$host'");

        } catch (\Exception $e) {
            return $this->formatter->formatError("Error granting privileges: " . $e->getMessage());
        }
    }

    /**
     * Revoke privileges from a user
     *
     * @param string $username Username
     * @param string $privileges Privileges to revoke
     * @param string $database Database (default: "*.*")
     * @param string $host Host (default: '%')
     * @return string Success or error message
     */
    public function revokePrivileges(string $username, string $privileges, string $database = "*.*", string $host = '%'): string
    {
        $username = trim($username);
        if (empty($username)) {
            return $this->formatter->formatError("Username cannot be empty.");
        }

        // Check if user exists
        if (!$this->userLister->userExists($username, $host)) {
            return $this->formatter->formatError("User '$username'@'$host' does not exist.");
        }

        try {
            $connection = $this->getConnection();
            
            $revokeQuery = "REVOKE $privileges ON $database FROM ?@?";
            $stmt = $connection->query($revokeQuery, [$username, $host]);
            
            if ($stmt === false) {
                return $this->formatter->formatError("Failed to revoke privileges from user '$username'@'$host'");
            }

            $connection->query("FLUSH PRIVILEGES");

            return $this->formatter->formatSuccess("Privileges '$privileges' revoked on '$database' from user '$username'@'$host'");

        } catch (\Exception $e) {
            return $this->formatter->formatError("Error revoking privileges: " . $e->getMessage());
        }
    }

    /**
     * Validate user inputs
     */
    private function validateUserInputs(string $username, string $password, string $host): string|bool
    {
        $username = trim($username);
        if (empty($username)) {
            return $this->formatter->formatError("Username cannot be empty.");
        }

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            return $this->formatter->formatError("Invalid username. Only letters, numbers and underscore (_) allowed.");
        }

        // Validate password
        $passwordValidation = $this->validatePassword($password);
        if ($passwordValidation !== true) {
            return $passwordValidation;
        }

        // Validate host
        if (!preg_match('/^[%a-zA-Z0-9._-]+$/', $host)) {
            return $this->formatter->formatError("Invalid host format.");
        }

        return true;
    }

    /**
     * Validate password strength
     */
    private function validatePassword(string $password): string|bool
    {
        $password = trim($password);
        if (empty($password)) {
            return $this->formatter->formatError("Password cannot be empty.");
        }

        if (strlen($password) < 8) {
            return $this->formatter->formatError("Password must be at least 8 characters long.");
        }

        if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            return $this->formatter->formatError("Password must include at least one letter and one number.");
        }

        return true;
    }

    /**
     * Fallback method for user creation using executable
     */
    private function addUserExec(string $username, string $password, string $host = '%'): string
    {
        try {
            $mysql_bin = rtrim($this->config['paths']['mysql'] ?? '', DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'bin';
            $mysql_exe = $mysql_bin . DIRECTORY_SEPARATOR . 'mysql' . (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? '.exe' : '');

            if (!file_exists($mysql_exe)) {
                return $this->formatter->formatError("MySQL client not found at $mysql_exe");
            }

            $user = 'root';
            $pass = $this->config['services']['MySQL']['password'] ?? '';
            $host = 'localhost';

            // Create user and grant privileges
            $queries = [
                "CREATE USER '$username'@'$host' IDENTIFIED BY '$password';",
                "GRANT ALL PRIVILEGES ON *.* TO '$username'@'$host' WITH GRANT OPTION;",
                "FLUSH PRIVILEGES;"
            ];

            foreach ($queries as $query) {
                $cmd = "\"$mysql_exe\" -u$user " . ($pass !== '' ? "-p$pass " : "") . "-h$host -e \"$query\" 2>&1";
                exec($cmd, $output, $return_var);

                if ($return_var !== 0) {
                    return $this->formatter->formatError("Error creating user '$username': " . implode("\n", $output));
                }
            }

            return $this->formatter->formatSuccess("MySQL user '$username' created successfully with full privileges.");

        } catch (\Exception $e) {
            return $this->formatter->formatError("Exception: " . $e->getMessage());
        }
    }

    /**
     * Get database connection
     */
    private function getConnection(): MySQLConnection
    {
        $host = $this->config['services']['MySQL']['host'] ?? 'localhost';
        $port = $this->config['services']['MySQL']['port'] ?? 3306;
        $user = $this->config['services']['MySQL']['user'] ?? 'root';
        $pass = $this->config['services']['MySQL']['password'] ?? '';

        return new MySQLConnection($host, $port, $user, $pass, 'mysql');
    }

    /**
     * Drop user (internal method for cleanup)
     */
    private function dropUser(string $username, string $host): void
    {
        try {
            $connection = $this->getConnection();
            $dropQuery = "DROP USER IF EXISTS ?@?";
            $connection->query($dropQuery, [$username, $host]);
        } catch (\Exception $e) {
            // Ignore errors during cleanup
        }
    }

    /**
     * Get user management help information
     */
    public function getHelp(): string
    {
        return $this->formatter->formatInfo("=== MySQL User Management Help ===") . PHP_EOL . PHP_EOL .
               $this->formatter->formatSuccess("Available Commands:") . PHP_EOL .
               "  " . $this->formatter->formatInfo("• add <user> <pass> [host]") . " - Create new user" . PHP_EOL .
               "  " . $this->formatter->formatInfo("• remove <user> [host]") . " - Remove user" . PHP_EOL .
               "  " . $this->formatter->formatInfo("• password <user> <new_pass> [host]") . " - Change password" . PHP_EOL .
               "  " . $this->formatter->formatInfo("• grant <user> <privileges> [database] [host]") . " - Grant privileges" . PHP_EOL .
               "  " . $this->formatter->formatInfo("• revoke <user> <privileges> [database] [host]") . " - Revoke privileges" . PHP_EOL . PHP_EOL .
               $this->formatter->formatSuccess("Examples:") . PHP_EOL .
               "  " . $this->formatter->formatInfo("• Add user:") . " mysql user add john secret123" . PHP_EOL .
               "  " . $this->formatter->formatInfo("• Remove user:") . " mysql user remove john" . PHP_EOL .
               "  " . $this->formatter->formatInfo("• Change password:") . " mysql user password john newpass123" . PHP_EOL .
               "  " . $this->formatter->formatInfo("• Grant privileges:") . " mysql user grant john SELECT my_database" . PHP_EOL . PHP_EOL .
               $this->formatter->formatSuccess("Password Requirements:") . PHP_EOL .
               "  " . $this->formatter->formatInfo("• Minimum 8 characters") . PHP_EOL .
               "  " . $this->formatter->formatInfo("• At least one letter and one number") . PHP_EOL;
    }
}