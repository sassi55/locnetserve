<?php
/**
 * -------------------------------------------------------------
 *  CoreCommands.php - Core Command Handler for LocNetServe
 * -------------------------------------------------------------
 *  Handles core system actions and operations by:
 *    - Managing version display and service status monitoring
 *    - Controlling language settings and server lifecycle
 *    - Integrating with Apache, MySQL, and service managers
 *    - Providing system-wide command execution interface
 *
 *  Author : Sassi Souid
 *  Email  : locnetserve@gmail.com
 *  Project: LocNetServe
 *  Version: 1.0.0
 * -------------------------------------------------------------
 */

namespace Core\Commands;

use Core\Commands\Command;
use Core\Commands\ServiceStatusCommand;
use Web\Commands\ApacheCommand;
use Database\Commands\MySQLCommandHandler;
use Core\Utils\ServerManager;

class CoreCommands implements Command
{
    private array $colors;
    private array $config;
    private array $langs;
    private string $config_path;
    private ServerManager $server;
    private ApacheCommand $apache;
    private MySQLCommandHandler $mysql;

    public function __construct(array $colors, array $config)
    {
        $this->colors = $colors;
        $this->config = $config;
        $this->langs = $GLOBALS['languages'] ?? ['en' => 'English', 'fr' => 'French', 'es' => 'Spanish'];
        $this->config_path = $GLOBALS['config_path'] ?? '';
        $this->server = new ServerManager();
        $this->apache = new ApacheCommand($colors, $config);
        $this->mysql = new MySQLCommandHandler($colors, $config);
    }

    public function execute(string $action, array $args = []): string
    {
        switch ($action) {
            case '-v':
                return $this->showVersion();

            case 'status':
                if (!empty($args) && $args[0] === 'detail') {
                    $statusCommand = new ServiceStatusCommand($this->colors, $this->config);
                    return $statusCommand->execute('detail');
                }
                return $this->showStatus();

            case 'lang':
                if (!empty($args)) {
                    return $this->setLanguage($args[0]);
                }
                return $this->getCurrentLanguage();

			case 'start':
				return $this->startServer();
			
			case 'stop':
				return $this->stopServer();

            default:
                return $this->colors['RED'] . "Unknown core action: $action" . $this->colors['RESET'];
        }
    }

    private function showVersion(): string
    {
        $version = $this->config['myserver']['version'] ?? "unknown";
        return $this->colors['GREEN'] . "LocNetServe version: $version" . $this->colors['RESET'];
    }

    private function showStatus(): string
    {
        $output = [];

        if ($this->server->isRunning()) {
            $output[] = $this->colors['GREEN'] . "LocNetServe is running (PID: " . $this->server->getPid() . ")" . $this->colors['RESET'];
        } else {
            $output[] = $this->colors['MAGENTA'] . "LocNetServe is not running." . $this->colors['RESET'];
        }

        $output[] = $this->apache->execute('status');
        $output[] = $this->mysql->execute('status');

        return implode(PHP_EOL, $output);
    }

    private function getCurrentLanguage(): string
    {
        $currentLang = $this->config['settings']['language'] ?? 'en';
        $langName = $this->langs[$currentLang] ?? $this->langs['en'];
        return $this->colors['GREEN'] . "Current language: $langName" . $this->colors['RESET'];
    }

    private function setLanguage(string $lang): string
    {
        $validLangs = ['en', 'fr', 'es'];

        if (!in_array($lang, $validLangs)) {
            return $this->colors['RED'] . "Invalid language code: $lang. Available: " . implode(', ', $validLangs) . $this->colors['RESET'];
        }

        if (!isset($this->langs[$lang])) {
            return $this->colors['RED'] . "Language not configured: $lang" . $this->colors['RESET'];
        }

        if (file_exists($this->config_path)) {
            $json_data = json_decode(file_get_contents($this->config_path), true);
            if (isset($json_data['settings'])) {
                $json_data['settings']['language'] = $lang;
                $json_data['settings']['t_lang'] = (string)time();
                file_put_contents(
                    $this->config_path,
                    json_encode($json_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                );
            }
        }

        $lang_name = $this->langs[$lang];
        return $this->colors['GREEN'] . "Language set to: $lang_name" . $this->colors['RESET'];
    }
	/**
	 * Start LocNetServe main process.
	 *
	 * @return string
	 */
	private function startServer(): string
	{
		if ($this->server->isRunning()) {
			return $this->colors['YELLOW'] . "LocNetServe is already running (PID: " . $this->server->getPid() . ")" . $this->colors['RESET'];
		}

		$result = $this->server->start(); // méthode déjà présente dans ServerManager ?
		if ($result) {
			return $this->colors['GREEN'] . "LocNetServe started successfully!" . $this->colors['RESET'];
		} else {
			return $this->colors['RED'] . "Failed to start LocNetServe process." . $this->colors['RESET'];
		}
	}
	/**
	 * Stop LocNetServe process.
	 *
	 * @return string
	 */
	private function stopServer(): string
	{
		if (!$this->server->isRunning()) {
			return $this->colors['MAGENTA'] . "LocNetServe is not running." . $this->colors['RESET'];
		}

		$result = $this->server->stop();

		if ($result) {
			return $this->colors['GREEN'] . "LocNetServe stopped successfully." . $this->colors['RESET'];
		}

		return $this->colors['RED'] . "Failed to stop LocNetServe process." . $this->colors['RESET'];
	}	
    public function getHelp(): string
    {
        return $this->colors['GREEN'] . "lns -v" . $this->colors['RESET'] .
               " - Show LocNetServe version" . PHP_EOL .
               $this->colors['GREEN'] . "lns status" . $this->colors['RESET'] .
               " - Show basic service status" . PHP_EOL .
               $this->colors['GREEN'] . "lns status detail" . $this->colors['RESET'] .
               " - Show detailed service status" . PHP_EOL .
               $this->colors['GREEN'] . "lns lang" . $this->colors['RESET'] .
               " - Show current language" . PHP_EOL .
               $this->colors['GREEN'] . "lns lang <code>" . $this->colors['RESET'] .
               " - Set language (en, fr, es)" . PHP_EOL .
               $this->colors['CYAN'] . "Usage examples:" . $this->colors['RESET'] . PHP_EOL .
               "  lns -v" . PHP_EOL .
               "  lns status" . PHP_EOL .
               "  lns lang fr";
    }
}
