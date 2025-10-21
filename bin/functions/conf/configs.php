<?php
//-------------------------------------------------------------
// configs.php - Load configuration and ANSI color codes
//-------------------------------------------------------------
// Provides global configuration paths and ANSI colors for CLI output.
// Ensures the main config file exists and is parsed correctly.
//================================================================================
// 
//     ██╗     ███╗   ██╗███████╗
//    ██║     ██╔██╗  ██║██╔════╝
//    ██║     ██║╚██╗ ██║███████╗
//    ██║     ██║ ╚████║╚════██║
//    ███████╗██║  ╚███║███████║
//    ╚══════╝╚═╝   ╚══╝╚══════╝
//
// Author      : Sassi Souid
// Email       : locnetserve@gmail.com
// Project     : LocNetServe
// Version     : 1.0.0
// Created     : 2025
// Last Update : 2025
// License     : MIT
//================================================================================

//-------------------------------------------------------------
// ANSI color codes for terminal output
//-------------------------------------------------------------
$colors = [
    'RESET'   => "\033[0m",
    'RED'     => "\033[31m",
    'GREEN'   => "\033[32m",
    'YELLOW'  => "\033[33m",
    'BLUE'    => "\033[34m",
    'CYAN'    => "\033[36m",
    'BOLD'    => "\033[1m",
    'MAGENTA' => "\033[35m",
	'WHITE'       => "\033[37m"
];

//-------------------------------------------------------------
// Available languages
//-------------------------------------------------------------
$languages = [
    'en' => "English",
    'fr' => "French", 
    'es' => "Spanish"
];

//-------------------------------------------------------------
// Load main configuration file
//-------------------------------------------------------------
$base_dir = dirname(dirname(dirname(__DIR__)));
$conf = $base_dir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR;
$config_path = $conf . 'config.json';

if (!file_exists($config_path)) {
    die("Configuration file not found: " . $config_path);
}

$raw_config = file_get_contents($config_path);
$config = json_decode($raw_config, true);

if (!is_array($config)) {
    die("Invalid JSON in config file: " . $config_path);
}

//-------------------------------------------------------------
// Build comprehensive paths configuration
//-------------------------------------------------------------
$paths = [
    'base' => $base_dir . DIRECTORY_SEPARATOR,
    'config' => $conf,
    'config_file' => $config_path,
    'icons' => $config['paths']['icons'] ?? $base_dir . DIRECTORY_SEPARATOR . 'icons' . DIRECTORY_SEPARATOR,
    'locales' => $config['paths']['locales'] ?? $base_dir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'locales' . DIRECTORY_SEPARATOR,
    'cmd' => $config['paths']['cmd'] ?? $base_dir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'cmd' . DIRECTORY_SEPARATOR,
    'dashboard' => $config['paths']['dashboard'] ?? $base_dir . DIRECTORY_SEPARATOR . 'apps' . DIRECTORY_SEPARATOR . 'dashboard' . DIRECTORY_SEPARATOR,
    
    // Service paths
    'apache' => $config['paths']['apache'] ?? $base_dir . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'apache' . DIRECTORY_SEPARATOR,
    'mysql' => $config['paths']['mysql'] ?? $base_dir . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'mysql' . DIRECTORY_SEPARATOR,
    'php' => $config['paths']['php'] ?? $base_dir . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR,
    
    // Log paths
    'apache_logs' => ($config['paths']['apache'] ?? $base_dir . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'apache') . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR,
    'mysql_logs' => ($config['paths']['mysql'] ?? $base_dir . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'mysql') . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR,
];

//-------------------------------------------------------------
// Service configurations with fallbacks
//-------------------------------------------------------------
$services = [
    'Apache' => [
        'exe' => $config['services']['Apache']['exe'] ?? $paths['apache'] . 'bin' . DIRECTORY_SEPARATOR . 'httpd.exe',
        'process' => $config['services']['Apache']['process'] ?? 'httpd.exe',
        'service' => $config['services']['Apache']['service'] ?? 'Apache2.4',
        'log_file' => $config['services']['Apache']['log_file'] ?? $paths['apache_logs'] . 'error.log',
        'version_cmd' => $config['services']['Apache']['version_cmd'] ?? '-v',
        'version_file' => $config['services']['Apache']['version_file'] ?? $paths['apache'] . 'bin' . DIRECTORY_SEPARATOR . 'httpd.exe',
        'hide_window' => $config['services']['Apache']['hide_window'] ?? 1,
        'conf' => $config['services']['Apache']['conf'] ?? $paths['apache'] . 'conf' . DIRECTORY_SEPARATOR . 'httpd.conf', // ⭐ AJOUT
    ],
    'MySQL' => [
        'exe' => $config['services']['MySQL']['exe'] ?? $paths['mysql'] . 'bin' . DIRECTORY_SEPARATOR . 'mysqld.exe',
        'admin' => $config['services']['MySQL']['admin'] ?? $paths['mysql'] . 'bin' . DIRECTORY_SEPARATOR . 'mysqladmin.exe',
        'dump' => $config['services']['MySQL']['dump'] ?? $paths['mysql'] . 'bin' . DIRECTORY_SEPARATOR . 'mysqldump.exe', // ⭐ AJOUT
        'client' => $config['services']['MySQL']['client'] ?? $paths['mysql'] . 'bin' . DIRECTORY_SEPARATOR . 'mysql.exe', // ⭐ AJOUT
        'process' => $config['services']['MySQL']['process'] ?? 'mysqld.exe',
        'service' => $config['services']['MySQL']['service'] ?? 'MySQL94',
        'user' => $config['services']['MySQL']['user'] ?? 'root',
        'password' => $config['services']['MySQL']['password'] ?? '',
        'host' => $config['services']['MySQL']['host'] ?? 'localhost', // ⭐ AJOUT
        'log_file' => $config['services']['MySQL']['log_file'] ?? $paths['mysql_logs'] . 'mysql_error.log',
        'version_cmd' => $config['services']['MySQL']['version_cmd'] ?? '-version',
        'version_file' => $config['services']['MySQL']['version_file'] ?? $paths['mysql'] . 'bin' . DIRECTORY_SEPARATOR . 'mysqld.exe',
        'hide_window' => $config['services']['MySQL']['hide_window'] ?? 1,
    ],
    'PHP' => [
        'exe' => $config['services']['PHP']['exe'] ?? $paths['php'] . 'php.exe',
        'version_cmd' => $config['services']['PHP']['version_cmd'] ?? '-v',
        'version_file' => $config['services']['PHP']['version_file'] ?? $paths['php'] . 'php.exe',
        'url_info' => $config['services']['PHP']['url_info'] ?? 'http://localhost/info.php',
    ],
    'versions' => [ // ⭐ NOUVELLE SECTION
        'apache' => $config['services']['versions']['apache'] ?? '2.4.65',
        'mysql' => $config['services']['versions']['mysql'] ?? '8.0.34', 
        'php' => $config['services']['versions']['php'] ?? '8.2.8',
        'locnetserve' => $config['services']['versions']['locnetserve'] ?? 'LocNetServe v1.0.0'
    ]
];

//-------------------------------------------------------------
// Version information
//-------------------------------------------------------------
$versions = [
    'locnetserve' => $config['myserver']['version'] ?? 'v1.0.0',
    'apache' => $config['services']['versions']['apache'] ?? '2.4.65',
    'mysql' => $config['services']['versions']['mysql'] ?? '8.0.34',
    'php' => $config['services']['versions']['php'] ?? '8.2.8',
];

//-------------------------------------------------------------
// Settings with defaults
//-------------------------------------------------------------
$settings = [
    'language' => $config['settings']['language'] ?? 'en',
    'refresh_interval' => $config['settings']['refreshInterval'] ?? '3000',
    'timestamps' => [
        'lang' => $config['settings']['t_lang'] ?? time(),
        'pid' => $config['settings']['t_pid'] ?? time(),
        'serve' => $config['settings']['t_serve'] ?? time(),
    ]
];

//-------------------------------------------------------------
// Icons configuration
//-------------------------------------------------------------
$icons = [
    'custom' => $config['icons']['custom'] ?? 'logo1.png',
    'tray' => [
        'off' => $config['icons']['tray']['off'] ?? 'logo2.png',
        'on' => $config['icons']['tray']['on'] ?? 'logo3.png',
        'stopped' => $config['icons']['tray']['stopped'] ?? 'logo1.png',
    ],
    'apache' => [
        'off' => $config['icons']['apache']['off'] ?? 'light.ico',
        'on' => $config['icons']['apache']['on'] ?? 'green.ico',
    ],
    'mysql' => [
        'off' => $config['icons']['mysql']['off'] ?? 'light.ico',
        'on' => $config['icons']['mysql']['on'] ?? 'green.ico',
    ]
];

//-------------------------------------------------------------
// Set comprehensive globals
//-------------------------------------------------------------
$GLOBALS['colors'] = $colors;
$GLOBALS['languages'] = $languages;
$GLOBALS['config_path'] = $config_path;
$GLOBALS['config'] = $config;

// Enhanced structured globals
$GLOBALS['app'] = [
    'name' => 'LocNetServe',
    'version' => $versions['locnetserve'],
    'status' => $config['myserver']['status'] ?? 'running',
];

$GLOBALS['paths'] = $paths;
$GLOBALS['services'] = $services;
$GLOBALS['versions'] = $versions;
$GLOBALS['settings'] = $settings;
$GLOBALS['icons'] = $icons;

//-------------------------------------------------------------
// Utility function to get service executable path safely
//-------------------------------------------------------------
function get_service_exe(string $service): string {
    return $GLOBALS['services'][$service]['exe'] ?? '';
}

//-------------------------------------------------------------
// Utility function to get service version
//-------------------------------------------------------------
function get_service_version(string $service): string {
    return $GLOBALS['versions'][strtolower($service)] ?? 'unknown';
}

//-------------------------------------------------------------
// Debug function (optional)
//-------------------------------------------------------------
function debug_config(): void {
    if (isset($_SERVER['argv']) && in_array('--debug', $_SERVER['argv'])) {
        echo $GLOBALS['colors']['CYAN'] . "Config loaded from: " . $GLOBALS['config_path'] . $GLOBALS['colors']['RESET'] . PHP_EOL;
        echo $GLOBALS['colors']['GREEN'] . "LocNetServe version: " . $GLOBALS['app']['version'] . $GLOBALS['colors']['RESET'] . PHP_EOL;
    }
}

// Call debug if needed
debug_config();
