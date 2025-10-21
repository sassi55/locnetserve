<?php
//-------------------------------------------------------------
// UserLister.php - MySQL User Listing Service for LocNetServe
//-------------------------------------------------------------
// Handles listing of MySQL users with multiple fallback methods.
// Provides consistent user listing functionality.
//
// Author : Sassi Souid
// Project: LocNetServe
// Version: 1.0.0
//-------------------------------------------------------------

namespace Database\Services;

use Database\Connection\ConnectionManager;
use Database\Formatters\DatabaseFormatter;

class UserLister
{
    private array $config;
    private array $colors;
    private ConnectionManager $connectionManager;
    private DatabaseFormatter $formatter;

    /**
     * UserLister constructor.
     *
     * @param array $config Application configuration
     * @param DatabaseFormatter $formatter Output formatter
     */
    public function __construct(array $config, DatabaseFormatter $formatter, array $colors)
    {
        
		$this->config = $config;
		$this->colors = $colors;
        $this->connectionManager = ConnectionManager::getInstance($this->getConnectionConfig());
        $this->formatter = $formatter;
    }

    /**
     * List all MySQL users with formatted output.
     *
     * @return string Formatted user list or error message
     */
    public function listUsers(): string
    {
        try {
            $connection = $this->connectionManager->getConnection();
            $result = $connection->query("SELECT User, Host FROM mysql.user ORDER BY User, Host");
            
            if ($result === false) {
                return $this->formatter->formatError("Failed to list users");
            }

            $users = $result->fetchAll(\PDO::FETCH_ASSOC);
            return $this->formatter->formatUserList($users);

        } catch (\Exception $e) {
            // Fallback to executable method if PDO fails
            return $this->listUsersExec();
        }
    }

    /**
     * Get detailed information about a specific user
     *
     * @param string $username Username to inspect
     * @param string $host Host (optional, defaults to '%')
     * @return string User information
     */
    public function getUserInfo(string $username, string $host = '%'): string
    {
        if (!$this->userExists($username, $host)) {
            return $this->formatter->formatError("User '$username'@'$host' does not exist.");
        }

        $privileges = $this->getUserPrivileges($username, $host);
        $userDetails = $this->getUserDetails($username, $host);
        
        $output = $this->formatter->formatInfo("=== User Information: $username@$host ===") . PHP_EOL . PHP_EOL;
        
        // Section Détails de l'utilisateur
        $output .= $this->formatter->formatSuccess("▲ User Details:") . PHP_EOL;
        if (!empty($userDetails)) {
            foreach ($userDetails as $label => $value) {
                $output .= "  " . $this->formatter->formatInfo("• $label: ") . $this->colors['WHITE'] . $value . $this->colors['RESET'] . PHP_EOL;
            }
        }
        
        $output .= PHP_EOL;

        // Section Privilèges
        $output .= $this->formatter->formatSuccess("▲ Privileges:") . PHP_EOL;
        if (empty($privileges)) {
            $output .= "  " . $this->formatter->formatWarning("No privileges found or unable to retrieve privileges.") . PHP_EOL;
        } else {
            foreach ($privileges as $privilege) {
                $formattedPrivilege = $this->formatPrivilege($privilege);
                $output .= $formattedPrivilege . PHP_EOL;
            }
        }

        return $output;
    }

    /**
     * Format a privilege string for better readability
     *
     * @param string $privilege Raw privilege string
     * @return string Formatted privilege
     */
    private function formatPrivilege(string $privilege): string
    {
        // Nettoyer et formater le privilège
        $privilege = trim($privilege);
        
        // Séparer les différentes parties du GRANT
        if (preg_match('/^GRANT\s+(.*?)\s+ON\s+(.*?)\s+TO\s+(.*?)(?:\s+WITH\s+(.*))?$/', $privilege, $matches)) {
            $privilegesList = $matches[1];
            $databaseObject = $matches[2];
            $userInfo = $matches[3];
            $withOptions = $matches[4] ?? '';
            
            $formatted = "  " . $this->formatter->formatInfo("• Privileges: ") . 
                        $this->colors['GREEN'] . $this->formatPrivilegesList($privilegesList) . $this->colors['RESET'] . PHP_EOL;
            
            $formatted .= "    " . $this->formatter->formatInfo("On: ") . 
                         $this->colors['CYAN'] . $databaseObject . $this->colors['RESET'] . PHP_EOL;
            
            $formatted .= "    " . $this->formatter->formatInfo("To: ") . 
                         $this->colors['YELLOW'] . $userInfo . $this->colors['RESET'];
            
            if (!empty($withOptions)) {
                $formatted .= PHP_EOL . "    " . $this->formatter->formatInfo("Options: ") . 
                             $this->colors['MAGENTA'] . $withOptions . $this->colors['RESET'];
            }
            
            return $formatted;
        }
        
        // Fallback pour un formatage simple
        return "  " . $this->formatter->formatInfo("• ") . $this->colors['WHITE'] . wordwrap($privilege, 80, PHP_EOL . "    ") . $this->colors['RESET'];
    }

    /**
     * Format privileges list for better readability
     *
     * @param string $privilegesList Comma-separated privileges
     * @return string Formatted privileges
     */
    private function formatPrivilegesList(string $privilegesList): string
    {
        // Séparer les privilèges par virgule
        $privileges = array_map('trim', explode(',', $privilegesList));
        
        // Grouper les privilèges par ligne (4 par ligne)
        $chunks = array_chunk($privileges, 4);
        $formatted = [];
        
        foreach ($chunks as $chunk) {
            $formatted[] = implode(', ', $chunk);
        }
        
        return implode(PHP_EOL . "                 ", $formatted);
    }

/**
     * Get detailed user information
     *
     * @param string $username Username
     * @param string $host Host
     * @return array User details
     */
    private function getUserDetails(string $username, string $host = '%'): array
    {
        try {
            $connection = $this->connectionManager->getConnection();
            
            // Requête compatible avec différentes versions de MySQL
            $sql = "SELECT 
                        User, 
                        Host, 
                        account_locked, 
                        password_expired,
                        password_last_changed,
                        password_lifetime
                    FROM mysql.user 
                    WHERE User = ? AND Host = ?";
            
            $stmt = $connection->query($sql, [$username, $host]);
            
            if ($stmt === false) {
                return [];
            }

            $details = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$details) {
                return [];
            }

            $userDetails = [];
            $userDetails['Username'] = $details['User'];
            $userDetails['Host'] = $details['Host'];
            
            // Champs optionnels (peuvent ne pas exister dans toutes les versions)
            if (isset($details['account_locked'])) {
                $userDetails['Account Locked'] = $details['account_locked'] === 'Y' ? 'Yes 🔒' : 'No ✅';
            }
            
            if (isset($details['password_expired'])) {
                $userDetails['Password Expired'] = $details['password_expired'] === 'Y' ? 'Yes ⚠️' : 'No ✅';
            }
            
            if (isset($details['password_last_changed']) && $details['password_last_changed']) {
                $userDetails['Password Changed'] = $details['password_last_changed'];
            }
            
            if (isset($details['password_lifetime']) && $details['password_lifetime']) {
                $userDetails['Password Lifetime'] = $details['password_lifetime'] . ' days';
            }

            return $userDetails;

        } catch (\Exception $e) {
            // Si la requête échoue, essayer une version plus simple
            return $this->getBasicUserDetails($username, $host);
        }
    }

    /**
     * Get basic user details (fallback method)
     *
     * @param string $username Username
     * @param string $host Host
     * @return array Basic user details
     */
    private function getBasicUserDetails(string $username, string $host = '%'): array
    {
        try {
            $connection = $this->connectionManager->getConnection();
            
            // Requête basique qui devrait fonctionner sur toutes les versions
            $sql = "SELECT User, Host FROM mysql.user WHERE User = ? AND Host = ?";
            $stmt = $connection->query($sql, [$username, $host]);
            
            if ($stmt === false) {
                return [];
            }

            $details = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$details) {
                return [];
            }

            return [
                'Username' => $details['User'],
                'Host' => $details['Host']
            ];

        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Fallback method using MySQL executable
     */
    private function listUsersExec(): string
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

            $query = "SELECT User, Host FROM mysql.user ORDER BY User, Host;";
            $cmd = "\"$mysql_exe\" -u$user " . ($pass !== '' ? "-p$pass " : "") . "-h$host -e \"$query\" 2>&1";
            exec($cmd, $output, $return_var);

            if ($return_var !== 0 || empty($output)) {
                return $this->formatter->formatError("Error executing MySQL command:\n" . implode("\n", $output));
            }

            // Process and format output
            $users = $this->parseUserOutput($output);
            return $this->formatter->formatUserList($users);

        } catch (\Exception $e) {
            return $this->formatter->formatError("Exception: " . $e->getMessage());
        }
    }

    /**
     * Parse MySQL command output into user array
     *
     * @param array $output Raw MySQL output
     * @return array Array of user data
     */
    private function parseUserOutput(array $output): array
    {
        $users = [];
        foreach ($output as $line) {
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) >= 2 && $parts[0] !== 'User') {
                $users[] = ['User' => $parts[0], 'Host' => $parts[1]];
            }
        }
        return $users;
    }

    /**
     * Get raw user list as array
     *
     * @return array Array of user data or empty array on error
     */
    public function getUserArray(): array
    {
        try {
            $connection = $this->connectionManager->getConnection();
            $result = $connection->query("SELECT User, Host FROM mysql.user ORDER BY User, Host");
            
            if ($result === false) {
                return [];
            }

            return $result->fetchAll(\PDO::FETCH_ASSOC);

        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Check if a user exists
     *
     * @param string $username Username to check
     * @param string $host Host to check (default: '%')
     * @return bool True if user exists
     */
    public function userExists(string $username, string $host = '%'): bool
    {
        $users = $this->getUserArray();
        foreach ($users as $user) {
            if ($user['User'] === $username && ($host === '%' || $user['Host'] === $host)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get user privileges
     *
     * @param string $username Username
     * @param string $host Host
     * @return array User privileges
     */
    public function getUserPrivileges(string $username, string $host = '%'): array
    {
        try {
            $connection = $this->connectionManager->getConnection();
            
            // Essayer avec la syntaxe paramétrée d'abord
            try {
                $sql = "SHOW GRANTS FOR ?@?";
                $stmt = $connection->query($sql, [$username, $host]);
                
                if ($stmt !== false) {
                    return $stmt->fetchAll(\PDO::FETCH_COLUMN);
                }
            } catch (\Exception $e) {
                // Continuer avec la méthode alternative
            }

            // Méthode alternative pour MySQL 5.7+
            $sql = "SHOW GRANTS FOR '$username'@'$host'";
            $stmt = $connection->query($sql);
            
            if ($stmt !== false) {
                return $stmt->fetchAll(\PDO::FETCH_COLUMN);
            }

            return [];

        } catch (\Exception $e) {
            return ["Unable to retrieve privileges: " . $e->getMessage()];
        }
    }

    /**
     * Get connection configuration
     */
    private function getConnectionConfig(): array
    {
        return [
            'default' => [
                'host' => $this->config['services']['MySQL']['host'] ?? 'localhost',
                'port' => $this->config['services']['MySQL']['port'] ?? 3306,
                'user' => $this->config['services']['MySQL']['user'] ?? 'root',
                'pass' => $this->config['services']['MySQL']['password'] ?? '',
                'dbname' => 'mysql',
                'charset' => 'utf8mb4',
                'timeout' => 5
            ]
        ];
    }
}