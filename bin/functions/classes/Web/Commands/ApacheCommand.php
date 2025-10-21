<?php
//================================================================================
// ApacheCommand.php - Apache Service Command Handler for LocNetServe
//================================================================================
// 
//     ██╗     ███╗   ██╗███████╗
//    ██║     ██╔██╗  ██║██╔════╝
//    ██║     ██║╚██╗ ██║███████╗
//    ██║     ██║ ╚████║╚════██║
//    ███████╗██║  ╚███║███████║
//    ╚══════╝╚═╝   ╚══╝╚══════╝
//
// Handles comprehensive Apache web server management operations using specialized 
// services. Provides a clean and unified command interface for all Apache-related
// operations including service monitoring, configuration management, log access,
// and network configuration.
//
// FEATURES:
// • Service status monitoring and health checks
// • Configuration file management and validation
// • Log file access and directory navigation
// • Port and network configuration display
// • Virtual hosts configuration management
// • Version information and module status
// • Configuration syntax validation
//
// Author      : Sassi Souid
// Email       : locnetserve@gmail.com
// Project     : LocNetServe
// Version     : 1.0.0
// Created     : 2025
// Last Update : 2025
// License     : MIT
//================================================================================

namespace Web\Commands;

use Core\Commands\Command;
use Database\Formatters\DatabaseFormatter;
use Web\Services\ApacheStatus;
use Web\Services\ApacheLog;
use Web\Services\ApacheConfig;
use Web\Services\ApachePorts;
use Web\Services\ApacheVersion;

class ApacheCommand implements Command
{
    private array $colors;
    private array $config;
    private DatabaseFormatter $formatter;
    private ApacheStatus $apacheStatus;
    private ApacheLog $apacheLog;
    private ApacheConfig $apacheConfig;
    private ApachePorts $apachePorts;
    private ApacheVersion $apacheVersion;

    /**
     * ApacheCommand constructor.
     *
     * @param array $colors Color configuration for CLI output
     * @param array $config Application configuration
     */
    public function __construct(array $colors, array $config)
    {
        $this->colors = $colors;
        $this->config = $config;
        $this->formatter = new DatabaseFormatter($colors);
        $this->apacheStatus = new ApacheStatus($config, $this->formatter,$this->colors);
        $this->apacheLog = new ApacheLog($config, $this->formatter,$this->colors);
        $this->apacheConfig = new ApacheConfig($config, $this->formatter,$this->colors);
        $this->apachePorts = new ApachePorts($config, $this->formatter,$this->colors);
        $this->apacheVersion = new ApacheVersion($config, $this->formatter,$this->colors);
    }

    /**
     * Execute Apache service command.
     *
     * @param string $action The action to perform (status/log/conf/vhosts/ports/version/config)
     * @param array $args Command arguments
     * @return string Result message
     */
    public function execute(string $action, array $args = []): string
    {
        switch ($action) {
            case 'status':
                return $this->apacheStatus->getStatus();

            case 'log':
                return $this->apacheLog->openLogFolder();

            case 'conf':
            case 'config':
                if (in_array('check', $args)) {
                    return $this->apacheConfig->checkConfig();
                }
                return $this->apacheConfig->openMainConfig();

            case 'vhosts':
            case 'virtualhosts':
                return $this->apacheConfig->openVirtualHosts();

            case 'ports':
                return $this->apachePorts->getPortInfo();

            case 'version':
                return $this->apacheVersion->version();

            case 'config-check':
            case 'check-config':
                return $this->apacheConfig->checkConfiguration();
            case "pid":
			   return $this->apacheStatus->getPid() ;
            default:
                return $this->formatter->formatError("Unknown Apache action: $action") . PHP_EOL . $this->getHelp();
        }
    }

    /**
     * Get help information for Apache commands.
     *
     * @return string Help message
     */
    public function getHelp(): string
    {
        return $this->colors['GREEN'] . "apache status" . $this->colors['RESET'] . 
               " - Check Apache service status" . PHP_EOL .
               $this->colors['GREEN'] . "apache log" . $this->colors['RESET'] . 
               " - Open Apache log folder" . PHP_EOL .
               $this->colors['GREEN'] . "apache conf" . $this->colors['RESET'] . 
               " - Open httpd.conf configuration file" . PHP_EOL .
               $this->colors['GREEN'] . "apache vhosts" . $this->colors['RESET'] . 
               " - Open VirtualHosts configuration file" . PHP_EOL .
               $this->colors['GREEN'] . "apache ports" . $this->colors['RESET'] . 
               " - List Apache listening ports" . PHP_EOL .
               $this->colors['GREEN'] . "apache version" . $this->colors['RESET'] . 
               " - Show Apache version" . PHP_EOL .
               $this->colors['GREEN'] . "apache config check" . $this->colors['RESET'] . 
               " - Check Apache configuration for errors" . PHP_EOL .
               $this->colors['CYAN'] . "Usage examples:" . $this->colors['RESET'] . PHP_EOL .
               "  lns apache status" . PHP_EOL .
               "  lns apache log" . PHP_EOL .
               "  lns apache conf" . PHP_EOL .
               "  lns apache vhosts" . PHP_EOL .
               "  lns apache ports" . PHP_EOL .
               "  lns apache version" . PHP_EOL .
               "  lns apache config check" . PHP_EOL .
               $this->colors['YELLOW'] . "Note: Requires Apache service to be installed and running." . $this->colors['RESET'];
    }
}