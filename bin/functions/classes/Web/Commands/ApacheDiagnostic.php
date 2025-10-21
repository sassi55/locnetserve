<?php
//-------------------------------------------------------------
// ApacheDiagnostic.php - Apache Diagnostic Handler for LocNetServe
//-------------------------------------------------------------
// Handles comprehensive Apache diagnostics including configuration,
// ports, services, and troubleshooting.
//
// Author : Sassi Souid
// Project: LocNetServe
// Version: 1.0.0
//-------------------------------------------------------------

namespace Web\Commands;

class ApacheDiagnostic
{
    private array $colors;
    private array $services;

    public function __construct(array $colors, array $services)
    {
        $this->colors = $colors;
        $this->services = $services;
    }

    /**
     * Run complete Apache diagnostic
     */
    public function runDiagnostic(): string
    {
       
		$output = $this->colors['CYAN'] . "Apache Complete Diagnostic" . $this->colors['RESET'] . PHP_EOL;
        $output .= str_repeat('=', 60) . PHP_EOL;
        
        $output .= $this->checkExecutable();
        $output .= $this->checkConfiguration();
        $output .= $this->checkPortsDetailed();
        $output .= $this->checkWindowsServices();
        $output .= $this->checkModules();
        $output .= $this->generateRecommendations();
        
        return $output;
    }

    /**
     * Check Apache executable
     */
    private function checkExecutable(): string
    {
        $output = PHP_EOL . $this->colors['YELLOW'] . "1. Apache Executable:" . $this->colors['RESET'] . PHP_EOL;
        
        $httpd = $this->services['Apache']['exe'] ?? '';
        
        if (!file_exists($httpd)) {
            $output .= $this->colors['RED'] . "   Not found: $httpd" . $this->colors['RESET'] . PHP_EOL;
        } else {
            $output .= $this->colors['GREEN'] . "   Found: " . basename($httpd) . $this->colors['RESET'] . PHP_EOL;
            
            // Version
            exec('"' . $httpd . '" -v', $versionOutput);
            if (!empty($versionOutput)) {
                $output .= "   " . $versionOutput[0] . PHP_EOL;
            }
        }
        
        return $output;
    }

    /**
     * Check Apache configuration
     */
    private function checkConfiguration(): string
    {
        $output = PHP_EOL . $this->colors['YELLOW'] . "2. Configuration:" . $this->colors['RESET'] . PHP_EOL;
        
        $httpd = $this->services['Apache']['exe'] ?? '';
        $confFile = $this->services['Apache']['conf'] ?? '';
        
        if (!file_exists($confFile)) {
            $output .= $this->colors['RED'] . "   Config file not found : $confFile" . $this->colors['RESET'] . PHP_EOL;
            return $output;
        }
        
        $output .= "   Config: " . basename($confFile) . PHP_EOL;
        
        // Syntax check
        exec('"' . $httpd . '" -t -f "' . $confFile . '" 2>&1', $syntaxOutput, $returnCode);
        
        if ($returnCode === 0) {
            $output .= $this->colors['GREEN'] . "  Syntax OK" . $this->colors['RESET'] . PHP_EOL;
        } else {
            $output .= $this->colors['RED'] . "   Syntax errors:" . $this->colors['RESET'] . PHP_EOL;
            foreach ($syntaxOutput as $line) {
                $output .= "      " . $line . PHP_EOL;
            }
        }
        
        return $output;
    }

    /**
     * Check ports in detail
     */
    private function checkPortsDetailed(): string
    {
        $output = PHP_EOL . $this->colors['YELLOW'] . "3. Port Configuration:" . $this->colors['RESET'] . PHP_EOL;
        
        $confFile = $this->services['Apache']['conf'] ?? '';
        
        if (!file_exists($confFile)) {
            return $output . $this->colors['RED'] . "   ❌ Cannot check ports - config file missing" . $this->colors['RESET'] . PHP_EOL;
        }
        
        $content = file_get_contents($confFile);
        preg_match_all('/Listen\s+(\d+)/', $content, $matches);
        $ports = $matches[1] ?? [];
        
        if (empty($ports)) {
            $output .= $this->colors['RED'] . "   No Listen directives found" . $this->colors['RESET'] . PHP_EOL;
        } else {
            foreach ($ports as $port) {
                $status = $this->checkPortAvailability((int)$port);
                $output .= "   Port $port: $status" . PHP_EOL;
            }
        }
        
        // Check ServerName
        if (preg_match('/ServerName\s+([^\s]+)/', $content, $serverMatch)) {
            $output .= "   ServerName: " . $serverMatch[1] . PHP_EOL;
        }
        
        return $output;
    }

    /**
     * Check Windows services
     */
    private function checkWindowsServices(): string
    {
        $output = PHP_EOL . $this->colors['YELLOW'] . "4. Windows Services:" . $this->colors['RESET'] . PHP_EOL;
        
        // Check IIS
        exec('sc query W3SVC', $iisOutput, $iisReturn);
        if ($iisReturn === 0) {
            $output .= $this->colors['RED'] . "   IIS (W3SVC) is installed" . $this->colors['RESET'] . PHP_EOL;
        } else {
            $output .= $this->colors['GREEN'] . "   IIS not detected" . $this->colors['RESET'] . PHP_EOL;
        }
        
        // Check other web servers
        $commonServers = ['nginx', 'WAS', 'WASVC'];
        foreach ($commonServers as $server) {
            exec("tasklist /fi \"imagename eq $server.exe\" 2>nul", $taskOutput);
            if (count($taskOutput) > 1) {
                $output .= $this->colors['YELLOW'] . "   $server.exe is running" . $this->colors['RESET'] . PHP_EOL;
            }
        }
        
        return $output;
    }

    /**
     * Check essential modules
     */
    private function checkModules(): string
    {
        $output = PHP_EOL . $this->colors['YELLOW'] . "5. Essential Modules:" . $this->colors['RESET'] . PHP_EOL;
        
        $confFile = $this->services['Apache']['conf'] ?? '';
        
        if (!file_exists($confFile)) {
            return $output . $this->colors['RED'] . "   Cannot check modules - config file missing" . $this->colors['RESET'] . PHP_EOL;
        }
        
        $content = file_get_contents($confFile);
        $essentialModules = [
            'mod_so' => 'LoadModule socache_shmcb_module',
            'mod_mime' => 'LoadModule mime_module', 
            'mod_log_config' => 'LoadModule log_config_module',
            'mod_setenvif' => 'LoadModule setenvif_module'
        ];
        
        foreach ($essentialModules as $module => $pattern) {
            if (strpos($content, $pattern) !== false) {
                $output .= "   $module: " . $this->colors['GREEN'] . "Loaded" . $this->colors['RESET'] . PHP_EOL;
            } else {
                $output .= "   $module: " . $this->colors['YELLOW'] . "Not loaded" . $this->colors['RESET'] . PHP_EOL;
            }
        }
        
        return $output;
    }

    /**
     * Generate recommendations
     */
    private function generateRecommendations(): string
    {
        $output = PHP_EOL . $this->colors['CYAN'] . "6. Recommendations:" . $this->colors['RESET'] . PHP_EOL;
        
        $confFile = $this->services['Apache']['conf'] ?? '';
        $hasRecommendations = false;
        
        if (file_exists($confFile)) {
            $content = file_get_contents($confFile);
            preg_match_all('/Listen\s+(\d+)/', $content, $matches);
            $ports = $matches[1] ?? [];
            
            foreach ($ports as $port) {
                if (!$this->isPortAvailable((int)$port)) {
                    $output .= $this->colors['YELLOW'] . "   • Port $port is blocked. Change to 8080 in httpd.conf" . $this->colors['RESET'] . PHP_EOL;
                    $hasRecommendations = true;
                }
            }
        }
        
        // Check IIS
        exec('sc query W3SVC', $iisOutput, $iisReturn);
        if ($iisReturn === 0) {
            $output .= $this->colors['YELLOW'] . "   • Stop IIS: net stop W3SVC" . $this->colors['RESET'] . PHP_EOL;
            $hasRecommendations = true;
        }
        
        if (!$hasRecommendations) {
            $output .= $this->colors['GREEN'] . "   No issues detected" . $this->colors['RESET'] . PHP_EOL;
        }
        
        return $output;
    }

    /**
     * Check port availability
     */
    private function checkPortAvailability(int $port): string
    {
        $command = "netstat -ano | findstr \":$port \"";
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0) {
            $pid = $this->findProcessUsingPort($port);
            if ($pid) {
                $process = $this->getProcessName($pid);
                return $this->colors['RED'] . "Used by $process (PID: $pid)" . $this->colors['RESET'];
            }
            return $this->colors['RED'] . "In use" . $this->colors['RESET'];
        } else {
            return $this->colors['GREEN'] . "Available" . $this->colors['RESET'];
        }
    }

    /**
     * Check if port is available
     */
    private function isPortAvailable(int $port): bool
    {
        $command = "netstat -ano | findstr \":$port \"";
        exec($command, $output, $returnCode);
        return $returnCode !== 0;
    }

    /**
     * Find process using port
     */
    private function findProcessUsingPort(int $port): ?string
    {
        $command = "netstat -ano | findstr \":$port \"";
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0 && !empty($output)) {
            foreach ($output as $line) {
                if (preg_match('/LISTENING\s+(\d+)$/', $line, $matches)) {
                    return $matches[1];
                }
            }
        }
        
        return null;
    }

    /**
     * Get process name from PID
     */
    private function getProcessName(string $pid): string
    {
        $command = "tasklist /FI \"PID eq $pid\" /FO CSV";
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0 && count($output) > 1) {
            $parts = str_getcsv($output[1]);
            return $parts[0] ?? 'Unknown';
        }
        
        return 'Unknown';
    }
}