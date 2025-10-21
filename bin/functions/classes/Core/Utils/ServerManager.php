<?php
/**
 * -------------------------------------------------------------
 *  ServerManager.php - Process Controller for LocNetServe
 * -------------------------------------------------------------
 *  Manages LocNetServe process lifecycle by:
 *    - Starting and stopping the main application process
 *    - Monitoring PID status and process existence
 *    - Handling PID file management and process detection
 *    - Providing process control for service management
 *
 *  Author : Sassi Souid
 *  Email  : locnetserve@gmail.com
 *  Project: LocNetServe
 *  Version: 1.0.0
 * -------------------------------------------------------------
 */

namespace Core\Utils;

class ServerManager
{
    private string $pidFile;
    private string $exePath;
    private array $colors;

    /**
     * Constructor.
     *
     * @param array|null $colors CLI colors (optional)
     */
    public function __construct(array $colors = [])
    {
        $base = dirname(__DIR__, 5); // C:\MyServer
		
        $this->pidFile = $base . DIRECTORY_SEPARATOR . "config" . DIRECTORY_SEPARATOR . "lns.pid";
        $this->exePath = $base . DIRECTORY_SEPARATOR . "LocNetServe.exe";
        $this->colors = $colors;
    }

    /**
     * Check if LocNetServe is currently running.
     *
     * @return bool
     */
    public function isRunning(): bool
    {
        if (!file_exists($this->pidFile)) {
            return false;
        }

        $pid = (int) trim(file_get_contents($this->pidFile));
        if ($pid <= 0) {
            return false;
        }

        $output = [];
        exec("tasklist /FI \"PID eq $pid\" 2>NUL", $output);

        foreach ($output as $line) {
            if (preg_match('/LocNetServe\.exe/i', $line)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get current PID.
     *
     * @return int|null
     */
    public function getPid(): ?int
    {
        if (!file_exists($this->pidFile)) {
            return null;
        }

        $pid = (int) trim(file_get_contents($this->pidFile));
        return $pid > 0 ? $pid : null;
    }

    /**
     * Start LocNetServe.exe if not already running.
     *
     * @return bool
     */
    public function start(): bool
    {
        if (!file_exists($this->exePath)) {
            echo ($this->colors['RED'] ?? '') . "Executable not found: {$this->exePath}" . ($this->colors['RESET'] ?? '') . PHP_EOL;
            return false;
        }

        if ($this->isRunning()) {
            echo ($this->colors['YELLOW'] ?? '') . "LocNetServe is already running." . ($this->colors['RESET'] ?? '') . PHP_EOL;
            return true;
        }

        // Start detached process
        $cmd = 'start "" "' . $this->exePath . '"';
        pclose(popen($cmd, "r"));

        // Wait a short delay for process creation
        sleep(1);

        // Try to detect PID
        $pid = $this->detectPid();
        if ($pid) {
            file_put_contents($this->pidFile, $pid);
            echo ($this->colors['GREEN'] ?? '') . "LocNetServe started successfully (PID: $pid)" . ($this->colors['RESET'] ?? '') . PHP_EOL;
            return true;
        }

        echo ($this->colors['RED'] ?? '') . "Failed to start LocNetServe process." . ($this->colors['RESET'] ?? '') . PHP_EOL;
        return false;
    }

    /**
     * Stop LocNetServe.exe gracefully.
     *
     * @return bool
     */
    public function stop(): bool
    {
        if (!$this->isRunning()) {
            echo ($this->colors['MAGENTA'] ?? '') . "LocNetServe is not running." . ($this->colors['RESET'] ?? '') . PHP_EOL;
            if (file_exists($this->pidFile)) {
                unlink($this->pidFile);
            }
            return false;
        }

        $pid = $this->getPid();

        if ($pid) {
            exec("taskkill /PID $pid /F >NUL 2>&1");
            unlink($this->pidFile);
            echo ($this->colors['GREEN'] ?? '') . "LocNetServe stopped successfully (PID: $pid)." . ($this->colors['RESET'] ?? '') . PHP_EOL;
            return true;
        }

        echo ($this->colors['RED'] ?? '') . "Unable to stop LocNetServe: PID not found." . ($this->colors['RESET'] ?? '') . PHP_EOL;
        return false;
    }

    /**
     * Detect LocNetServe.exe process PID.
     *
     * @return int|null
     */
    private function detectPid(): ?int
    {
        $output = [];
        exec('tasklist /FI "IMAGENAME eq LocNetServe.exe" /FO CSV /NH', $output);

        if (!empty($output[0]) && strpos($output[0], 'LocNetServe.exe') !== false) {
            $fields = str_getcsv($output[0]);
            return isset($fields[1]) ? (int) $fields[1] : null;
        }

        return null;
    }
}
