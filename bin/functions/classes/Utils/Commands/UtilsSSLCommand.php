<?php
//-------------------------------------------------------------
// UtilsSSLCommand.php - SSL Certificate Generator for LocNetServe
//-------------------------------------------------------------
// Handles "-u ssl gen" command.
// Generates a self-signed SSL certificate for localhost using OpenSSL.
//
// Author  : Sassi Souid
// Email   : locnetserve@gmail.com
// Project : LocNetServe
// Version : 1.0.0
//-------------------------------------------------------------

namespace Utils\Commands;

use Core\Commands\Command;

class UtilsSSLCommand implements Command
{
    private array $colors;
    private array $config;

    public function __construct(array $colors = [], array $config = [])
    {
        $this->colors = $colors;
        $this->config = $config;
    }

    /**
     * Execute the SSL command.
     */
    public function execute(string $action, array $args = []): string
    {
        if ($action !== 'gen') {
            return $this->colors['YELLOW'] . "Usage: lns -u ssl gen" . $this->colors['RESET'];
        }

        return $this->generateSSL();
    }

    /**
     * Generate a self-signed SSL certificate for localhost.
     */
    private function generateSSL(): string
    {
        $sslDir = $this->config['paths']['base'] . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'ssl';
        $certFile = $sslDir . DIRECTORY_SEPARATOR . 'localhost.crt';
        $keyFile  = $sslDir . DIRECTORY_SEPARATOR . 'localhost.key';

        // Ensure SSL directory exists
        if (!is_dir($sslDir)) {
            mkdir($sslDir, 0755, true);
        }

        // Build OpenSSL command (Windows-friendly)
        $cmd = sprintf(
            'openssl req -x509 -nodes -days 365 -newkey rsa:2048 -keyout "%s" -out "%s" -subj "/CN=localhost"',
            $keyFile,
            $certFile
        );

        // Execute silently
        exec($cmd . " 2>&1", $output, $resultCode);

        if ($resultCode !== 0) {
            return $this->colors['RED'] .
                "Error generating SSL certificate." . PHP_EOL .
                "Ensure OpenSSL is installed and accessible from PATH." . $this->colors['RESET'];
        }

        return $this->colors['GREEN'] .
            "Self-signed SSL certificate generated successfully!" . PHP_EOL .
            "Certificate: $certFile" . PHP_EOL .
            "Private Key: $keyFile" . $this->colors['RESET'];
    }

    /**
     * Display help message.
     */
    public function getHelp(): string
    {
        return <<<HELP
{$this->colors['CYAN']}SSL Commands (-u ssl):{$this->colors['RESET']}

  -u ssl gen         Generate self-signed SSL certificate for localhost

HELP;
    }
}
