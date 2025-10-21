<?php
/**
 * -------------------------------------------------------------
 *  HelpCommands.php - Help System for LocNetServe
 * -------------------------------------------------------------
 *  Provides comprehensive command documentation by:
 *    - Displaying all available commands or filtered by category
 *    - Organizing commands in structured, color-coded format
 *    - Loading command definitions from JSON configuration
 *    - Supporting both general and category-specific help
 *
 *  Author : Sassi Souid
 *  Email  : locnetserve@gmail.com
 *  Project: LocNetServe
 *  Version: 1.0.0
 * -------------------------------------------------------------
 */

namespace Core\Commands;

use Core\Commands\Command;

class HelpCommands implements Command
{
    private array $colors;
    private array $config;

    private array $category_order = [
        'core', 'apache', 'mysql', 'php', 'laravel', 'update', 'logs', 'utils', 'vhosts'
    ];

    public function __construct(array $colors, array $config)
    {
        $this->colors = $colors;
        $this->config = $config;
    }

    public function execute(string $action, array $args = []): string
    {
		
		if(empty($action)){
			$categoryFilter = $args[0] ?? null;
		}else{
			$categoryFilter = $action;
			
		}
        
		
        return $this->showHelp($categoryFilter);
    }

    public function showHelp(string $categoryName = null): string
    {
        $commands_path = $this->config['paths']['cmd'] . DIRECTORY_SEPARATOR . 'cmd.json';
        if (!file_exists($commands_path)) {
            return $this->colors['RED'] . "Commands file not found: $commands_path" . $this->colors['RESET'];
        }

        $json = json_decode(file_get_contents($commands_path), true);
        if (!$json || !isset($json['commands'])) {
            return $this->colors['RED'] . "Invalid or empty commands file." . $this->colors['RESET'];
        }

        $commands = $json['commands'];

        if ($categoryName && isset($commands[$categoryName])) {
            return $this->formatHelpOutput([$categoryName => $commands[$categoryName]], $categoryName);
        }

        return $this->formatHelpOutput($commands, null);
    }

    private function formatHelpOutput(array $commands, ?string $categoryName): string
    {
        $output = $this->colors['BOLD'] . "LocNetServe V1.0.0 Commands" . $this->colors['RESET'];
        if ($categoryName) {
            $output .= " - " . $this->colors['CYAN'] . strtoupper($categoryName) . $this->colors['RESET'] . " Category";
        }
        $output .= PHP_EOL . str_repeat("=", 50) . PHP_EOL . PHP_EOL;

        foreach ($this->category_order as $cat) {
            if (!isset($commands[$cat])) continue;
            $output .= $this->colors['MAGENTA'] . "[" . strtoupper($cat) . "]" . $this->colors['RESET'] . PHP_EOL;
            foreach ($commands[$cat] as $action => $desc) {
                $cmd_text = "lns $action";
                $padding = str_repeat(" ", max(1, 35 - strlen($cmd_text)));
                $output .= "  " . $this->colors['GREEN'] . $cmd_text . $this->colors['RESET'] . $padding . $desc . PHP_EOL;
            }
            $output .= PHP_EOL;
        }

        if (!$categoryName) {
            $output .= $this->colors['YELLOW'] . "Use 'lns help <category>' to see specific commands." . $this->colors['RESET'] . PHP_EOL;
        }

        return $output;
    }
	
	public function getHelp(): string
{
    return $this->colors['GREEN'] . "lns help" . $this->colors['RESET'] .
           " - Show all available commands" . PHP_EOL .
           $this->colors['GREEN'] . "lns help <category>" . $this->colors['RESET'] .
           " - Show commands for a specific category" . PHP_EOL .
           $this->colors['YELLOW'] . "Examples: lns help core | lns help mysql" . $this->colors['RESET'];
}
}
