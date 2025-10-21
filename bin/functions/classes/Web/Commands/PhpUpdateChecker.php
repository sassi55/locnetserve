<?php
//-------------------------------------------------------------
// PhpUpdateChecker.php - PHP Update Command for LocNetServe
//-------------------------------------------------------------
// Checks the latest available PHP version online and compares
// it with the locally installed version.
//
// Usage: lns php update
//
// Author : Sassi Souid
// Project: LocNetServe
// Version: 1.0.0
//-------------------------------------------------------------

namespace Web\Commands;

use Core\Commands\Command;

class PhpUpdateChecker implements Command
{
    private array $colors;
    private array $config;
    private string $apiUrl = 'https://php.watch/api/v1/versions/latest';
    private int $timeout = 5;

    /**
     * PhpUpdateChecker constructor.
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
     * Execute the PHP update check.
     *
     * @param string $action The action being executed ("update")
     * @param array $args Additional arguments (not used here)
     * @return string The formatted result message
     */
    public function execute(string $action, array $args = []): string
    {
        $output = PHP_EOL . $this->colors['CYAN'] . "Checking for PHP updates..." . $this->colors['RESET'] . PHP_EOL;

        $local = $this->getLocalVersion();
        $remoteInfo = $this->fetchRemoteVersion();

        if (!$remoteInfo) {
            return $this->colors['RED'] . "Unable to fetch remote PHP version. Please check your network or try again later." . $this->colors['RESET'];
        }

        $remoteVer = $remoteInfo['version'];

        $output .= "Local version : " . $this->colors['YELLOW'] . $local . $this->colors['RESET'] . PHP_EOL;
        $output .= "Latest version: " . $this->colors['GREEN'] . $remoteVer . $this->colors['RESET'] . PHP_EOL . PHP_EOL;

        if (version_compare($local, $remoteVer, '<')) {
            $output .= $this->colors['YELLOW'] . "A new PHP version is available: $remoteVer (current: $local)." . $this->colors['RESET'] . PHP_EOL;
            $output .= "Visit " . $this->colors['CYAN'] . "https://windows.php.net/download/" . $this->colors['RESET'] . " to download the latest release." . PHP_EOL;
        } elseif (version_compare($local, $remoteVer, '==')) {
            $output .= $this->colors['GREEN'] . "You are already running the latest PHP version: $local." . $this->colors['RESET'] . PHP_EOL;
        } else {
            $output .= $this->colors['MAGENTA'] . "Your PHP version ($local) is newer than the latest stable release ($remoteVer)." . $this->colors['RESET'] . PHP_EOL;
        }

        return $output;
    }

    /**
     * Get the current local PHP version.
     *
     * @return string
     */
    private function getLocalVersion(): string
    {
        return phpversion();
    }

    /**
     * Fetch the latest PHP version from the official API.
     *
     * @return array|null
     */
    private function fetchRemoteVersion(): ?array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'User-Agent: LocNetServe-PHP-UpdateChecker/2.0.0',
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            curl_close($ch);
            return null;
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            return null;
        }

        $data = json_decode($response, true);
        if (!is_array($data) || !isset($data['data']['name'])) {
            return null;
        }

        return [
            'version' => $data['data']['name'],
            'raw' => $data['data'],
        ];
    }

    /**
     * Get help information for this command.
     *
     * @return string
     */
    public function getHelp(): string
    {
        return $this->colors['GREEN'] . "php update" . $this->colors['RESET'] .
               " - Check for the latest PHP version online and compare with the local version." . PHP_EOL .
               $this->colors['CYAN'] . "Usage:" . $this->colors['RESET'] . PHP_EOL .
               "  lns php update" . PHP_EOL .
               "  lns php update --force" . PHP_EOL .
               PHP_EOL . $this->colors['YELLOW'] . "Note: Internet connection required." . $this->colors['RESET'];
    }
}
