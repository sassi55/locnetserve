<?php
//-------------------------------------------------------------
// PHPInfoCommand.php - PHP Info Command for LocNetServe
//-------------------------------------------------------------
// Handles phpinfo() display in browser and CLI PHP information.
// Provides easy access to comprehensive PHP configuration.
//
// Author : Sassi Souid
// Project: LocNetServe
// Version: 1.0.0
//-------------------------------------------------------------

namespace Web\Commands;

use Core\Commands\Command;

class PHPInfoCommand implements Command
{
    private array $colors;
    private array $config;

    public function __construct(array $colors, array $config)
    {
        $this->colors = $colors;
        $this->config = $config;
    }

    public function execute(string $action, array $args = []): string
    {
        return $this->openPHPInfo();
    }

    public function openPHPInfo(): string
    {
        $php_exe = $this->getPHPExecutable();
        
        if (empty($php_exe) || !file_exists($php_exe)) {
            return $this->colors['RED'] . "PHP executable not found." . $this->colors['RESET'];
        }

        // Create a temporary phpinfo file
        $www_root = $this->config['paths']['www'] ?? (BASE_DIR . DIRECTORY_SEPARATOR . 'www');
        $info_file = $www_root . DIRECTORY_SEPARATOR . 'phpinfo.php';

        // Create phpinfo file
        file_put_contents($info_file, '<?php phpinfo(); ?>');

        // Get Apache port for URL construction
        $apache_port = $this->getApachePort();
        $url = "http://localhost:" . $apache_port . "/phpinfo.php";

        // Open in browser
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            exec("start $url");
        } else {
            exec("xdg-open \"$url\" >/dev/null 2>&1 &");
        }

        $result = $this->colors['GREEN'] . "PHP info opened in browser: " . $this->colors['RESET'] . PHP_EOL;
        $result .= $this->colors['CYAN'] . $url . $this->colors['RESET'] . PHP_EOL . PHP_EOL;
        $result .= $this->colors['YELLOW'] . "Note: The phpinfo.php file will be created in your www directory." . $this->colors['RESET'];

        return $result;
    }

    private function getPHPExecutable(): string
    {
        return $this->config['services']['PHP']['exe'] ?? 
               $this->config['paths']['php'] . DIRECTORY_SEPARATOR . 'php.exe' ?? 
               '';
    }

    private function getApachePort(): string
    {
        // Try to get Apache port from configuration or detect it
        $apache_port = $this->config['services']['Apache']['port'] ?? '80';
        
        if ($apache_port === '80') {
            // Try to detect actual port from running Apache
            exec('netstat -ano | findstr :80 | findstr LISTENING', $output);
            if (!empty($output)) {
                return '80';
            }
            // Fallback to common ports
            return '8080';
        }
        
        return $apache_port;
    }

    public function getHelp(): string
    {
        return $this->colors['GREEN'] . "php info" . $this->colors['RESET'] . 
               " - Open phpinfo() in web browser" . PHP_EOL .
               $this->colors['CYAN'] . "Usage: lns php info" . $this->colors['RESET'] . PHP_EOL .
               "Creates a temporary phpinfo.php file and opens it in your default browser" . PHP_EOL .
               $this->colors['YELLOW'] . "Shows: PHP configuration, loaded extensions, environment variables, and more" . $this->colors['RESET'];
    }
}