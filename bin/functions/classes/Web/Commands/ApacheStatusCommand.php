<?php
//-------------------------------------------------------------
// ApacheStatusCommand.php - Apache Status Command for LocNetServe
//-------------------------------------------------------------
// Handles Apache service status monitoring and PID detection.
// Provides detailed status information about Apache service.
//
// Author : Sassi Souid
// Project: LocNetServe
// Version: 1.0.0
//-------------------------------------------------------------

namespace Web\Commands;

use Core\Commands\Command;

class ApacheStatusCommand implements Command
{
    private array $colors;
    private array $config;
    private string $pid_file;

    public function __construct(array $colors, array $config)
    {
        $this->colors = $colors;
        $this->config = $config;
        $this->pid_file = dirname($config['config_path'] ?? '') . DIRECTORY_SEPARATOR . 'apache.pid';
    }

    public function execute(string $action, array $args = []): string
    {
        return $this->getStatus();
    }

    public function getStatus(): string
    {
        $pid = $this->getPid();
        
        if ($pid) {
            $status = $this->colors['GREEN'] . "Apache is running (PID: $pid)" . $this->colors['RESET'];
            
            // Add additional details if available
            $port = $this->getPort();
            $version = $this->getVersion();
            
            $details = [];
            if (!empty($port)) {
                $details[] = "Port: $port";
            }
            if ($version !== "unknown") {
                $details[] = "Version: $version";
            }
            
            if (!empty($details)) {
                $status .= PHP_EOL . $this->colors['CYAN'] . "Details: " . implode(", ", $details) . $this->colors['RESET'];
            }
            
            return $status;
        }
        
        return $this->colors['MAGENTA'] . "Apache is not running." . $this->colors['RESET'];
    }

    public function getPid()
    {
        // Check PID file first
        if (file_exists($this->pid_file)) {
            $pid_content = trim(file_get_contents($this->pid_file));
            $pid_content = remove_bom($pid_content);
            if (is_numeric($pid_content)) {
                return (int)$pid_content;
            }
        }

        // Check Windows process list
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            exec('tasklist /fi "imagename eq httpd.exe" /fo csv', $output);
            if (count($output) > 1) {
                $processes = array_slice($output, 1);
                $parts = str_getcsv($processes[0]);
                if (isset($parts[1]) && is_numeric($parts[1])) {
                    return (int)$parts[1];
                }
            }
        }

        return false;
    }

    private function getPort(): string
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

    private function getVersion(): string
    {
        $httpd = $this->config['services']['Apache']['exe'] ?? '';
        if (!file_exists($httpd)) return "unknown";
        
        exec('"' . $httpd . '" -v', $output);
        if (!empty($output)) {
            foreach ($output as $line) {
                if (preg_match('/Server version:\s*(.+)/i', $line, $matches)) {
                    return $matches[1];
                }
            }
        }
        return "unknown";
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
        return $this->colors['GREEN'] . "apache status" . $this->colors['RESET'] . 
               " - Check Apache service status, PID, port, and version" . PHP_EOL .
               $this->colors['CYAN'] . "Usage: lns apache status" . $this->colors['RESET'] . PHP_EOL .
               "Displays: Service state, Process ID, Listening port, Apache version";
    }
}