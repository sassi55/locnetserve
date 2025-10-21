<?php
//-------------------------------------------------------------
// ApachePortCommand.php - Apache Port Command for LocNetServe
//-------------------------------------------------------------
// Handles Apache port detection and network information.
// Provides detailed port and IP binding information.
//
// Author : Sassi Souid
// Project: LocNetServe
// Version: 1.0.0
//-------------------------------------------------------------

namespace Web\Commands;

use Core\Commands\Command;

class ApachePortCommand implements Command
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
        return $this->getPortInfo();
    }

    public function getPortInfo(): string
    {
        $port = $this->getPort();
        $ip = $this->getIP();
        
        $output = $this->colors['GREEN'] . "Apache Port Information:" . $this->colors['RESET'] . PHP_EOL;
        $output .= "Port: " . $this->colors['CYAN'] . ($port ?: "Not detected") . $this->colors['RESET'] . PHP_EOL;
        $output .= "IP: " . $this->colors['CYAN'] . $ip . $this->colors['RESET'] . PHP_EOL;
        
        // Add connection details
        $output .= PHP_EOL . $this->colors['YELLOW'] . "Connection Details:" . $this->colors['RESET'] . PHP_EOL;
        $output .= "Host: " . $this->colors['WHITE'] . "localhost" . $this->colors['RESET'] . PHP_EOL;
        $output .= "Full Address: " . $this->colors['WHITE'] . "$ip:" . ($port ?: "unknown") . $this->colors['RESET'];
        
        return $output;
    }

    public function getPort(): string
    {
        $pids = $this->getAllPids();
        
        foreach ($pids as $pid) {
            $port = $this->findPortByPid($pid);
            if ($port !== null) {
                return $port;
            }
        }

        return "";
    }

    public function getIP(): string
    {
        $pids = $this->getAllPids();
        
        foreach ($pids as $pid) {
            $ip = $this->findIPByPid($pid);
            if ($ip !== null) {
                return $ip;
            }
        }

        return "Unknown IP";
    }

    private function findPortByPid(int $pid): ?string
    {
        exec("netstat -ano | findstr $pid", $output, $return_var);
        
        if ($return_var === 0 && !empty($output)) {
            foreach ($output as $line) {
                if (preg_match('/:(\d+)\s+.*\s+' . $pid . '$/', $line, $matches)) {
                    return $matches[1];
                }
            }
        }
        
        return null;
    }

    private function findIPByPid(int $pid): ?string
    {
        exec("netstat -ano | findstr $pid", $output, $return_var);
        
        if ($return_var === 0 && !empty($output)) {
            foreach ($output as $line) {
                if (preg_match('/\s+([\d\.]+):\d+\s+.*\s+' . $pid . '$/', $line, $matches)) {
                    return $matches[1];
                }
            }
        }
        
        return null;
    }

    private function getAllPids(): array
    {
        $pids = [];

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            exec('tasklist /fi "imagename eq httpd.exe" /fo csv', $output);
            if (count($output) > 1) {
                $processes = array_slice($output, 1);
                foreach ($processes as $process) {
                    $parts = str_getcsv($process);
                    if (isset($parts[1]) && is_numeric($parts[1])) {
                        $pids[] = (int)$parts[1];
                    }
                }
            }
        } else {
            exec('pgrep httpd', $output);
            foreach ($output as $line) {
                if (is_numeric(trim($line))) {
                    $pids[] = (int)trim($line);
                }
            }
        }

        return $pids;
    }

    public function getHelp(): string
    {
        return $this->colors['GREEN'] . "apache ports" . $this->colors['RESET'] . 
               " - Display Apache listening port and IP information" . PHP_EOL .
               $this->colors['CYAN'] . "Usage: lns apache ports" . $this->colors['RESET'] . PHP_EOL .
               "Displays: Listening port, IP address, connection details";
    }
}