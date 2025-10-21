<?php
/**
 * -------------------------------------------------------------
 *  command.php - Command Interface for LocNetServe
 * -------------------------------------------------------------
 *  Handles incoming service commands (start, stop, restart) by:
 *    - Processing HTTP GET requests for service management
 *    - Interacting with configured services and Windows processes
 *    - Executing system commands and updating service statistics
 *    - Managing service status in stats.json file
 *
 *  Author : Sassi Souid
 *  Email  : locnetserve@gmail.com
 *  Project: LocNetServe
 *  Version: 1.0.0
 * -------------------------------------------------------------
 */

//-------------------------------------------------------------
// HTTP Headers
//-------------------------------------------------------------
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

//-------------------------------------------------------------
// Load Configuration and Stats Files
//-------------------------------------------------------------

$projectRoot = dirname(__DIR__, 3); // Move up three levels from apps/dashboard
$configFile  = $projectRoot . '\\config\\config.json';
$statsFile   = dirname(__DIR__) . '/stats.json';

$config = json_decode(file_get_contents($configFile), true);

// Load existing stats or initialize an empty array
if (file_exists($statsFile)) {
    $stats = json_decode(file_get_contents($statsFile), true);
    if (!is_array($stats)) {
        $stats = [];
    }
} else {
    $stats = [];
}

//-------------------------------------------------------------
// Retrieve and Validate GET Parameters
//-------------------------------------------------------------
$service = isset($_GET['service']) ? strtolower($_GET['service']) : '';
$action  = isset($_GET['action'])  ? strtolower($_GET['action'])  : '';

if (empty($service) || empty($action)) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

//-------------------------------------------------------------
// Validate Service Existence
//-------------------------------------------------------------
$servicesConfig = array_change_key_case($config['services'], CASE_LOWER);
if (!isset($servicesConfig[$service])) {
    echo json_encode(['success' => false, 'message' => 'Unknown service']);
    exit;
}

//-------------------------------------------------------------
// Validate Requested Action
//-------------------------------------------------------------
$validActions = ['start', 'stop', 'restart'];
if (!in_array($action, $validActions)) {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

//-------------------------------------------------------------
// Extract Service Configuration
//-------------------------------------------------------------
$serviceConfig = $servicesConfig[$service];
$exePath       = $serviceConfig['exe'] ?? null;
$processName   = $serviceConfig['process'] ?? null;

if (!$exePath || !$processName) {
    echo json_encode(['success' => false, 'message' => $service . ' Executable or process not defined']);
    exit;
}

//-------------------------------------------------------------
// Execute the Corresponding System Command
//-------------------------------------------------------------
$output = [];
$returnCode = 0;
$command = '';

if ($action === 'start') {
    // Launch service in background mode
    $command = 'start /B "" "' . $exePath . '"';
    exec($command, $output, $returnCode);

} elseif ($action === 'stop') {
    // Forcefully stop the service process
    $command = 'taskkill /F /IM ' . escapeshellarg($processName);
    exec($command, $output, $returnCode);

} elseif ($action === 'restart') {
    // Stop and restart the service
    $commandStop = 'taskkill /F /IM ' . escapeshellarg($processName);
    exec($commandStop, $output, $returnCode);

    sleep(1);

    $command = 'start /B "" "' . $exePath . '"';
    exec($command, $output, $returnCode);
}

//-------------------------------------------------------------
// Update stats.json After Successful Execution
//-------------------------------------------------------------
if ($returnCode === 0) {
    if (!isset($stats[$service])) {
        $stats[$service] = [];
    }

    // Update service status
    if ($action === 'start' || $action === 'restart') {
        $stats[$service]['status'] = 'running';
    } elseif ($action === 'stop') {
        $stats[$service]['status'] = 'stopped';
    }

    // Reset metrics to defaults
    $stats[$service]['uptime'] = '00:00:00';
    $stats[$service]['cpu']    = "0";
    $stats[$service]['memory'] = "0";

    // Save stats file with updated data
    file_put_contents($statsFile, json_encode($stats, JSON_PRETTY_PRINT));

    echo json_encode([
        'success' => true,
        'message' => 'Command executed successfully',
        'command' => $command,
        'stats'   => $stats[$service]
    ]);
} else {
    // Return error response in case of command failure
    echo json_encode([
        'success' => false,
        'message' => 'Execution error occurred',
        'command' => $command,
        'output'  => $output,
        'code'    => $returnCode
    ]);
}
?>
