<?php
/**
 * -------------------------------------------------------------
 *  start_vhost_manager.php - VHost Manager Launcher for LocNetServe
 * -------------------------------------------------------------
 *  Starts the AutoHotkey-based Virtual Host Manager by:
 *    - Checking if VHostManager.ahk process is already running
 *    - Launching the manager using AutoHotkey64.exe
 *    - Verifying successful process startup
 *    - Ensuring single instance of the manager
 *
 *  Author : Sassi Souid
 *  Email  : locnetserve@gmail.com
 *  Project: LocNetServe
 *  Version: 1.0.0
 * -------------------------------------------------------------
 */

//-------------------------------------------------------------
// Response Header
//-------------------------------------------------------------
header('Content-Type: application/json');

//-------------------------------------------------------------
// Function: startAhkManager()
// Purpose : Launches VHostManager.ahk using AutoHotkey64.exe
//           Ensures that the process is not already running.
//-------------------------------------------------------------
function startAhkManager() {
    $ahkPath = 'C:\Program Files\AutoHotkey\v2\AutoHotkey64.exe';
    $scriptPath = __DIR__ . '\VHostManager.ahk';
    
    //---------------------------------------------------------
    // Verify file existence
    //---------------------------------------------------------
    if (!file_exists($ahkPath)) {
        return ['success' => false, 'message' => 'AutoHotkey not found: ' . $ahkPath];
    }
    
    if (!file_exists($scriptPath)) {
        return ['success' => false, 'message' => 'VHostManager script not found: ' . $scriptPath];
    }
    
    //---------------------------------------------------------
    // Check if VHostManager is already running
    //---------------------------------------------------------
    exec('tasklist /FI "IMAGENAME eq AutoHotkey64.exe" /FO CSV /NH', $tasklistOutput, $tasklistReturn);
    
    $vhostManagerRunning = false;
    $otherAhkProcesses = [];
    
    // Loop through all AutoHotkey processes
    foreach ($tasklistOutput as $line) {
        if (strpos($line, 'AutoHotkey64.exe') !== false) {
            $parts = str_getcsv($line);
            if (count($parts) >= 2) {
                $pid = $parts[1];
                
                // Retrieve full command line for the given PID
                $commandLine = shell_exec('wmic process where "ProcessId=' . $pid . '" get CommandLine 2>nul');
                
                // Determine if this process belongs to VHostManager
                if (strpos($commandLine, 'VHostManager.ahk') !== false) {
                    $vhostManagerRunning = true;
                } else {
                    $otherAhkProcesses[] = $pid;
                }
            }
        }
    }
    
    //---------------------------------------------------------
    // If already running, do nothing
    //---------------------------------------------------------
    if ($vhostManagerRunning) {
        return ['success' => true, 'message' => 'VHost Manager is already running'];
    }
    
    //---------------------------------------------------------
    // Start only the VHostManager.ahk script
    //---------------------------------------------------------
    $command = 'start /B "" "' . $ahkPath . '" "' . $scriptPath . '"';
    $process = popen($command, 'r');
    pclose($process);
    
    // Wait a short time before rechecking process status
    sleep(2);
    
    //---------------------------------------------------------
    // Recheck if VHostManager started successfully
    //---------------------------------------------------------
    exec('tasklist /FI "IMAGENAME eq AutoHotkey64.exe" /FO CSV /NH', $tasklistOutput, $tasklistReturn);
    
    $isRunning = false;
    foreach ($tasklistOutput as $line) {
        if (strpos($line, 'AutoHotkey64.exe') !== false) {
            $parts = str_getcsv($line);
            if (count($parts) >= 2) {
                $pid = $parts[1];
                $commandLine = shell_exec('wmic process where "ProcessId=' . $pid . '" get CommandLine 2>nul');
                
                if (strpos($commandLine, 'VHostManager.ahk') !== false) {
                    $isRunning = true;
                    break;
                }
            }
        }
    }
    
    //---------------------------------------------------------
    // Return result as JSON
    //---------------------------------------------------------
    if ($isRunning) {
        return ['success' => true, 'message' => 'VHost Manager started successfully'];
    } else {
        return ['success' => false, 'message' => 'Failed to start VHost Manager'];
    }
}

//-------------------------------------------------------------
// Execute and Output Result
//-------------------------------------------------------------
$result = startAhkManager();
echo json_encode($result);
?>
