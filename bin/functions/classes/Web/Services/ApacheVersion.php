<?php
//================================================================================
// ApacheVersion.php - Apache Version Service for LocNetServe
//================================================================================
// 
//     ██╗     ███╗   ██╗███████╗
//    ██║     ██╔██╗  ██║██╔════╝
//    ██║     ██║╚██╗ ██║███████╗
//    ██║     ██║ ╚████║╚════██║
//    ███████╗██║  ╚███║███████║
//    ╚══════╝╚═╝   ╚══╝╚══════╝
//
// Handles Apache web server version detection and module information.
// Provides detailed version information, module status, and server capabilities.
//
// FEATURES:
// ‚ÄĘ Apache version detection and display
// ‚ÄĘ Module list and status information
// ‚ÄĘ Compilation information and build date
// ‚ÄĘ Server capabilities and features
// ‚ÄĘ Version compatibility checking
// ‚ÄĘ Module dependency analysis
//
// Author      : Sassi Souid
// Email       : locnetserve@gmail.com
// Project     : LocNetServe
// Version     : 1.0.0
// Created     : 2025
// Last Update : 2025
// License     : MIT
//================================================================================

namespace Web\Services;

use Database\Formatters\DatabaseFormatter;

class ApacheVersion
{
    private array $config;
    private DatabaseFormatter $formatter;
    private array $colors;

    /**
     * ApacheVersion constructor.
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
	public function version(){
		
		return $this->colors['GREEN'] . $this->getVersion(). $this->colors['RESET'];
	}
    /**
     * Get Apache version from executable.
     *
     * @return string Version information
     */
    private function getVersion(): string
    {
        $apache_exe = $this->services['Apache']['exe'] ?? '';
        
        if (empty($apache_exe) || !file_exists($apache_exe)) {
            // Try common Apache executable names
            $common_paths = [
                $this->config['services']['Apache']['exe']?? '' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'httpd.exe',
                $this->config['paths']['apache'] ?? '' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'apache.exe',
                '/usr/sbin/apache2',
                '/usr/sbin/httpd',
                '/usr/local/apache2/bin/httpd'
            ];
            
            foreach ($common_paths as $path) {
                if (file_exists($path)) {
                    $apache_exe = $path;
                    break;
                }
            }
        }

        if (empty($apache_exe) || !file_exists($apache_exe)) {
            return "Apache executable not found";
        }

        $output = [];
        exec('"' . $apache_exe . '" -v', $output);
        return !empty($output) ? trim($output[0]) : "Version not detected";
    }
}