<?php
/**
 * -------------------------------------------------------------
 *  main.php - Primary CLI Router for LocNetServe
 * -------------------------------------------------------------
 *  Acts as the central command router for LocNetServe by:
 *    - Processing CLI commands and routing to appropriate handlers
 *    - Supporting core actions, categories, and utility shortcuts
 *    - Validating commands and providing comprehensive help system
 *    - Managing command parsing and execution flow
 *
 *  Author : Sassi Souid
 *  Email  : locnetserve@gmail.com
 *  Project: LocNetServe
 *  Version: 1.0.0
 * -------------------------------------------------------------
 */

// Include routes which handles configuration, PID, BOM, autoloader
$routes = require_once __DIR__ . '/routes/index.php';
$colors = $routes['colors'];
$config = $routes['config'];
$commandValidator = $routes['commandValidator'] ?? null;
$core_actions = $routes['coreActions'] ?? ["-v", "status",  "lang", "start", "stop"];
$category_map = $routes['category_map'];


/**
 * -------------------------------------------------------------
 * route_command
 * Routes a CLI command to the appropriate handler class.
 *
 * Special handling for 'help' command to allow optional category argument.
 *
 * @param string $category Command category (e.g., "core", "apache", "help")
 * @param string $action   Action or subcommand (e.g., "-v", "status")
 * @param array  $args     Additional CLI arguments
 * @return string          Result message
 * -------------------------------------------------------------
 */
function route_command(string $category, string $action, array $args = []): string {
	

    global $category_map, $colors, $config, $commandValidator;

    $category = trim($category);
    
    // ---------------------------------------------------------
    // Special handling for 'help'
    // ---------------------------------------------------------
 

    // ---------------------------------------------------------
    // Validate command for all other categories
    // ---------------------------------------------------------
    if ($commandValidator) {
        list($isValid, $errorMessage, $expectedArgs) = validate_command($category, $action, $args);
        if (!$isValid) {
            return $errorMessage;
        }
    }

    // ---------------------------------------------------------
    // Check if category exists
    // ---------------------------------------------------------
    if (!in_array($category, ["help","core","apache","mysql","php","laravel", "utils", "vhosts"])) {
        return $colors['RED'] . "Unknown category : $category" . $colors['RESET'];
    }

    $handler_class = $category_map[$category];

    // ---------------------------------------------------------
    // Check if handler class exists
    // ---------------------------------------------------------
    if (!class_exists($handler_class)) {
        return $colors['RED'] . "Handler class not found: $handler_class" . $colors['RESET'];
    }

    // ---------------------------------------------------------
    // Instantiate handler
    // ---------------------------------------------------------
    try {
        if (strpos($handler_class, '\\') !== false) {
            $handler = new $handler_class($colors, $config);
        } else {
            $handler = new $handler_class();
        }
    } catch (\Exception $e) {
        return $colors['RED'] . "Error creating handler: " . $e->getMessage() . $colors['RESET'];
    }

    // ---------------------------------------------------------
    // Execute handler
    // ---------------------------------------------------------
    if (method_exists($handler, 'execute')) {
        return $handler->execute($action, $args);
    }

    // Fallback: try category-specific help methods
    if (method_exists($handler, 'getHelp')) {
        return $handler->getHelp();
    }

    return $colors['RED'] . "No valid method found for action: $category $action" . $colors['RESET'];
}


/**
 * Parse command line arguments and extract category, action, and args
 */

function parse_arguments(array $argv): array {
    if (count($argv) < 2) {
        return ['error' => 'No arguments provided'];
    }

    global $core_actions;

    $category = $argv[1];
    $action = '';
    $args = [];

    // ---------------------------------------------------------
    // 1. CORE ACTIONS DIRECT (e.g. -v, status, start)
    // ---------------------------------------------------------
    if (in_array($category, $core_actions, true)) {
        return [
            'category' => 'core',
            'action'   => $category,
            'args'     => array_slice($argv, 2)
        ];
    }

    // ---------------------------------------------------------
    // 2. UTILS CATEGORY SHORTCUT (-u ...)
    // ---------------------------------------------------------
    if ($category === '-u') {
        // Remap '-u' to 'utils' category
        $realAction = $argv[2] ?? '';
        $args = array_slice($argv, 3);

        return [
            'category' => 'utils',
            'action'   => $realAction,
            'args'     => $args
        ];
    }    
	if ($category === '-vh') {
        // Remap '-vh' to 'vhosts' category
        $realAction = $argv[2] ?? '';
        $args = array_slice($argv, 3);

        return [
            'category' => 'vhosts',
            'action'   => $realAction,
            'args'     => $args
        ];
    }

    // ---------------------------------------------------------
    // 3. OTHER CATEGORIES (apache, mysql, php, etc.)
    // ---------------------------------------------------------
    if (isset($argv[2])) {
        $action = $argv[2];
        $args = array_slice($argv, 3);
    } else {
        $action = 'help'; // Default action
    }

    return [
        'category' => $category,
        'action'   => $action,
        'args'     => $args
    ];
}


// -------------------------------------------------------------
// CLI Entry Point
// -------------------------------------------------------------
if (isset($argv[1])) {
    $parsed = parse_arguments($argv);
   
	if(count($parsed['args']) >0 && $parsed['args'][0]==""){
		
		$parsed['args']=[];
	}
	if (isset($parsed['error'])) {
        echo $colors['RED'] . $parsed['error'] . $colors['RESET'] . PHP_EOL;
        exit(1);
    }

    if (isset($argv[1]) && $argv[1] === '--debug') {
        echo "DEBUG - Category: '{$parsed['category']}', Action: '{$parsed['action']}'" . PHP_EOL;
        echo "DEBUG - Args: " . implode(', ', $parsed['args']) . PHP_EOL;
    }

    $result = route_command($parsed['category'], $parsed['action'], $parsed['args']);
    echo $result . PHP_EOL;
} else {
    echo $colors['MAGENTA'] . "LocNetServe CLI Router" . $colors['RESET'] . PHP_EOL;
    echo "Usage: lns <category> <action> [arguments...]" . PHP_EOL . PHP_EOL;
    
    echo $colors['CYAN'] . "Available categories:" . $colors['RESET'] . PHP_EOL;
    foreach ($category_map as $category => $handler) {
        echo "  " . $colors['GREEN'] . $category . $colors['RESET'] . PHP_EOL;
    }
    
    echo PHP_EOL . $colors['YELLOW'] . "Core commands:" . $colors['RESET'] . PHP_EOL;
    echo "  " . $colors['GREEN'] . "-v" . $colors['RESET'] . " - Show version" . PHP_EOL;
    echo "  " . $colors['GREEN'] . "status" . $colors['RESET'] . " - Show service status" . PHP_EOL;
    echo "  " . $colors['GREEN'] . "help" . $colors['RESET'] . " - Show help" . PHP_EOL;
    
    echo PHP_EOL . $colors['YELLOW'] . "Examples:" . $colors['RESET'] . PHP_EOL;
    echo "  lns help" . PHP_EOL;
    echo "  lns -v" . PHP_EOL;
    echo "  lns status" . PHP_EOL;
    echo "  lns mysql status" . PHP_EOL;
    echo "  lns apache version" . PHP_EOL;
}
