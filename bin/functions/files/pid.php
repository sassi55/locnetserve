<?php
//-------------------------------------------------------------
// pid.php - PID management for LocNetServe
//-------------------------------------------------------------
// Contains functions to create, read, and delete PID files,
// and retrieve running process IDs on Windows and Linux.
// Essential for monitoring and controlling LocNetServe processes.
//
// Author : Sassi Souid
// Email  : locnetserve@gmail.com
// Project: LocNetServe
// Version: 1.0.0
//-------------------------------------------------------------

/**
 * Updates the PID file with the current process ID.
 *
 * Ensures the config directory exists, overwrites any existing PID file,
 * and stores the current PHP process ID. Returns the PID on success.
 *
 * @return int|string PID of the current process or error message
 */
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'conf' . DIRECTORY_SEPARATOR . 'configs.php';

function update_pid_file() {
    global $conf, $config_path;
    $pid_file = $conf . 'lns.pid';
    $currentPID = getmypid();

    try {
        $pid_dir = dirname($pid_file);
        if (!file_exists($pid_dir)) {
            mkdir($pid_dir, 0777, true);
        }

        file_put_contents($pid_file, (string)$currentPID);

        // Enregistrer timestamp dans config.json
        if (file_exists($config_path)) {
            $json_data = json_decode(file_get_contents($config_path), true);
            if (isset($json_data['settings'])) {
                $json_data['settings']['t_pid'] = time();
                file_put_contents($config_path, json_encode($json_data, JSON_PRETTY_PRINT));
            }
        }

        return $currentPID;

    } catch (Exception $err) {
        return "Error updating PID file: " . $err->getMessage();
    }
}

/**
 * Gets the PID of a running process by name.
 *
 * Works on both Windows and Linux. On Linux, uses pgrep.
 * On Windows, uses tasklist and parses CSV output.
 *
 * @return int|null PID of the process, or null if not found
 */
function getPID() {
    if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
        exec('pgrep httpd', $output);
        return !empty($output) ? $output[0] : null;
    }

    exec('tasklist /fi "imagename eq httpd.exe" /fo csv', $output);
    
    if (count($output) <= 1) {
        return null; // Only header or no process found
    }
    
    // Skip the header line
    $processes = array_slice($output, 1);
    
    // Return PID of the first process (usually the parent)
    $parts = str_getcsv($processes[0]);
    if (isset($parts[1]) && is_numeric($parts[1])) {
        return $parts[1];
    }
    
    return null;
}

/**
 * Retrieves the LocNetServe PID from the PID file.
 *
 * Reads the PID file, removes BOM/invisible characters, trims, 
 * and returns the PID if valid.
 *
 * @return int|false PID if running, false otherwise
 */
function get_locnetserve_pid() {
    global $conf;
    $pid_file = $conf . 'lns.pid';
    
    if (file_exists($pid_file)) {
        $pid = file_get_contents($pid_file);
        $pidContent = remove_bom($pid);
        $pidContent = trim($pidContent);
        
        if (is_numeric($pidContent)) {
            return $pidContent;
        }
    }
    
    return false;
}

/**
 * Deletes the LocNetServe PID file.
 *
 * @return bool True if file deleted, false if file did not exist
 */
function delete_pid_file() {
    global $conf;
    $pid_file = $conf . 'lns.pid';
    if (file_exists($pid_file)) {
        unlink($pid_file);
        return true;
    }
    return false;
}
