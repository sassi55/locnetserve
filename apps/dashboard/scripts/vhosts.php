<?php
/**
 * -------------------------------------------------------------
 *  vhosts.php - Virtual Host Management API
 * -------------------------------------------------------------
 *  Provides REST API for managing Apache virtual hosts by:
 *    - Listing existing virtual hosts
 *    - Creating new virtual hosts
 *    - Deleting existing virtual hosts
 *    - Integrating with AutoHotkey for system operations
 *    - Supporting both web and CLI interfaces
 *
 *  Author : Sassi Souid
 *  Email  : locnetserve@gmail.com
 *  Project: LocNetServe
 *  Version: 1.0.0
 * -------------------------------------------------------------
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
// -------------------------------------------------------------
// Load Translator
// -------------------------------------------------------------
require_once __DIR__ . '/translator.php';

// Detect root path
$rootPath = dirname(dirname(dirname(__DIR__)));
Translator::init($rootPath);


if (!isset($_GET['action']) && empty($_POST)) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'Accès direct non autorisé. Paramètre action requis.',
        'requires_restart' => false
    ]);
    exit;
}

$action = $_GET['action'] ?? '';


$validActions = ['list', 'create', 'delete'];
if (!in_array($action, $validActions)) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'Action non supportée. Actions valides: ' . implode(', ', $validActions),
        'requires_restart' => false
    ]);
    exit;
}

$name = $_GET['name'] ?? '';
$path = $_GET['path'] ?? '';


$baseDir = realpath(__DIR__ . '/../../../') . '\\';
$vhostsPath = $baseDir . 'bin\apache\conf\extra\vhosts\\';
$tmpDir = $baseDir . 'tmp/';
$commandFile = $tmpDir . 'ahk_commands.json';

$result = ['success' => false, 'message' => '', 'requires_restart' => false];
function getInstallDir($pathsConfPath) {
    if (!file_exists($pathsConfPath)) {
        return null;
    }
    
    $content = file_get_contents($pathsConfPath);
    
    
    if (preg_match('/Define\s+INSTALL_DIR\s+"([^"]+)"/', $content, $matches)) {
        return $matches[1];
    }
    
    return null;
}
$installDir = getInstallDir('../../../bin/apache/conf/paths.conf');

if ($installDir) {
    $pathMatchconf = $installDir.'/';
} else {
    $pathMatchconf = "INSTALL_DIR";
}
switch ($action) {
    case 'list':
        $vhosts = [];
        if (is_dir($vhostsPath)) {
            $files = glob($vhostsPath . '*.conf');
            
            foreach ($files as $file) {
                $content = file_get_contents($file);
                if (preg_match('/ServerName\s+(\S+)/', $content, $nameMatch) &&
                    preg_match('/DocumentRoot\s+"([^"]+)"/', $content, $pathMatch)) {
                    $vhosts[] = [
                        'name' => $nameMatch[1],
                        'path' => str_replace('${INSTALL_DIR}',$pathMatchconf,$pathMatch[1]),
                        'configFile' => basename($file)
                    ];
                }
            }
        }
        $result['success'] = true;
        $result['vhosts'] = $vhosts;
        break;
        
    case 'create':
        if (empty($name) || empty($path)) {
            $result['message'] = 'Nom et chemin requis';
            break;
        }
        
        $command = [
            'type' => 'create_vhost',
            'name' => $name,
            'path' => $path,
            'timestamp' => time() 
        ];
        
        $commands = [];
        if (file_exists($commandFile)) {
            $commands = json_decode(file_get_contents($commandFile), true);
        }
        
        $commands[] = $command;
        
        if (file_put_contents($commandFile, json_encode($commands, JSON_PRETTY_PRINT))) {
            $result['success'] = true;
            $result['message'] = Translator::t('messages.notifications.vhost_created', ['name' => $name.".localhost"]);
            $result['requires_restart'] = false;
        } else {
            $result['message'] = 'Erreur lors de l\'enregistrement';
        }
        break;
        
    case 'delete':
        if (empty($name)) {
            $result['message'] = 'Nom requis';
            break;
        }
		if ($name == "localhost") {
            $result['message'] = 'Hôte Non Supprimé';
            break;
        }
        $name = preg_replace('/\.localhost$/', '', $name);
        $command = [
            'type' => 'delete_vhost',
            'name' => $name,
            'timestamp' => time() 
        ];
        
        $commands = [];
        if (file_exists($commandFile)) {
            $commands = json_decode(file_get_contents($commandFile), true);
        }
        
        $commands[] = $command;
        
        if (file_put_contents($commandFile, json_encode($commands, JSON_PRETTY_PRINT))) {
            $result['success'] = true;
            $result['message'] = Translator::t('messages.notifications.vhost_deleted', ['name' => $name.".localhost"]);
            $result['requires_restart'] = false;
        } else {
            $result['message'] = 'Erreur lors de l\'enregistrement';
        }
        break;
        
    default:
        $result['message'] = 'Action non supportée';
}




// Support CLI
if (php_sapi_name() === 'cli' || isset($_GET['cli'])) {
    header('Content-Type: application/json');
    
    if ($action === 'list') {
        echo json_encode($result);
        exit;
    }
    
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => $result['message'],
            'vhosts' => $result['vhosts'] ?? []
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => $result['message']
        ]);
    }
    exit;
}
echo json_encode($result);

?>