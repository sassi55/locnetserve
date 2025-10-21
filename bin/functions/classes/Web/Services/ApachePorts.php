<?php
//================================================================================
// ApachePorts.php - Apache Ports Service for LocNetServe
//================================================================================
// 
//     ██╗     ███╗   ██╗███████╗
//    ██║     ██╔██╗  ██║██╔════╝
//    ██║     ██║╚██╗ ██║███████╗
//    ██║     ██║ ╚████║╚════██║
//    ███████╗██║  ╚███║███████║
//    ╚══════╝╚═╝   ╚══╝╚══════╝
//
// Handles Apache web server port detection and network configuration.
// Provides detailed information about Apache listening ports and network bindings.
//
// FEATURES:
// • Listening port detection and verification
// • Network interface binding information
// • SSL/TLS port configuration
// • Virtual host port mapping
// • Port conflict detection
// • Network configuration analysis
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

class ApachePorts
{
    private array $config;
    private DatabaseFormatter $formatter;
    private array $colors;

    /**
     * ApachePorts constructor.
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
     * Get detailed Apache port information.
     *
     * @return string Formatted port information
     */
    public function getPortInfo(): string
    {
        $ports = $this->getListeningPorts();
        $allConnections = $this->getAllConnections();
        
        $output = $this->formatter->formatInfo("=== Apache Port Information ===") . PHP_EOL . PHP_EOL;
        
        // Main Listening Ports
        $output .= $this->formatter->formatSuccess("▲ Listening Ports:") . PHP_EOL;
        if (empty($ports)) {
            $output .= "  " . $this->formatter->formatWarning("• No Apache listening ports detected") . PHP_EOL;
        } else {
            foreach ($ports as $portInfo) {
                $status = $portInfo['status'];
                $output .= "  " . $this->formatter->formatInfo("• ") . 
                          $this->colors['CYAN'] . $portInfo['protocol'] . $this->colors['RESET'] . " on " .
                          $this->colors['GREEN'] . $portInfo['local_address'] . $this->colors['RESET'] . " • " .
                          $this->colors['YELLOW'] . $status . $this->colors['RESET'] . PHP_EOL;
            }
        }

        $output .= PHP_EOL;

        // Default Port Information
        $output .= $this->formatter->formatSuccess("▲ Default Ports:") . PHP_EOL;
        $output .= "  " . $this->formatter->formatInfo("• HTTP: ") . $this->colors['WHITE'] . "Port 80" . $this->colors['RESET'] . PHP_EOL;
        $output .= "  " . $this->formatter->formatInfo("• HTTPS: ") . $this->colors['WHITE'] . "Port 443" . $this->colors['RESET'] . PHP_EOL;
        $output .= "  " . $this->formatter->formatInfo("• Common Alternative: ") . $this->colors['WHITE'] . "Port 8080" . $this->colors['RESET'] . PHP_EOL;

        $output .= PHP_EOL;

        // All Network Connections
        $output .= $this->formatter->formatSuccess("▲ Network Connections:") . PHP_EOL;
        if (empty($allConnections)) {
            $output .= "  " . $this->formatter->formatWarning("• No Apache network connections found") . PHP_EOL;
        } else {
            foreach ($allConnections as $connection) {
                $status = $connection['status'] === 'LISTENING' ? 'LISTENING' : ' ' . $connection['status'];
                $output .= "  " . $this->formatter->formatInfo("• ") . 
                          $this->colors['CYAN'] . $connection['local_address'] . $this->colors['RESET'] . " → " .
                          $this->colors['YELLOW'] . $connection['foreign_address'] . $this->colors['RESET'] . " • " .
                          $this->colors['GREEN'] . $status . $this->colors['RESET'] . PHP_EOL;
            }
        }

        $output .= PHP_EOL;

        // Port Analysis
        $analysis = $this->analyzePorts($ports);
        $output .= $this->formatter->formatSuccess("▲ Port Analysis:") . PHP_EOL;
        $output .= "  " . $this->formatter->formatInfo("• Total Listening Ports: ") . $this->colors['CYAN'] . $analysis['total_ports'] . $this->colors['RESET'] . PHP_EOL;
        $output .= "  " . $this->formatter->formatInfo("• HTTP Port (80): ") . 
                  ($analysis['http_port'] ? 
                   $this->colors['GREEN'] . 'Active ' . $this->colors['RESET'] : 
                   $this->colors['YELLOW'] . 'Inactive ' . $this->colors['RESET']) . PHP_EOL;
        $output .= "  " . $this->formatter->formatInfo("• HTTPS Port (443): ") . 
                  ($analysis['https_port'] ? 
                   $this->colors['GREEN'] . 'Active ' . $this->colors['RESET'] : 
                   $this->colors['YELLOW'] . 'Inactive ' . $this->colors['RESET']) . PHP_EOL;

        return $output;
    }

    /**
     * Get Apache listening ports
     *
     * @return array Array of port information
     */
    private function getListeningPorts(): array
    {
        $ports = [];
        $pids = $this->getApachePids();

        foreach ($pids as $pid) {
            $portInfo = $this->getPortsByPid($pid);
            $ports = array_merge($ports, $portInfo);
        }

        return $ports;
    }

    /**
     * Get all Apache PIDs from process list.
     *
     * @return array Array of process IDs
     */
    private function getApachePids(): array
    {
        $pids = [];
        $process_names = ['httpd.exe', 'apache.exe', 'httpd', 'apache2'];

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            foreach ($process_names as $process_name) {
                exec("tasklist /FI \"IMAGENAME eq $process_name\" /FO CSV /NH", $output);
                foreach ($output as $line) {
                    $line = trim($line, "\" \t\n\r\0\x0B");
                    if (!empty($line)) {
                        $cols = str_getcsv($line);
                        if (isset($cols[1]) && is_numeric($cols[1])) {
                            $pids[] = (int)$cols[1];
                        }
                    }
                }
            }
        } else {
            foreach ($process_names as $process_name) {
                exec("ps -e -o pid,comm | grep $process_name", $output);
                foreach ($output as $line) {
                    if (preg_match('/^\s*(\d+)\s+' . $process_name . '/', $line, $matches)) {
                        $pids[] = (int)$matches[1];
                    }
                }
            }
        }

        return array_unique($pids);
    }

    /**
     * Get ports by process ID
     */
    private function getPortsByPid(int $pid): array
    {
        $ports = [];

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            exec("netstat -ano | findstr $pid", $output, $return_var);
        } else {
            exec("netstat -tlnp | grep $pid", $output, $return_var);
        }

        if ($return_var === 0 && !empty($output)) {
            foreach ($output as $line) {
                $connection = $this->parseConnectionLine($line, $pid);
                if ($connection && $connection['status'] === 'LISTENING') {
                    $ports[] = $connection;
                }
            }
        }

        return $ports;
    }

    /**
     * Get all network connections for Apache
     */
    private function getAllConnections(): array
    {
        $connections = [];
        $pids = $this->getApachePids();

        foreach ($pids as $pid) {
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                exec("netstat -ano | findstr $pid", $output, $return_var);
            } else {
                exec("netstat -tlnp | grep $pid", $output, $return_var);
            }

            if ($return_var === 0 && !empty($output)) {
                foreach ($output as $line) {
                    $connection = $this->parseConnectionLine($line, $pid);
                    if ($connection) {
                        $connections[] = $connection;
                    }
                }
            }
        }

        return $connections;
    }

    /**
     * Parse a netstat connection line
     */
    private function parseConnectionLine(string $line, int $pid): ?array
    {
        $line = trim($line);
        
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows format: TCP    0.0.0.0:80    0.0.0.0:0    LISTENING    1234
            if (preg_match('/^(TCP|UDP)\s+([\d\.]+):(\d+)\s+([\d\.]+):(\d+)\s+(\w+)\s+' . $pid . '$/', $line, $matches)) {
                return [
                    'protocol' => $matches[1],
                    'local_address' => $matches[2] . ':' . $matches[3],
                    'foreign_address' => $matches[4] . ':' . $matches[5],
                    'status' => $matches[6],
                    'pid' => $pid,
                    'port' => (int)$matches[3]
                ];
            }
        } else {
            // Linux format: tcp6   0   0 :::80    :::*    LISTEN    1234/httpd
            if (preg_match('/^(tcp|tcp6|udp)\s+\d+\s+\d+\s+([\d\.]+|::):(\d+)\s+([\d\.\*]+|:::\*)\s+(\w+)\s+' . $pid . '\\/(httpd|apache2)/', $line, $matches)) {
                return [
                    'protocol' => $matches[1],
                    'local_address' => ($matches[2] === '::' ? 'localhost' : $matches[2]) . ':' . $matches[3],
                    'foreign_address' => $matches[4],
                    'status' => $matches[5],
                    'pid' => $pid,
                    'port' => (int)$matches[3]
                ];
            }
        }
        
        return null;
    }

    /**
     * Analyze port configuration
     */
    private function analyzePorts(array $ports): array
    {
        $analysis = [
            'total_ports' => count($ports),
            'http_port' => false,
            'https_port' => false,
            'custom_ports' => []
        ];

        foreach ($ports as $portInfo) {
            if ($portInfo['port'] === 80) {
                $analysis['http_port'] = true;
            } elseif ($portInfo['port'] === 443) {
                $analysis['https_port'] = true;
            } else {
                $analysis['custom_ports'][] = $portInfo['port'];
            }
        }

        return $analysis;
    }

    /**
     * Check if specific port is in use by Apache
     */
    public function isPortInUse(int $port): bool
    {
        $ports = $this->getListeningPorts();
        
        foreach ($ports as $portInfo) {
            if ($portInfo['port'] === $port) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get port status summary
     */
    public function getPortStatus(): string
    {
        $ports = $this->getListeningPorts();
        $httpActive = $this->isPortInUse(80);
        $httpsActive = $this->isPortInUse(443);

        if (empty($ports)) {
            return $this->formatter->formatError(" Apache is not listening on any port");
        }

        $status = "Apache is listening on " . count($ports) . " port(s): ";
        $portList = [];
        
        foreach ($ports as $portInfo) {
            $portList[] = $portInfo['port'];
        }
        
        $status .= implode(', ', $portList);

        if ($httpActive && $httpsActive) {
            return $this->formatter->formatSuccess("" . $status . " (HTTP & HTTPS active)");
        } elseif ($httpActive) {
            return $this->formatter->formatSuccess("" . $status . " (HTTP active)");
        } elseif ($httpsActive) {
            return $this->formatter->formatSuccess("" . $status . " (HTTPS active)");
        } else {
            return $this->formatter->formatWarning("" . $status . " (Custom ports only)");
        }
    }
}