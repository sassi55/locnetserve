<?php
/**
 * -------------------------------------------------------------
 *  check_apache_config.php - Apache Configuration Validator
 * -------------------------------------------------------------
 *  Validates Apache server configuration by:
 *    - Executing 'httpd -t' syntax test command
 *    - Checking configuration files for errors
 *    - Returning validation results with detailed output
 *
 *  Author : Sassi Souid
 *  Email  : locnetserve@gmail.com
 *  Project: LocNetServe
 *  Version: 1.0.0
 * -------------------------------------------------------------
 */
header('Content-Type: application/json');

function checkApacheConfig() {
    $apachePath = dirname(__DIR__, 2).'\bin\apache\bin\\';
    $command = $apachePath . 'httpd -t';
    
    exec($command, $output, $returnCode);
    
    return [
        'valid' => $returnCode === 0,
        'output' => implode("\n", $output),
        'returnCode' => $returnCode
    ];
}

$result = checkApacheConfig();
echo json_encode($result);
?>