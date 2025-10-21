<?php
//-------------------------------------------------------------
// MySQLVersion.php - MySQL Version Service for LocNetServe
//-------------------------------------------------------------
// Handles MySQL version detection and display.
// Provides version information from MySQL executable and server.
//
// Author : Sassi Souid
// Project: LocNetServe
// Version: 1.0.0
//-------------------------------------------------------------

namespace Database\Services;

use Database\Connection\MySQLConnection;
use Database\Formatters\DatabaseFormatter;

class MySQLVersion
{
    private array $config;
    private DatabaseFormatter $formatter;
    private array $colors;

    /**
     * MySQLVersion constructor.
     *
     * @param array $config Application configuration
     * @param DatabaseFormatter $formatter Output formatter
     */
    public function __construct(array $config, DatabaseFormatter $formatter, array $colors)
    {
        $this->config = $config;
        $this->formatter = $formatter;
        $this->colors = $colors;
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
     * Get MySQL version information.
     *
     * @return string Formatted version information
     */
    public function getVersion(): string
    {
        $serverVersion = $this->getServerVersion();
        $clientVersion = $this->getClientVersion();
        $versionDetails = $this->getVersionDetails();
        
        $output = $this->formatter->formatInfo("=== MySQL Version Information ===") . PHP_EOL . PHP_EOL;
        
        // Server Version
        $output .= $this->formatter->formatSuccess("▲ Server Version:") . PHP_EOL;
        if ($serverVersion['success']) {
            $output .= "  " . $this->formatter->formatInfo("• Version: ") . $this->colors['GREEN'] . $serverVersion['version'] . $this->colors['RESET'] . PHP_EOL;
            $output .= "  " . $this->formatter->formatInfo("• Type: ") . $this->colors['CYAN'] . $serverVersion['type'] . $this->colors['RESET'] . PHP_EOL;
            if (!empty($serverVersion['comment'])) {
                $output .= "  " . $this->formatter->formatInfo("• Comment: ") . $this->colors['YELLOW'] . $serverVersion['comment'] . $this->colors['RESET'] . PHP_EOL;
            }
        } else {
            $output .= "  " . $this->formatter->formatError("• Unable to retrieve server version: " . $serverVersion['error']) . PHP_EOL;
        }

        $output .= PHP_EOL;

        // Client Version
        $output .= $this->formatter->formatSuccess("▲ Client Version:") . PHP_EOL;
        if ($clientVersion['success']) {
            $output .= "  " . $this->formatter->formatInfo("• Version: ") . $this->colors['GREEN'] . $clientVersion['version'] . $this->colors['RESET'] . PHP_EOL;
            $output .= "  " . $this->formatter->formatInfo("• Executable: ") . $this->colors['WHITE'] . $clientVersion['executable'] . $this->colors['RESET'] . PHP_EOL;
        } else {
            $output .= "  " . $this->formatter->formatError("• Unable to retrieve client version: " . $clientVersion['error']) . PHP_EOL;
        }

        $output .= PHP_EOL;

        // Version Details
        $output .= $this->formatter->formatSuccess("▲ Version Details:") . PHP_EOL;
        if (!empty($versionDetails)) {
            foreach ($versionDetails as $detail) {
                $output .= "  " . $this->formatter->formatInfo("• ") . $this->colors['WHITE'] . $detail . $this->colors['RESET'] . PHP_EOL;
            }
        } else {
            $output .= "  " . $this->formatter->formatWarning("• No additional version details available") . PHP_EOL;
        }

        $output .= PHP_EOL;

        // Compatibility Check
        $compatibility = $this->checkCompatibility();
        $output .= $this->formatter->formatSuccess("▲ Compatibility:") . PHP_EOL;
        $output .= "  " . $this->formatter->formatInfo("• Status: ") . 
                  ($compatibility['compatible'] ? 
                   $this->colors['GREEN'] . "Compatible" . $this->colors['RESET'] : 
                   $this->colors['YELLOW'] . "Version Mismatch" . $this->colors['RESET']) . PHP_EOL;
        
        if (!$compatibility['compatible']) {
            $output .= "  " . $this->formatter->formatInfo("• Server: ") . $this->colors['CYAN'] . $compatibility['server'] . $this->colors['RESET'] . PHP_EOL;
            $output .= "  " . $this->formatter->formatInfo("• Client: ") . $this->colors['CYAN'] . $compatibility['client'] . $this->colors['RESET'] . PHP_EOL;
        }

        return $output;
    }

    /**
     * Get MySQL server version
     *
     * @return array Server version information
     */
    public function getServerVersion(): array
    {
        try {
            $host = $this->config['services']['MySQL']['host'] ?? 'localhost';
            $port = $this->config['services']['MySQL']['port'] ?? 3306;
            $user = $this->config['services']['MySQL']['user'] ?? 'root';
            $pass = $this->config['services']['MySQL']['password'] ?? '';

            $connection = new MySQLConnection($host, $port, $user, $pass);
            $pdo = $connection->getPDO();

            // Get basic version
            $version = $pdo->query("SELECT VERSION()")->fetchColumn();
            
            // Get version comment
            $comment = $pdo->query("SELECT @@version_comment")->fetchColumn();
            
            // Determine version type
            $type = $this->determineVersionType($version);

            return [
                'success' => true,
                'version' => $version,
                'comment' => $comment,
                'type' => $type,
                'raw' => $version
            ];

        } catch (\Exception $e) {
            // Fallback to executable method
            return $this->getServerVersionFromExecutable();
        }
    }

    /**
     * Get server version from MySQL executable (fallback)
     */
    private function getServerVersionFromExecutable(): array
    {
        $mysqld = $this->config['services']['MySQL']['exe'] ?? '';
        
        if (empty($mysqld) || !file_exists($mysqld)) {
            return [
                'success' => false,
                'error' => 'MySQL executable not found or not configured',
                'version' => 'Unknown',
                'type' => 'Unknown'
            ];
        }

        $output = [];
        exec('"' . $mysqld . '" --version', $output, $return_var);

        if ($return_var !== 0 || empty($output)) {
            return [
                'success' => false,
                'error' => 'Failed to get MySQL version from executable',
                'version' => 'Unknown',
                'type' => 'Unknown'
            ];
        }

        $versionString = trim($output[0]);
        $versionString = str_replace($mysqld, '', $versionString);
        $versionString = trim($versionString);

        $type = $this->determineVersionType($versionString);

        return [
            'success' => true,
            'version' => $versionString,
            'comment' => '',
            'type' => $type,
            'raw' => $versionString
        ];
    }

    /**
     * Get MySQL client version
     *
     * @return array Client version information
     */
    public function getClientVersion(): array
    {
        $mysql_bin = rtrim($this->config['paths']['mysql'] ?? '', DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'bin';
        $mysql_exe = $mysql_bin . DIRECTORY_SEPARATOR . 'mysql' . (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? '.exe' : '');

        if (!file_exists($mysql_exe)) {
            return [
                'success' => false,
                'error' => 'MySQL client not found: ' . $mysql_exe,
                'version' => 'Unknown',
                'executable' => $mysql_exe
            ];
        }

        $output = [];
        exec('"' . $mysql_exe . '" --version', $output, $return_var);

        if ($return_var !== 0 || empty($output)) {
            return [
                'success' => false,
                'error' => 'Failed to get MySQL client version',
                'version' => 'Unknown',
                'executable' => $mysql_exe
            ];
        }

        $versionString = trim($output[0]);
        $versionString = str_replace($mysql_exe, '', $versionString);
        $versionString = trim($versionString);

        return [
            'success' => true,
            'version' => $versionString,
            'executable' => $mysql_exe,
            'raw' => $versionString
        ];
    }

    /**
     * Determine MySQL version type
     */
    private function determineVersionType(string $versionString): string
    {
        $versionString = strtolower($versionString);

        if (strpos($versionString, 'mariadb') !== false) {
            return 'MariaDB';
        } elseif (strpos($versionString, 'percona') !== false) {
            return 'Percona Server';
        } elseif (strpos($versionString, 'mysql cluster') !== false) {
            return 'MySQL Cluster';
        } elseif (strpos($versionString, 'enterprise') !== false) {
            return 'MySQL Enterprise';
        } elseif (strpos($versionString, 'community') !== false) {
            return 'MySQL Community';
        } else {
            return 'MySQL';
        }
    }

    /**
     * Get detailed version information
     *
     * @return array Version details
     */
    public function getVersionDetails(): array
    {
        try {
            $host = $this->config['services']['MySQL']['host'] ?? 'localhost';
            $port = $this->config['services']['MySQL']['port'] ?? 3306;
            $user = $this->config['services']['MySQL']['user'] ?? 'root';
            $pass = $this->config['services']['MySQL']['password'] ?? '';

            $connection = new MySQLConnection($host, $port, $user, $pass);
            $pdo = $connection->getPDO();

            $details = [];

            // Get version components
            $versionComponents = $pdo->query("SELECT @@version")->fetchColumn();
            $details[] = "Full Version: " . $versionComponents;

            // Get compile information
            $compileInfo = $pdo->query("SHOW VARIABLES LIKE 'version_compile%'")->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($compileInfo as $info) {
                $details[] = $info['Variable_name'] . ": " . $info['Value'];
            }

            // Get system information
            $systemInfo = $pdo->query("SELECT @@version_comment as comment, @@version_compile_machine as machine, @@version_compile_os as os")->fetch(\PDO::FETCH_ASSOC);
            if (!empty($systemInfo['comment'])) {
                $details[] = "Version Comment: " . $systemInfo['comment'];
            }
            if (!empty($systemInfo['machine'])) {
                $details[] = "Compile Machine: " . $systemInfo['machine'];
            }
            if (!empty($systemInfo['os'])) {
                $details[] = "Compile OS: " . $systemInfo['os'];
            }

            return $details;

        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Check client-server version compatibility
     *
     * @return array Compatibility information
     */
    public function checkCompatibility(): array
    {
        $serverVersion = $this->getServerVersion();
        $clientVersion = $this->getClientVersion();

        if (!$serverVersion['success'] || !$clientVersion['success']) {
            return [
                'compatible' => false,
                'server' => $serverVersion['success'] ? $serverVersion['version'] : 'Unknown',
                'client' => $clientVersion['success'] ? $clientVersion['version'] : 'Unknown',
                'reason' => 'Unable to retrieve version information'
            ];
        }

        // Extract major.minor version numbers
        $serverMajorMinor = $this->extractMajorMinorVersion($serverVersion['raw']);
        $clientMajorMinor = $this->extractMajorMinorVersion($clientVersion['raw']);

        $compatible = ($serverMajorMinor === $clientMajorMinor);

        return [
            'compatible' => $compatible,
            'server' => $serverVersion['version'],
            'client' => $clientVersion['version'],
            'server_major_minor' => $serverMajorMinor,
            'client_major_minor' => $clientMajorMinor,
            'reason' => $compatible ? 'Versions match' : 'Version mismatch'
        ];
    }

    /**
     * Extract major.minor version from version string
     */
    private function extractMajorMinorVersion(string $versionString): string
    {
        if (preg_match('/(\d+\.\d+)/', $versionString, $matches)) {
            return $matches[1];
        }
        return '0.0';
    }

    /**
     * Get simple version string (for status display)
     */
    public function getSimpleVersion(): string
    {
        $serverVersion = $this->getServerVersion();
        
        if ($serverVersion['success']) {
            return $serverVersion['version'];
        }
        
        return "Version not detected";
    }

    /**
     * Check if version meets minimum requirement
     *
     * @param string $minVersion Minimum required version (e.g., '5.7', '8.0')
     * @return array Check result
     */
    public function checkVersionRequirement(string $minVersion): array
    {
        $serverVersion = $this->getServerVersion();
        
        if (!$serverVersion['success']) {
            return [
                'meets_requirement' => false,
                'current_version' => 'Unknown',
                'required_version' => $minVersion,
                'reason' => 'Unable to retrieve server version'
            ];
        }

        $currentMajorMinor = $this->extractMajorMinorVersion($serverVersion['raw']);
        $meetsRequirement = version_compare($currentMajorMinor, $minVersion, '>=');

        return [
            'meets_requirement' => $meetsRequirement,
            'current_version' => $serverVersion['version'],
            'required_version' => $minVersion,
            'current_major_minor' => $currentMajorMinor,
            'reason' => $meetsRequirement ? 
                "Current version ($currentMajorMinor) meets requirement ($minVersion)" :
                "Current version ($currentMajorMinor) does not meet requirement ($minVersion)"
        ];
    }
}