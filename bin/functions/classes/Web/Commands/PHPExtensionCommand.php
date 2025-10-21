<?php
//-------------------------------------------------------------
// PHPExtensionCommand.php - PHP Extension Command for LocNetServe
//-------------------------------------------------------------
// Handles PHP extension management: listing, enabling, disabling.
// Provides comprehensive extension status and management.
//
// Author : Sassi Souid
// Project: LocNetServe
// Version: 1.0.0
//-------------------------------------------------------------

namespace Web\Commands;

use Core\Commands\Command;

class PHPExtensionCommand implements Command
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
         
		switch ($action) {
            case 'list':
                return $this->listExtensions();
            case 'enable':
                if (empty($args)) {
                    return $this->colors['RED'] . "Error: Missing extension name. Use: lns php ext enable <extension>" . $this->colors['RESET'];
                }
                return $this->enableExtension($args[0]);
            case 'disable':
                if (empty($args)) {
                    return $this->colors['RED'] . "Error: Missing extension name. Use: lns php ext disable <extension>" . $this->colors['RESET'];
                }
                return $this->disableExtension($args[0]);
            case 'available':
                return $this->listAvailableExtensions();
            default:
                return $this->colors['RED'] . "Unknown extension action: $action" . $this->colors['RESET'];
        }
    }

    public function listExtensions(): string
    {
        $php_exe = $this->getPHPExecutable();
        
        if (empty($php_exe) || !file_exists($php_exe)) {
            return $this->colors['RED'] . "PHP executable not found." . $this->colors['RESET'];
        }

        // Get loaded extensions
        exec('"' . $php_exe . '" -m', $extensions, $return_var);

        if ($return_var !== 0) {
            return $this->colors['RED'] . "Failed to get PHP extensions list." . $this->colors['RESET'];
        }

        $output = $this->colors['GREEN'] . "PHP Loaded Extensions (" . count($extensions) . "):" . $this->colors['RESET'] . PHP_EOL;
        
        // Group extensions by category (common patterns)
        $core_extensions = [];
        $database_extensions = [];
        $utility_extensions = [];
        $other_extensions = [];

        $core_patterns = ['^curl$', '^date$', '^filter$', '^hash$', '^json$', '^mbstring$', '^openssl$', '^pcre$', '^reflection$', '^session$', '^standard$', '^tokenizer$', '^xml$'];
        $db_patterns = ['^pdo_', '^mysql', '^mysqli', '^pgsql', '^sqlite', '^oci'];
        $utility_patterns = ['^gd$', '^zip$', '^zlib$', '^fileinfo$', '^ftp$', '^intl$', '^soap$'];

        foreach ($extensions as $extension) {
            $ext = strtolower(trim($extension));
            if (empty($ext)) continue;

            $matched = false;
            
            // Check core extensions
            foreach ($core_patterns as $pattern) {
                if (preg_match("/$pattern/", $ext)) {
                    $core_extensions[] = $extension;
                    $matched = true;
                    break;
                }
            }
            if ($matched) continue;

            // Check database extensions
            foreach ($db_patterns as $pattern) {
                if (preg_match("/$pattern/", $ext)) {
                    $database_extensions[] = $extension;
                    $matched = true;
                    break;
                }
            }
            if ($matched) continue;

            // Check utility extensions
            foreach ($utility_patterns as $pattern) {
                if (preg_match("/$pattern/", $ext)) {
                    $utility_extensions[] = $extension;
                    $matched = true;
                    break;
                }
            }
            if ($matched) continue;

            $other_extensions[] = $extension;
        }

        // Display extensions by category
        if (!empty($core_extensions)) {
            $output .= PHP_EOL . $this->colors['CYAN'] . "Core Extensions:" . $this->colors['RESET'] . PHP_EOL;
            $output .= $this->formatExtensionList($core_extensions);
        }

        if (!empty($database_extensions)) {
            $output .= PHP_EOL . $this->colors['CYAN'] . "Database Extensions:" . $this->colors['RESET'] . PHP_EOL;
            $output .= $this->formatExtensionList($database_extensions);
        }

        if (!empty($utility_extensions)) {
            $output .= PHP_EOL . $this->colors['CYAN'] . "Utility Extensions:" . $this->colors['RESET'] . PHP_EOL;
            $output .= $this->formatExtensionList($utility_extensions);
        }

        if (!empty($other_extensions)) {
            $output .= PHP_EOL . $this->colors['CYAN'] . "Other Extensions:" . $this->colors['RESET'] . PHP_EOL;
            $output .= $this->formatExtensionList($other_extensions);
        }

        return $output;
    }

    public function listAvailableExtensions(): string
    {
        $php_dir = $this->config['paths']['php'] ?? dirname($this->getPHPExecutable());
        $ext_dir = $php_dir . DIRECTORY_SEPARATOR . 'ext';

        if (!is_dir($ext_dir)) {
            return $this->colors['RED'] . "PHP extensions directory not found: $ext_dir" . $this->colors['RESET'];
        }

        // Get all DLL files in ext directory
        $ext_files = glob($ext_dir . DIRECTORY_SEPARATOR . '*.dll');
        
        if (empty($ext_files)) {
            return $this->colors['YELLOW'] . "No extension files found in: $ext_dir" . $this->colors['RESET'];
        }

        // Get currently loaded extensions
        $php_exe = $this->getPHPExecutable();
        exec('"' . $php_exe . '" -m', $loaded_extensions, $return_var);

        $loaded_extensions = array_map('strtolower', $loaded_extensions);

        $output = $this->colors['GREEN'] . "Available PHP Extensions:" . $this->colors['RESET'] . PHP_EOL;
        $output .= "Extensions directory: " . $this->colors['CYAN'] . $ext_dir . $this->colors['RESET'] . PHP_EOL . PHP_EOL;

        $available_extensions = [];
        foreach ($ext_files as $file) {
            $ext_name = str_replace(['php_', '.dll'], '', basename($file));
            $status = in_array(strtolower($ext_name), $loaded_extensions) ? 
                $this->colors['GREEN'] . '● Enabled' . $this->colors['RESET'] : 
                $this->colors['YELLOW'] . '○ Disabled' . $this->colors['RESET'];
            
            $available_extensions[] = [
                'name' => $ext_name,
                'status' => $status,
                'file' => basename($file)
            ];
        }

        // Sort by name
        usort($available_extensions, function($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        foreach ($available_extensions as $ext) {
            $output .= "  " . str_pad($ext['name'], 25) . " " . $ext['status'] . PHP_EOL;
        }

        $output .= PHP_EOL . $this->colors['YELLOW'] . "Total: " . count($available_extensions) . " extensions available" . $this->colors['RESET'];

        return $output;
    }

    public function enableExtension(string $extension): string
    {
      
		return $this->colors['YELLOW'] . "Extension management requires manual php.ini editing." . $this->colors['RESET'] . PHP_EOL .
               "Use: " . $this->colors['GREEN'] . "lns php ini" . $this->colors['RESET'] . " to open php.ini" . PHP_EOL .
               "Add or uncomment: " . $this->colors['CYAN'] . "extension=$extension" . $this->colors['RESET'] . PHP_EOL .
               $this->colors['YELLOW'] . "Note: Restart Apache after modifying php.ini" . $this->colors['RESET'];
    }

    public function disableExtension(string $extension): string
    {
        return $this->colors['YELLOW'] . "Extension management requires manual php.ini editing." . $this->colors['RESET'] . PHP_EOL .
               "Use: " . $this->colors['GREEN'] . "lns php ini" . $this->colors['RESET'] . " to open php.ini" . PHP_EOL .
               "Comment out: " . $this->colors['CYAN'] . ";extension=$extension" . $this->colors['RESET'] . PHP_EOL .
               $this->colors['YELLOW'] . "Note: Restart Apache after modifying php.ini" . $this->colors['RESET'];
    }

    private function formatExtensionList(array $extensions): string
    {
        sort($extensions);
        $output = '';
        $chunks = array_chunk($extensions, 4);
        
        foreach ($chunks as $chunk) {
            $line = '';
            foreach ($chunk as $ext) {
                $line .= str_pad($ext, 20);
            }
            $output .= "  " . trim($line) . PHP_EOL;
        }
        
        return $output;
    }

    private function getPHPExecutable(): string
    {
        return $this->config['services']['PHP']['exe'] ?? 
               $this->config['paths']['php'] . DIRECTORY_SEPARATOR . 'php.exe' ?? 
               '';
    }

    public function getHelp(): string
    {
        return $this->colors['GREEN'] . "php ext list" . $this->colors['RESET'] . 
               " - List all loaded PHP extensions" . PHP_EOL .
               $this->colors['GREEN'] . "php ext enable <name>" . $this->colors['RESET'] . 
               " - Enable a PHP extension" . PHP_EOL .
               $this->colors['GREEN'] . "php ext disable <name>" . $this->colors['RESET'] . 
               " - Disable a PHP extension" . PHP_EOL .
               $this->colors['GREEN'] . "php ext available" . $this->colors['RESET'] . 
               " - List available extensions with status" . PHP_EOL .
               $this->colors['CYAN'] . "Usage examples:" . $this->colors['RESET'] . PHP_EOL .
               "  lns php ext list" . PHP_EOL .
               "  lns php ext enable curl" . PHP_EOL .
               "  lns php ext dispo" . PHP_EOL .
               $this->colors['YELLOW'] . "Note: Extension changes require php.ini modification and Apache restart" . $this->colors['RESET'];
    }
}