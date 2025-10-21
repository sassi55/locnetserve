<?php
/**
 * -------------------------------------------------------------
 *  routes/index.php - Central Autoloader and Router for LocNetServe
 * -------------------------------------------------------------
 *  Provides centralized routing and autoloading system by:
 *    - Loading essential configuration and functional files
 *    - Registering autoloader for namespaced classes
 *    - Initializing command validator and category mapping
 *    - Defining core CLI actions and helper functions
 *
 *  Author : Sassi Souid
 *  Email  : locnetserve@gmail.com
 *  Project: LocNetServe
 *  Version: 1.0.0
 * -------------------------------------------------------------
 */

// -------------------------------------------------------------
// 1. Define base directories
// -------------------------------------------------------------
define('BASE_DIR', dirname(__DIR__, 3));  // C:\MyServer
define('CONF_DIR', BASE_DIR . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'functions' . DIRECTORY_SEPARATOR . 'conf' . DIRECTORY_SEPARATOR);
define('FILES_DIR', BASE_DIR . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'functions' . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR);
define('CLASSES_DIR', BASE_DIR . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'functions' . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR);
define('CMD_FILE', BASE_DIR. DIRECTORY_SEPARATOR . 'config'. DIRECTORY_SEPARATOR .'cmd' . DIRECTORY_SEPARATOR . 'cmd.json');
define('FILES_BACKUP', BASE_DIR . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR);
// -------------------------------------------------------------
// 2. Include global configuration with error handling
// -------------------------------------------------------------
if (!file_exists(CONF_DIR . 'configs.php')) {
    die("Error: Configuration file not found at " . CONF_DIR . 'configs.php');
}
require_once CONF_DIR . 'configs.php';   // $colors, $conf, $config

// -------------------------------------------------------------
// 3. Include essential functional files with error handling
// -------------------------------------------------------------
$essential_files = [
    FILES_DIR . 'pid.php',      // PID management
    FILES_DIR . 'bom.php',      // remove_bom()
];

foreach ($essential_files as $file) {
    if (!file_exists($file)) {
        die("Error: Essential file not found: $file");
    }
    require_once $file;
}

// -------------------------------------------------------------
// 4. Enhanced Autoloader for namespaced classes
// -------------------------------------------------------------
spl_autoload_register(function ($class_name) {
    $class_path = str_replace('\\', DIRECTORY_SEPARATOR, $class_name);
    $file = CLASSES_DIR . $class_path . '.php';
    
    if (file_exists($file)) {
        require_once $file;
        return true;
    }
    
    $legacy_file = CLASSES_DIR . $class_name . '.php';
    if (file_exists($legacy_file)) {
        require_once $legacy_file;
        return true;
    }
    
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        error_log("Autoloader: Class not found - $class_name");
    }
    
    return false;
});

// -------------------------------------------------------------
// 5. Load commands configuration and initialize CommandValidator
// -------------------------------------------------------------
$commands_config = [];
$commandValidator = null;

// ⚡ Déclaration des core actions (mêmes que dans main.php)
$core_actions = ["-v", "status",  "lang", "start", "stop"];



// Map command categories to their handler classes
$category_map = [
    'help'     => 'Core\Commands\HelpCommands',
    'core'     => 'Core\Commands\CoreCommands',
    'apache'   => 'Web\Commands\ApacheCommand',
    'mysql'    => 'Database\Commands\MySQLDatabaseCommand',
    'php'      => 'Web\Commands\PHPCommand',
    'laravel'  => 'Framework\Commands\LaravelCommandHandler',
    'update'   => 'System\Commands\UpdateCommand',
    'logs'     => 'System\Commands\LogCommand',
    'utils'    => 'Utils\Commands\UtilsCommand',
    'vhosts'    => 'Vhosts\Commands\VhostsCommand'
];



try {
   
    if (file_exists(CMD_FILE)) {
        $json = json_decode(file_get_contents(CMD_FILE), true);
        $commands_config = $json['commands'] ?? [];
        
        if (class_exists('Core\Utils\CommandValidator')) {
            // ⚡ On passe $core_actions au constructeur
            $commandValidator = new Core\Utils\CommandValidator($commands_config, $colors, $core_actions);
        } else {
            error_log("Warning: CommandValidator class not found, command validation disabled");
        }
    } else {
        error_log("Warning: Commands configuration file not found at: " . CMD_FILE);

    }
} catch (Exception $e) {
    error_log("Error loading commands configuration: " . $e->getMessage());
}

// -------------------------------------------------------------
// 6. CLI helper function (optional, reusable)
// -------------------------------------------------------------
function cli_echo(string $text, string $color = ''): void {
    global $colors;
    $c = $colors[$color] ?? '';
    echo $c . $text . $colors['RESET'] . PHP_EOL;
}

// -------------------------------------------------------------
// 7. Command validation helper function
// -------------------------------------------------------------
function validate_command(string $category, string $action, array $args = []): array {
    global $commandValidator;
    
    if (!$commandValidator) {
        return [true, '', []];
    }
    
    return $commandValidator->validate($category, $action, $args);
}

// -------------------------------------------------------------
// 8. Return loaded globals for other scripts
// -------------------------------------------------------------
return [
    'colors' => $colors,
    'conf'   => $conf,
    'config' => $config,
    'commandValidator' => $commandValidator,
    'commandsConfig' => $commands_config,
    'category_map' => $category_map,
    'coreActions' => $core_actions, // ⚡ exposer aussi
    'paths'  => [
        'base' => BASE_DIR,
        'classes' => CLASSES_DIR,
        'config' => CONF_DIR,
        'files' => FILES_DIR
    ]
];
