<?php
/**
 * -------------------------------------------------------------
 *  CommandValidator.php - Command Structure Validator for LocNetServe
 * -------------------------------------------------------------
 *  Validates CLI command structure and arguments by:
 *    - Checking commands against cmd.json configuration
 *    - Validating argument counts and types across all categories
 *    - Providing detailed error messages for invalid commands
 *    - Supporting core, help, PHP, MySQL, utils, and vhosts categories
 *
 *  Author : Sassi Souid
 *  Email  : locnetserve@gmail.com
 *  Project: LocNetServe
 *  Version: 1.0.0
 * -------------------------------------------------------------
 */

namespace Core\Utils;

use Core\Utils\ValidationErrorHandler;

class CommandValidator
{
    private array $commandsConfig;
    private array $colors;
    private array $coreActions;
    private ValidationErrorHandler $errors;

    /**
     * Constructor
     */
    public function __construct(array $commandsConfig, array $colors, array $coreActions = [])
    {
        $this->commandsConfig = $commandsConfig;
        $this->colors = $colors;
        $this->coreActions = $coreActions;
        $this->errors = new ValidationErrorHandler($colors);
    }

    /**
     * Validate a command against cmd.json configuration.
     *
     * @return array [isValid (bool), errorMessage (string), expectedArgs (int)]
     */
    public function validate(string $category, string $action, array $args): array
    {
         
		switch ($category) {
            // -------------------------------------------------------------
            // 1. CORE CATEGORY
            // -------------------------------------------------------------
            case 'core':
                if (in_array($action, $this->coreActions, true)) {
                    if (count($args) > 0 && $action !== "lang" && !in_array($args[0], ["detail"])) {
                        return $this->errors->tooManyArgs($action, $args[0]);
                    }

                    if ($action === "lang" && count($args) > 1) {
                        return $this->errors->tooManyArgs($action, implode(' ', $args));
                    }

                    if ($action === "status" && count($args) > 1) {
                        return $this->errors->tooManyArgs($action, $args[1]);
                    }

                    return $this->errors->ok();
                }
                break;

				// -------------------------------------------------------------
				// 2. HELP CATEGORY
				// -------------------------------------------------------------
				case 'help':
					// Si une action est spécifiée
					if (!empty($action)) {
						// Vérifier si l'action est une catégorie valide
						$availableCategories = array_keys($this->commandsConfig);
						if (!in_array($action, $availableCategories)) {
							return $this->errors->unknownCommand("$category $action");
						}
						
						// Si des arguments supplémentaires sont fournis après une catégorie valide
						if (count($args) > 0) {
							return $this->errors->unknownCommand("$category $action {$args[0]}");
						}
					}
					
					return $this->errors->ok();
					break;           
            // 3. PHP CATEGORY
            // -------------------------------------------------------------
            case 'php':
                switch ($action) {
                    case 'ext':
                        if (count($args) > 1 && in_array(trim($args[0]), ["list", "available"])) {
                            return $this->errors->tooManyArgs("php $action", $args[1]);
                        }
                        break;

                    case 'version':
                    case 'info':
                    case 'ini':
                    case 'update':
                        if (count($args) > 0) {
                            return $this->errors->tooManyArgs("php $action", $args[0]);
                        }
                        break;
                }

                $commandKey = trim($action . ' ' . implode(' ', $args));
                break;
   
   
   
			// -------------------------------------------------------------
			// 4. MYSQL CATEGORY
			// -------------------------------------------------------------
			case 'mysql':
				// Specific validation for MySQL commands with arguments
				switch ($action) {
					case 'export':
					case 'import':
					case 'create':
					case 'drop':
					case 'data':
						 
		
						$expectedArgs = $this->getMySQLExpectedArgs($action, $args);
						
						if (count($args) < $expectedArgs) {
							return $this->errors->missingArgs("mysql $action", $expectedArgs);
						}
						if (count($args) > $expectedArgs) {
							$extraArgs = array_slice($args, $expectedArgs);
							return $this->errors->tooManyArgs("mysql $action", implode(' ', $extraArgs));
						}
						return $this->errors->ok($expectedArgs);
						
					case 'user':
						// commandes user add/del
						if (count($args) === 0) {
							return $this->errors->missingArgs("mysql user", 1);
						}
						$subAction = $args[0];
						if ($subAction === 'add') {
							$expectedArgs = 3; // user add <username> <password>
							if (count($args) < $expectedArgs) {
								return $this->errors->missingArgs("mysql user add", $expectedArgs - 1);
							}
							if (count($args) > $expectedArgs) {
								$extraArgs = array_slice($args, $expectedArgs);
								return $this->errors->tooManyArgs("mysql user add", implode(' ', $extraArgs));
							}
						} elseif ($subAction === 'del') {
							$expectedArgs = 2; // user del <username>
							if (count($args) < $expectedArgs) {
								return $this->errors->missingArgs("mysql user del", $expectedArgs - 1);
							}
							if (count($args) > $expectedArgs) {
								$extraArgs = array_slice($args, $expectedArgs);
								return $this->errors->tooManyArgs("mysql user del", implode(' ', $extraArgs));
							}
						}
						return $this->errors->ok();
						break;
					case 'users':
					case 'status':
					case 'ports':
					case 'shell':
					case 'version':
					case 'health':
						
						if (count($args) > 0 && !in_array($args[0],['info'])) {
							return $this->errors->tooManyArgs("mysql $action", $args[0]);
						}
						return $this->errors->ok();
						
					default:
						
						$commandKey = "$category $action";
						if (!array_key_exists($commandKey, $this->commandsConfig[$category])) {
							return $this->errors->unknownCommand($commandKey);
						}
						
						$expectedArgs = $this->countPlaceholders($this->commandsConfig[$category][$commandKey] ?? '');
						if (count($args) < $expectedArgs) {
							return $this->errors->missingArgs("$category $action", $expectedArgs);
						}
						if (count($args) > $expectedArgs) {
							$extraArgs = array_slice($args, $expectedArgs);
							return $this->errors->tooManyArgs("$category $action", implode(' ', $extraArgs));
						}
						return $this->errors->ok($expectedArgs);
				}
				break;
   
   
   
   
   
				// -------------------------------------------------------------
				// 4. UTILS CATEGORY (robust lookup + helpful message)
				// -------------------------------------------------------------
				case 'utils':
					if (!isset($this->commandsConfig[$category])) {
						return $this->errors->unknownCategory($category);
					}

					// Normalize action for lookup in cmd.json keys that start with "-u "
					$lookupBase = (stripos($action, '-u') === 0) ? $action : '-u ' . $action;
					  if ($action === 'backup' && count($args) > 0 && $args[0] === 'restore') {
							$commandKey = '-u backup restore <zip>';
							$expectedArgs = 1; // <zip> est requis
							
							if (count($args) - 1 < $expectedArgs) { // -1 car 'restore' est déjà compté
								return $this->errors->missingArgs("$category $action restore", $expectedArgs);
							}
							if (count($args) - 1 > $expectedArgs) {
								$extraArgs = array_slice($args, $expectedArgs + 1);
								return $this->errors->tooManyArgs("$category $action restore", implode(' ', $extraArgs));
							}
							return $this->errors->ok($expectedArgs);
						}
					// 1) Try full lookup with first arg (e.g. "-u backup restore")
					$commandKey = $lookupBase;
					if (count($args) > 0) {
						$candidate = $lookupBase . ' ' . $args[0];
						// normalize spaces
						$candidate = preg_replace('/\s+/', ' ', trim($candidate));
						if (array_key_exists($candidate, $this->commandsConfig[$category])) {
							$commandKey = $candidate;
						}
					}

					// 2) If not found, try lookup base (e.g. "-u backup")
					if (!array_key_exists($commandKey, $this->commandsConfig[$category])) {
						$lookupBaseNorm = preg_replace('/\s+/', ' ', trim($lookupBase));
						if (array_key_exists($lookupBaseNorm, $this->commandsConfig[$category])) {
							$commandKey = $lookupBaseNorm;
						}
					}

					// 3) Fallback: maybe the config uses action without "-u" prefix (rare)
					if (!array_key_exists($commandKey, $this->commandsConfig[$category]) && array_key_exists($action, $this->commandsConfig[$category])) {
						$commandKey = $action;
					}

					// 4) If still not found and user asked only "backup" without subcommand --> provide suggestions
					if (!array_key_exists($commandKey, $this->commandsConfig[$category])) {
						// if user asked 'backup' (or lookupBase is '-u backup') and no args or not matched -> list available subcommands
						if (stripos($lookupBase, '-u backup') === 0) {
							$available = $this->collectSubcommandsForPrefix($this->commandsConfig[$category], '-u backup');
							return $this->errors->missingSubcommand("$category $action", $available);
						}

						// generic unknown utils command
						return $this->errors->unknownUtilsCommand($action);
					}

					
					$actionArgs = $this->countActionArgs($category, "-u $action");
					$expectedArgs = $actionArgs[$commandKey] ?? $this->countPlaceholders($this->commandsConfig[$category][$commandKey] ?? '');
					
					if (count($args) < $expectedArgs) {
						return $this->errors->missingArgs("$category $action", $expectedArgs);
					}

					// CORRECTION: Check for too many arguments
					if (count($args) > $expectedArgs) {
						$extraArgs = array_slice($args, $expectedArgs);
						return $this->errors->tooManyArgs("lns -u $action", implode(' ', $extraArgs));
					}

					return $this->errors->ok($expectedArgs);
                    // -------------------------------------------------------------
				// 5. Vhosts CATEGORY (robust lookup + helpful message)
				// -------------------------------------------------------------
				case 'vhosts':
					if (!isset($this->commandsConfig[$category])) {
						return $this->errors->unknownCategory($category);
					}
					$lookupBase = (stripos($action, '-vh') === 0) ? $action : '-vh ' . $action;
					
					if ($action === 'show' && count($args) > 0 ) {
							$commandKey = '-vh show';
							$expectedArgs = 0; 
							
							$extraArgs = array_slice($args, $expectedArgs);
								return $this->errors->tooManyArgs("$commandKey", implode(' ', $extraArgs));
						}
					
					if ($action === 'open' && count($args) < 1 ) {
							$commandKey = '-vh open';
							$expectedArgs = 1; 
							return $this->errors->missingArgs("$commandKey", $expectedArgs);
							
						}
						
					if ($action === 'open' && count($args) > 1 ) {
							$commandKey = '-vh open';
							$expectedArgs = 1; 
							$extraArgs = array_slice($args, $expectedArgs);
							return $this->errors->tooManyArgs("$commandKey", $extraArgs[0]);
							
						}
					return $this->errors->ok();
					
					echo $lookupBase."\n";
					echo count($args);
					exit;
					
					
            // -------------------------------------------------------------
			// 5. DEFAULT VALIDATION (apache, mysql, laravel, etc.)
			// -------------------------------------------------------------
			default:
				if (!isset($this->commandsConfig[$category])) {
					return $this->errors->unknownCategory($category);
				}

				
				$commandKey = "$category $action" . (!empty($args) ? ' ' . implode(' ', $args) : '');
				
				
				$foundKey = null;
				foreach ($this->commandsConfig[$category] as $key => $description) {
					
					if (strpos($commandKey . ' ', $key . ' ') === 0) {
						$foundKey = $key;
						break;
					}
				}

				
				if (!$foundKey) {
					$baseCommand = "$category $action";
					if (array_key_exists($baseCommand, $this->commandsConfig[$category])) {
						$foundKey = $baseCommand;
					} elseif (array_key_exists($action, $this->commandsConfig[$category])) {
						$foundKey = $action;
					} else {
						return $this->errors->unknownCommand("$category $action");
					}
				}

				
				$expectedArgs = $this->countPlaceholders($this->commandsConfig[$category][$foundKey] ?? '');
				
				$providedArgs = $this->countProvidedArgs("$category $action", $foundKey, $args);
				
				if ($providedArgs < $expectedArgs) {
					return $this->errors->missingArgs("$category $action", $expectedArgs);
				}
				
				if ($providedArgs > $expectedArgs) {
					$extraArgs = array_slice($args, $expectedArgs);
					return $this->errors->tooManyArgs("$category $action", implode(' ', $extraArgs));
				}
				
				return $this->errors->ok($expectedArgs);
					}

        return $this->errors->ok();
    }

    /**
     * Collect subcommands for a given prefix (ex: '-u backup') from configuration keys.
     *
     * @param array  $categoryCommands  The commands array for the category
     * @param string $prefix            The prefix to search for (e.g. '-u backup')
     * @return array                   List of unique subcommand strings (e.g. ['all','mysql','projects','list','restore <zip>'])
     */
    protected function collectSubcommandsForPrefix(array $categoryCommands, string $prefix): array
    {
        $list = [];
        foreach (array_keys($categoryCommands) as $key) {
            $norm = preg_replace('/\s+/', ' ', trim($key));
            if (stripos($norm, $prefix) === 0) {
                $sub = trim(substr($norm, strlen($prefix)));
                $sub = $sub === '' ? '(no subcommand)' : ltrim($sub);
                $list[] = $sub;
            }
        }
        $list = array_values(array_unique($list));
        return $list;
    }

    /**
     * Count the number of argument placeholders (<arg>) in the command description.
     */
    private function countPlaceholders(string $commandDesc): int
    {
        preg_match_all('/<[^>]+>/', $commandDesc, $matches);
        return count($matches[0]);
    }
	
	/**
 * Count the number of argument segments following a given category/action prefix.
 *
 * Example:
 *   countActionArgs("utils", "-u open") → returns [
 *      '-u open www' => 1,
 *      '-u open localhost' => 1,
 *      '-u open dashboard' => 1
 *   ]
 *
 * @param string $category  Command category (e.g. "utils", "apache")
 * @param string $actionKey Command prefix (e.g. "-u open", "mysql user")
 * @return array<string,int> Array of command keys => argument count
 */
public function countActionArgs(string $category, string $actionKey): array
{
    if (!isset($this->commandsConfig[$category])) {
        return [];
    }

    $results = [];
    foreach ($this->commandsConfig[$category] as $cmdKey => $description) {

        // Skip commands not matching the given prefix
        if (strpos($cmdKey, $actionKey) !== 0) {
            continue;
        }

        // Split key into parts
        $parts = explode(' ', trim($cmdKey));
        $baseParts = explode(' ', trim($actionKey));

        // Count what comes after the prefix
        $remaining = array_slice($parts, count($baseParts));

        // Each "<arg>" counts as one argument
        $argCount = 0;
        foreach ($remaining as $segment) {
            if (preg_match('/^<[^>]+>$/', $segment)) {
                $argCount++;
            } else {
                $argCount++;
            }
        }

        $results[$cmdKey] = $argCount;
    }

    return $results;
}
	/**
	 * Count provided arguments excluding command parts
	 */
	private function countProvidedArgs(string $baseCommand, string $foundKey, array $args): int
	{
		$baseParts = explode(' ', $baseCommand);
		$foundParts = explode(' ', $foundKey);
		
		// The additional arguments are what remains after the found command
		$extraParts = count($foundParts) - count($baseParts);
		
		return max(0, count($args) - $extraParts);
	}
	/**
	 * Get expected arguments count for MySQL commands
	 */
	private function getMySQLExpectedArgs(string $action, array $args): int
	{
		switch ($action) {
			case 'data':
				return 1; // list
			
			case 'drop':
				return 1; // <db>
			case 'export':
				return 2; // <db> [output_file]
			case 'import':
				return 2; // <db> <file.sql>
			case 'create':
				if (isset($args[0]) && $args[0] === 'db') {
					return 2; // create db <db_name>
				}
				return 1; // create <something>
			default:
				return 0;
		}
	}
	
	 
	
	
}
