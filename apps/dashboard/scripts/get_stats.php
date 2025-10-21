<?php
/**
 * -------------------------------------------------------------
 *  get_stats.php - Statistics API for LocNetServe
 * -------------------------------------------------------------
 *  This script provides system and project statistics for the
 *  LocNetServe dashboard. It allows:
 *    - Reading statistics from stats.json
 *    - Falling back to default stats in case of errors
 *    - Scanning real projects from /www
 *
 *  Messages are translated according to the language defined in
 *  config/config.json and corresponding locale file in config/locales.
 *
 *  Author : Sassi Souid
 *  Email  : locnetserve@gmail.com
 *  Project: LocNetServe
 *  Version: 1.0.0
 * -------------------------------------------------------------
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// -------------------------------------------------------------
// Load Translator
// -------------------------------------------------------------
require_once __DIR__ . '/translator.php';

// Detect root path
$rootPath = dirname(dirname(dirname(__DIR__)));
Translator::init($rootPath);

// -------------------------------------------------------------
// Dynamic paths
// -------------------------------------------------------------
$basePath     = $rootPath . DIRECTORY_SEPARATOR . 'apps' . DIRECTORY_SEPARATOR . 'dashboard' . DIRECTORY_SEPARATOR;
$jsonFilePath = $basePath . 'stats.json';

// -------------------------------------------------------------
// Functions
// -------------------------------------------------------------

function getStatsFromJson($filePath) {
    if (!file_exists($filePath)) {
        return [
            'error'   => Translator::t('messages.notifications.not_found', ['item' => 'stats.json']),
            'message' => Translator::t('messages.notifications.error', ['message' => 'File not found'])
        ];
    }

    if (!is_readable($filePath)) {
        return [
            'error'   => Translator::t('messages.notifications.error', ['message' => 'Permission denied']),
            'message' => Translator::t('messages.notifications.error', ['message' => 'Unable to read file'])
        ];
    }

    $jsonContent = file_get_contents($filePath);

    if ($jsonContent === false) {
        return [
            'error'   => Translator::t('messages.notifications.error', ['message' => 'Read error']),
            'message' => Translator::t('messages.notifications.error', ['message' => 'Could not read stats.json'])
        ];
    }

    if (trim($jsonContent) === '') {
        return [
            'error'   => Translator::t('messages.notifications.error', ['message' => 'Empty file']),
            'message' => Translator::t('messages.notifications.error', ['message' => 'stats.json is empty'])
        ];
    }

    $data = json_decode($jsonContent, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('Invalid JSON: ' . substr($jsonContent, 0, 200));

        return [
            'error'   => Translator::t('messages.notifications.error', ['message' => 'Invalid JSON']),
            'message' => json_last_error_msg(),
            'json_sample' => substr($jsonContent, 0, 200)
        ];
    }

    return $data;
}

function getDefaultStats() {
    return [
        'apache' => [
            'status' => "running",
            'cpu' => "2.5",
            'memory' => "120.5",
            'uptime' => "02:45:30",
            'requests' => "1245"
        ],
        'mysql' => [
            'status' => "running",
            'cpu' => "1.8",
            'memory' => "85.2",
            'uptime' => "02:45:30",
            'connections' => "8"
        ],
        'system' => [
            'cpu_usage' => "15.3",
            'memory_usage' => "45.8",
            'disk_usage' => "62.1",
            'network_usage' => "125.4"
        ]
    ];
}

function getProjectsData() {
    global $rootPath;
    $wwwRoot = $rootPath . DIRECTORY_SEPARATOR . 'www' . DIRECTORY_SEPARATOR;

    if (!is_dir($wwwRoot)) {
        error_log('Projects directory not found: ' . $wwwRoot);
        return null;
    }

    $projects = [];
    $items = scandir($wwwRoot);

    foreach ($items as $item) {
        $fullPath = $wwwRoot . $item;
        if ($item !== '.' && $item !== '..' && is_dir($fullPath) && $item !== 'dashboard') {
            $size = 0;
            $files = 0;
            $lastModified = 0;

            try {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($fullPath, RecursiveDirectoryIterator::SKIP_DOTS)
                );

                foreach ($iterator as $file) {
                    if ($file->isFile()) {
                        $size += $file->getSize();
                        $files++;
                        $fileMTime = $file->getMTime();
                        if ($fileMTime > $lastModified) {
                            $lastModified = $fileMTime;
                        }
                    }
                }

                $projects[$item] = [
                    'name' => $item,
                    'size' => round($size / 1024 / 1024, 1), // MB
                    'files' => $files,
                    'last_modified' => date('Y-m-d H:i:s', $lastModified)
                ];

            } catch (Exception $e) {
                error_log('Project scan error ' . $item . ': ' . $e->getMessage());
                continue;
            }
        }
    }

    return $projects;
}

// -------------------------------------------------------------
// Main
// -------------------------------------------------------------
try {
    $jsonData = getStatsFromJson($jsonFilePath);

    if (isset($jsonData['error'])) {
        $response = getDefaultStats();
        $response['warning'] = $jsonData['error'];
        $response['warning_message'] = $jsonData['message'];
        $response['debug'] = [
            'file_path' => $jsonFilePath,
            'file_exists' => file_exists($jsonFilePath),
            'file_readable' => is_readable($jsonFilePath)
        ];

        if (isset($jsonData['json_sample'])) {
            $response['debug']['json_sample'] = $jsonData['json_sample'];
        }

    } else {
        $response = $jsonData;
    }

    if (!isset($response['projects']) || empty($response['projects'])) {
        $realProjects = getProjectsData();
        if ($realProjects !== null && !empty($realProjects)) {
            $response['projects'] = $realProjects;
        }
    }

    echo json_encode($response, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    error_log('Unexpected error: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'error' => Translator::t('messages.notifications.error', ['message' => 'Unexpected error']),
        'message' => $e->getMessage(),
        'default_data' => getDefaultStats()
    ], JSON_PRETTY_PRINT);
}
?>
