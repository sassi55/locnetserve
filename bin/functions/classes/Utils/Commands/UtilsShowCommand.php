<?php
//-------------------------------------------------------------
// UtilsShowCommand.php - Show Command Handler for LocNetServe
//-------------------------------------------------------------
// Handles all "show" related utils commands such as ports, config, etc.
//
// Author  : Sassi Souid
// Email   : locnetserve@gmail.com
// Project : LocNetServe
// Version : 1.0.0
//-------------------------------------------------------------

namespace Utils\Commands;

use Core\Commands\Command;

class UtilsShowCommand implements Command
{
    private array $colors;
    private array $config;

    public function __construct(array $colors = [], array $config = [])
    {
        $this->colors = $colors;
        $this->config = $config;
    }

    /**
     * Execute show-related commands
     */
    public function execute(string $action, array $args = []): string
    {
        switch ($action) {
            case 'ports':
                return $this->showPorts();

            case 'config':
                return $this->showConfig($args);

            case 'services':
                return $this->showServices();

            default:
                return $this->colors['RED'] . "Unknown show command: $action" . $this->colors['RESET'] . PHP_EOL .
                       $this->getHelp();
        }
    }

    /**
     * Show all active ports and their services
     */
    private function showPorts(): string
    {
        $output = $this->colors['CYAN'] . "Active Network Ports - LocNetServe" . $this->colors['RESET'] . PHP_EOL;
        $output .= str_repeat('=', 60) . PHP_EOL;
        
        // Get active ports using netstat
        $ports = $this->getActivePorts();
        
        if (empty($ports)) {
            return $this->colors['YELLOW'] . "No active ports found or netstat not available." . $this->colors['RESET'];
        }
        
        // Header
        $output .= $this->colors['YELLOW'] . 
                  sprintf("%-8s %-6s %-25s %s", "PORT", "PROTO", "SERVICE", "STATUS") . 
                  $this->colors['RESET'] . PHP_EOL;
        $output .= str_repeat('-', 60) . PHP_EOL;
        
        foreach ($ports as $port) {
            $output .= $this->formatPortInfo($port);
        }
        
        $output .= PHP_EOL . $this->colors['GREEN'] . "Total active ports: " . count($ports) . $this->colors['RESET'];
        
        return $output;
    }

    /**
     * Show configuration information
     */
    private function showConfig(array $args): string
    {
        $section = $args[0] ?? 'all';
        
        switch ($section) {
            case 'mysql':
                return $this->showMySQLConfig();
            case 'apache':
                return $this->showApacheConfig();
            case 'php':
                return $this->showPHPConfig();
            case 'all':
            default:
                return $this->showAllConfig();
        }
    }

    /**
     * Show services status
     */
    private function showServices(): string
    {
        $output = $this->colors['CYAN'] . "LocNetServe Services Status" . $this->colors['RESET'] . PHP_EOL;
        $output .= str_repeat('=', 50) . PHP_EOL;
        
        $services = [
            ['name' => 'Apache', 'port' => '80/443', 'status' => $this->checkService('apache')],
            ['name' => 'MySQL', 'port' => '3306', 'status' => $this->checkService('mysql')],
            ['name' => 'PHP', 'port' => '9000', 'status' => $this->checkService('php')]
        ];
        
        foreach ($services as $service) {
            $statusColor = $service['status'] ? 'GREEN' : 'RED';
            $statusText = $service['status'] ? 'RUNNING' : 'STOPPED';
            
            $output .= sprintf("%-15s %-12s %s%s%s" . PHP_EOL,
                $service['name'],
                $service['port'],
                $this->colors[$statusColor],
                $statusText,
                $this->colors['RESET']
            );
        }
        
        return $output;
    }

    /**
     * Get active ports using netstat command
     */
    private function getActivePorts(): array
    {
        $ports = [];
        
        // Command to get listening ports on Windows
        $command = "netstat -ano | findstr LISTENING";
        exec($command, $output, $returnCode);
     
        if ($returnCode === 0 && !empty($output)) {
            $ports = $this->parseNetstatOutput($output);
        }
        
        return $ports;
    }

    /**
     * Parse netstat output to extract port information
     */
    private function parseNetstatOutput(array $output): array
{
    $ports = [];
    $commonServices = $this->getCommonServices();
   
    foreach ($output as $line) {
        $line = trim($line);
        
        // Debug: Afficher la ligne pour analyse
        // echo "Processing line: $line" . PHP_EOL;
        
        // Pattern pour capturer le port dans différentes formats de netstat
        // Format 1: TCP    0.0.0.0:80             0.0.0.0:0              LISTENING
        // Format 2: TCP    127.0.0.1:3306         0.0.0.0:0              LISTENING
        // Format 3: TCP    [::]:80                [::]:0                 LISTENING
        
        if (preg_match('/TCP\s+[^\s]+\:(\d+)\s+/', $line, $matches)) {
            $port = $matches[1];
            
            // Debug: Afficher le port capturé
            // echo "Found port: $port" . PHP_EOL;
            
            // Filtrer les ports (afficher les ports communs et les ports au-dessus de 1000)
            if ($port > 1000 || isset($commonServices[$port])) {
                $service = $commonServices[$port] ?? 'Unknown';
                $pid = $this->extractPID($line);
                
                $ports[$port] = [
                    'port' => $port,
                    'protocol' => 'TCP',
                    'service' => $service,
                    'status' => 'LISTENING',
                    'pid' => $pid
                ];
            }
        }
    }
    
    // Trier par numéro de port
    ksort($ports);
    
    return array_values($ports);
}

   /**
 * Get common services mapping
 */
private function getCommonServices(): array
{
    return [
        '21' => 'FTP',
        '22' => 'SSH',
        '23' => 'Telnet',
        '25' => 'SMTP',
        '53' => 'DNS',
        '80' => 'Apache HTTP',
        '110' => 'POP3',
        '143' => 'IMAP',
        '443' => 'Apache HTTPS',
        '993' => 'IMAPS',
        '995' => 'POP3S',
        '1433' => 'SQL Server',
        '1521' => 'Oracle DB',
        '3306' => 'MySQL Database',
        '3307' => 'MySQL (Alt 1)',
        '3308' => 'MySQL (Alt 2)',
        '3309' => 'MySQL (Alt 3)',
        '3389' => 'Remote Desktop',
        '5432' => 'PostgreSQL',
        '5500' => 'VNC Server',
        '5601' => 'Kibana',
        '6379' => 'Redis',
        '8000' => 'HTTP Development',
        '8080' => 'HTTP Alternative',
        '8081' => 'HTTP Alternative 2',
        '8443' => 'HTTPS Alternative',
        '9000' => 'PHP-FPM',
        '9001' => 'PHP-FPM (Alt 1)',
        '9002' => 'PHP-FPM (Alt 2)',
        '9200' => 'Elasticsearch',
        '9300' => 'Elasticsearch Cluster',
        '11211' => 'Memcached',
        '27017' => 'MongoDB',
        '33060' => 'MySQL X Protocol'
    ];
}
    /**
     * Extract PID from netstat line
     */
    private function extractPID(string $line): string
    {
        if (preg_match('/LISTENING\s+(\d+)$/', $line, $matches)) {
            return $matches[1];
        }
        return 'N/A';
    }

	/**
	 * Format port information for display
	 */
	private function formatPortInfo(array $port): string
	{
		$color = $this->getPortColor($port['port']);
		$serviceColor = $this->getServiceColor($port['service']);
		
		return sprintf("%-8s %-6s %s%-25s%s %s%s%s" . PHP_EOL,
			$port['port'],
			$port['protocol'],
			$this->colors[$serviceColor],
			$port['service'],
			$this->colors['RESET'],
			$this->colors[$color],
			$port['status'],
			$this->colors['RESET']
		);
	}

	/**
	 * Get color for service based on type
	 */
	private function getServiceColor(string $service): string
	{
		if (strpos($service, 'Apache') !== false) {
			return 'GREEN';
		} elseif (strpos($service, 'MySQL') !== false) {
			return 'CYAN';
		} elseif (strpos($service, 'PHP') !== false) {
			return 'MAGENTA';
		} else {
			return 'YELLOW';
		}
	}
	
	
	/**
 * Get color for port based on service type
 */
private function getPortColor(string $port): string
{
    $webPorts = ['80', '443', '8080', '8081', '8443', '8000'];
    $mysqlPorts = ['3306', '3307', '3308', '3309', '33060'];
    $phpPorts = ['9000', '9001', '9002'];
    $databasePorts = ['1433', '1521', '5432', '27017', '6379', '11211'];
    $systemPorts = ['21', '22', '23', '25', '53', '110', '143', '993', '995', '3389'];
    
    if (in_array($port, $webPorts)) {
        return 'GREEN';      // Vert pour les services web
    } elseif (in_array($port, $mysqlPorts)) {
        return 'CYAN';       // Cyan pour MySQL
    } elseif (in_array($port, $phpPorts)) {
        return 'MAGENTA';    // Magenta pour PHP
    } elseif (in_array($port, $databasePorts)) {
        return 'BLUE';       // Bleu pour autres bases de données
    } elseif (in_array($port, $systemPorts)) {
        return 'YELLOW';     // Jaune pour les services système
    } else {
        return 'WHITE';      // Blanc pour les ports inconnus
    }
}
    /**
     * Check if a service is running
     */
    private function checkService(string $service): bool
    {
        switch ($service) {
            case 'apache':
                return $this->isPortActive('80') || $this->isPortActive('443');
            case 'mysql':
                return $this->isPortActive('3306');
            case 'php':
                return $this->isPortActive('9000');
            default:
                return false;
        }
    }

    /**
     * Check if a specific port is active
     */
    private function isPortActive(string $port): bool
    {
        $command = "netstat -ano | findstr :{$port} | findstr LISTENING";
        exec($command, $output, $returnCode);
        
        return ($returnCode === 0 && !empty($output));
    }

    /**
     * Show all configuration
     */
    private function showAllConfig(): string
    {
        $output = $this->colors['CYAN'] . "LocNetServe Configuration Summary" . $this->colors['RESET'] . PHP_EOL;
        $output .= str_repeat('=', 50) . PHP_EOL;
        
        $output .= $this->showMySQLConfig() . PHP_EOL;
        $output .= $this->showApacheConfig() . PHP_EOL;
        $output .= $this->showPHPConfig();
        
        return $output;
    }

    /**
     * Show MySQL configuration
     */
    private function showMySQLConfig(): string
    {
        $mysqlConfig = $this->config['mysql'] ?? $this->config['database'] ?? [];
        
        $output = $this->colors['YELLOW'] . "MySQL Configuration:" . $this->colors['RESET'] . PHP_EOL;
        $output .= sprintf("  Host: %s\n", $mysqlConfig['host'] ?? 'localhost');
        $output .= sprintf("  Port: %s\n", $mysqlConfig['port'] ?? '3306');
        $output .= sprintf("  User: %s\n", $mysqlConfig['user'] ?? 'root');
        $output .= sprintf("  Status: %s\n", $this->checkService('mysql') ? 
            $this->colors['GREEN'] . 'RUNNING' . $this->colors['RESET'] : 
            $this->colors['RED'] . 'STOPPED' . $this->colors['RESET']);
        
        return $output;
    }

    /**
     * Show Apache configuration
     */
    private function showApacheConfig(): string
    {
        $output = $this->colors['YELLOW'] . "Apache Configuration:" . $this->colors['RESET'] . PHP_EOL;
        $output .= sprintf("  HTTP Port: 80\n");
        $output .= sprintf("  HTTPS Port: 443\n");
        $output .= sprintf("  Status: %s\n", $this->checkService('apache') ? 
            $this->colors['GREEN'] . 'RUNNING' . $this->colors['RESET'] : 
            $this->colors['RED'] . 'STOPPED' . $this->colors['RESET']);
        
        return $output;
    }

    /**
     * Show PHP configuration
     */
    private function showPHPConfig(): string
    {
        $output = $this->colors['YELLOW'] . "PHP Configuration:" . $this->colors['RESET'] . PHP_EOL;
        $output .= sprintf("  FPM Port: 9000\n");
        $output .= sprintf("  Status: %s\n", $this->checkService('php') ? 
            $this->colors['GREEN'] . 'RUNNING' . $this->colors['RESET'] : 
            $this->colors['RED'] . 'STOPPED' . $this->colors['RESET']);
        
        return $output;
    }

    /**
     * Display help information for show commands
     */
    public function getHelp(): string
    {
        return <<<HELP
{$this->colors['CYAN']}Show Commands (-u show):{$this->colors['RESET']}

  -u show ports                 Show all active network ports
  -u show services              Show LocNetServe services status
  -u show config                Show all configuration
  -u show config mysql          Show MySQL configuration
  -u show config apache         Show Apache configuration
  -u show config php            Show PHP configuration

HELP;
    }
}