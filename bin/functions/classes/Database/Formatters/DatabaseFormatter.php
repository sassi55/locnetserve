<?php
/**
 * -------------------------------------------------------------
 *  DatabaseFormatter.php - Database Output Formatter for LocNetServe
 * -------------------------------------------------------------
 *  Formats database command output consistently by:
 *    - Providing standardized formatting for all database commands
 *    - Handling colorized output and structured data presentation
 *    - Supporting error messages and success notifications
 *    - Ensuring uniform user experience across database operations
 *
 *  Author : Sassi Souid
 *  Email  : locnetserve@gmail.com
 *  Project: LocNetServe
 *  Version: 1.0.0
 * -------------------------------------------------------------
 */

namespace Database\Formatters;

class DatabaseFormatter
{
    private array $colors;

    /**
     * DatabaseFormatter constructor.
     *
     * @param array $colors Color configuration for CLI output
     */
    public function __construct(array $colors)
    {
        $this->colors = $colors;
    }

    /**
 * Format database list with colors and table-like output.
 *
 * @param array $databases Array of database names
 * @return string Formatted database list
 */
public function formatDatabaseList(array $databases): string
{
    if (empty($databases)) {
        return $this->colors['YELLOW'] . "No databases found." . $this->colors['RESET'];
    }

    // Séparer les bases utilisateur et système
    $userDatabases = [];
    $systemDatabases = [];
    
    foreach ($databases as $dbName) {
        if (in_array(strtolower($dbName), ['mysql', 'information_schema', 'performance_schema', 'sys'])) {
            $systemDatabases[] = $dbName;
        } else {
            $userDatabases[] = $dbName;
        }
    }

    $output = '';

    // Afficher les bases utilisateur d'abord
    if (!empty($userDatabases)) {
        $output .= $this->colors['MAGENTA'] . "=== MySQL User Databases ===" . $this->colors['RESET'] . PHP_EOL;
        
        // Calculer la largeur maximale pour l'alignement (uniquement pour les bases utilisateur)
        $maxWidth = !empty($userDatabases) ? max(array_map('strlen', $userDatabases)) : 0;
        
        foreach ($userDatabases as $dbName) {
            $output .= $this->colors['GREEN'] . str_pad($dbName, $maxWidth + 2) . $this->colors['RESET'] . PHP_EOL;
        }
        
        $output .= PHP_EOL; // Ligne vide pour séparer
    }

    // Afficher les bases système en bas
    if (!empty($systemDatabases)) {
        $output .= $this->colors['MAGENTA'] . "=== MySQL System Databases ===" . $this->colors['RESET'] . PHP_EOL;
        
        // Calculer la largeur maximale pour l'alignement (uniquement pour les bases système)
        $maxWidthSystem = !empty($systemDatabases) ? max(array_map('strlen', $systemDatabases)) : 0;
        
        foreach ($systemDatabases as $dbName) {
            $output .= $this->colors['WHITE'] . str_pad($dbName, $maxWidthSystem + 2) . $this->colors['RESET'] . PHP_EOL;
        }
    }

    // Statistiques finales
    $output .= PHP_EOL . $this->colors['CYAN'] . "Total: " . count($databases) . " databases (" 
              . count($userDatabases) . " user, " . count($systemDatabases) . " system)" . $this->colors['RESET'];
    
    return $output;
}


    /**
     * Format user list with table output.
     *
     * @param array $users Array of user data [['User' => '', 'Host' => '']]
     * @return string Formatted user list
     */
    public function formatUserList(array $users): string
    {
        if (empty($users)) {
            return $this->colors['YELLOW'] . "No users found." . $this->colors['RESET'];
        }

        $output = $this->colors['MAGENTA'] . "=== MySQL Users ===" . $this->colors['RESET'] . PHP_EOL;
        
        // Calculate column widths
        $userWidth = max(array_map(fn($u) => strlen($u['User']), $users));
        $hostWidth = max(array_map(fn($u) => strlen($u['Host']), $users));
        
        $userWidth = max($userWidth, 4) + 2; // "User" header
        $hostWidth = max($hostWidth, 4) + 2; // "Host" header

        // Header
        $output .= $this->colors['CYAN'] . str_pad('User', $userWidth) . str_pad('Host', $hostWidth) . $this->colors['RESET'] . PHP_EOL;
        $output .= str_repeat('-', $userWidth + $hostWidth) . PHP_EOL;

        // User rows
        foreach ($users as $user) {
            // System users in white, regular users in green
            if (preg_match('/^(mysql\.|root|sys)/i', $user['User'])) {
                $output .= $this->colors['WHITE'] . str_pad($user['User'], $userWidth) . str_pad($user['Host'], $hostWidth) . $this->colors['RESET'];
            } else {
                $output .= $this->colors['GREEN'] . str_pad($user['User'], $userWidth) . str_pad($user['Host'], $hostWidth) . $this->colors['RESET'];
            }
            $output .= PHP_EOL;
        }

        $output .= $this->colors['CYAN'] . "Total: " . count($users) . " users" . $this->colors['RESET'];
        
        return $output;
    }
    /**
     * Format a success message.
     *
     * @param string $message Success message
     * @return string Formatted success message
     */
    public function formatSuccess(string $message): string
    {
        return $this->colors['GREEN'] . $message . $this->colors['RESET'];
    }

    /**
     * Format an error message.
     *
     * @param string $message Error message
     * @return string Formatted error message
     */
    public function formatError(string $message): string
    {
        return $this->colors['RED'] . $message . $this->colors['RESET'];
    }

    /**
     * Format a warning message.
     *
     * @param string $message Warning message
     * @return string Formatted warning message
     */
    public function formatWarning(string $message): string
    {
        return $this->colors['YELLOW'] . $message . $this->colors['RESET'];
    }

    /**
     * Format an info message.
     *
     * @param string $message Info message
     * @return string Formatted info message
     */
    public function formatInfo(string $message): string
    {
        return $this->colors['CYAN'] . $message . $this->colors['RESET'];
    }
}