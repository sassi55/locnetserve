<?php
/**
 * -------------------------------------------------------------
 *  check_vhost_manager.php - AutoHotkey Process Monitor
 * -------------------------------------------------------------
 *  Monitors AutoHotkey process status by:
 *    - Checking if AutoHotkey64.exe is currently running
 *    - Using Windows tasklist command for process detection
 *    - Providing real-time process status information
 *
 *  Author : Sassi Souid
 *  Email  : locnetserve@gmail.com
 *  Project: LocNetServe
 *  Version: 1.0.0
 * -------------------------------------------------------------
 */
header('Content-Type: application/json');

function isProcessRunning($processName) {
    exec('tasklist /FI "IMAGENAME eq ' . $processName . '" /FO CSV /NH', $output);
    return count($output) > 0 && strpos($output[0], $processName) !== false;
}

$result = [
    'running' => isProcessRunning('AutoHotkey64.exe'),
    'process' => 'AutoHotkey64.exe'
];

echo json_encode($result);
?>