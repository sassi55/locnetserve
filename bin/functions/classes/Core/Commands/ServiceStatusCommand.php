<?php
/**
 * -------------------------------------------------------------
 *  ServiceStatusCommand.php - Service Status Command for LocNetServe
 * -------------------------------------------------------------
 *  Provides comprehensive service monitoring by:
 *    - Displaying detailed status for all LocNetServe services
 *    - Showing PID, ports, versions, and IP addresses
 *    - Generating service health summaries with color indicators
 *    - Integrating Apache, MySQL, and server status information
 *
 *  Author : Sassi Souid
 *  Email  : locnetserve@gmail.com
 *  Project: LocNetServe
 *  Version: 1.0.0
 * -------------------------------------------------------------
 */

namespace Core\Commands;

use Core\Commands\Command;
use Web\Commands\ApacheCommand;
use Database\Commands\MySQLCommandHandler;
use Core\Utils\ServerManager;

class ServiceStatusCommand implements Command
{
    private array $colors;
    private array $config;
    private ServerManager $server;
    private ApacheCommand $apache;
    private MySQLCommandHandler $mysql;

    /**
     * ServiceStatusCommand constructor.
     *
     * @param array $colors Color configuration for CLI output
     * @param array $config Application configuration
     */
    public function __construct(array $colors, array $config)
    {
        $this->colors = $colors;
        $this->config = $config;
        $this->server = new ServerManager();
        $this->apache = new ApacheCommand($colors, $config);
        $this->mysql = new MySQLCommandHandler($colors, $config);
    }

    /**
     * Execute service status command.
     *
     * @param string $action The action to perform
     * @param array $args Command arguments
     * @return string Detailed status information
     */
    public function execute(string $action, array $args = []): string
    {
        return $this->getDetailedStatus();
    }

    /**
     * Get detailed status for all services.
     *
     * @return string Formatted detailed status
     */
    public function getDetailedStatus(): string
    {
        $output = [];

        // --- LocNetServe ---
        $output[] = $this->colors['BOLD'] . "LocNetServe:" . $this->colors['RESET'];
        $pid = $this->server->getPid();
        $running = $this->server->isRunning();
        $output[] = "  Status : " . ($running ? $this->colors['GREEN'] . "Running" . $this->colors['RESET'] : $this->colors['MAGENTA'] . "Stopped" . $this->colors['RESET']);
        $output[] = "  PID    : " . ($pid ?: "N/A");
        $output[] = "  Version: " . ($this->config['myserver']['version'] ?? "unknown");
        $output[] = "  IP     : " . ($this->config['myserver']['ip'] ?? "127.0.0.1");
        $output[] = "  Port   : " . $this->server->getLocNetServePort();

        $output[] = ""; // Empty line for separation

        // --- Apache ---
        $output[] = $this->colors['BOLD'] . "Apache:" . $this->colors['RESET'];
        $apache_pid = $this->apache->getPid();
        $apache_running = $this->apache->execute('status');
        
        $output[] = "  Status : " . $apache_running;
        $output[] = "  PID    : " . ($apache_pid ?: "N/A");
        $output[] = "  IP     : " . $this->apache->getIP();
        $output[] = "  Port   : " . $this->apache->getPort();
        $output[] = "  Version: " . $this->apache->getVersion();

        $output[] = ""; // Empty line for separation

        // --- MySQL ---
        $mysql_pid = $this->mysql->execute('getpid');
        $mysql_running = $this->mysql->execute('status');
        $output[] = $this->colors['BOLD'] . "MySQL:" . $this->colors['RESET'];
        $output[] = "  Status : " . $mysql_running;
        $output[] = "  PID    : " . ($mysql_pid ?: "N/A");
        $output[] = "  IP     : " . $this->mysql->execute('getip');
        $output[] = "  Port   : " . $this->mysql->execute('ports');
        $output[] = "  Version: " . $this->mysql->execute('version');

        // Summary
        $output[] = "";
        $output[] = $this->colors['CYAN'] . "=== Service Summary ===" . $this->colors['RESET'];
        $output[] = $this->getServiceSummary();

        return implode(PHP_EOL, $output);
    }

    /**
     * Get service summary with color indicators.
     *
     * @return string Service summary
     */
    private function getServiceSummary(): string
    {
        $services = [
            'LocNetServe' => $this->server->isRunning(),
            'Apache' => (bool)$this->apache->getPid(),
            'MySQL' => (bool)$this->mysql->execute('getpid')
        ];

        $summary = [];
        foreach ($services as $name => $running) {
            $status = $running ? 
                $this->colors['GREEN'] . '● Running' . $this->colors['RESET'] : 
                $this->colors['RED'] . '● Stopped' . $this->colors['RESET'];
            $summary[] = "$name: $status";
        }

        return implode(' | ', $summary);
    }

    /**
     * Get help information for status command.
     *
     * @return string Help message
     */
    public function getHelp(): string
    {
        return $this->colors['GREEN'] . "lns status detail" . $this->colors['RESET'] . 
               " - Show detailed service status information" . PHP_EOL .
               $this->colors['CYAN'] . "Displays:" . $this->colors['RESET'] . PHP_EOL .
               "  • Service status and PID" . PHP_EOL .
               "  • IP addresses and ports" . PHP_EOL .
               "  • Version information" . PHP_EOL .
               "  • Service health summary" . PHP_EOL .
               $this->colors['YELLOW'] . "Use this command for comprehensive service monitoring." . $this->colors['RESET'];
    }
}