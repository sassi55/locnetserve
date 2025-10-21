<?php
/**
 * -------------------------------------------------------------
 *  MySQLAutoStarter.php - Python-based MySQL Startup Helper
 * -------------------------------------------------------------
 *  Automates MySQL service startup by:
 *    - Executing Python script modules/mysql.py for service control
 *    - Providing fallback startup when connection tests fail
 *    - Integrating with backup and utility operations
 *
 *  Author : Sassi Souid
 *  Email  : locnetserve@gmail.com
 *  Project: LocNetServe
 *  Version: 1.0.0
 * -------------------------------------------------------------
 */

namespace Core\Utils;

class MySQLAutoStarter
{
    private string $pythonPath;
    private string $modulePath;

    public function __construct()
    {
        $this->pythonPath = 'python'; 
        $this->modulePath = BASE_DIR . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'mysql.py';
    }

   
    public function startMySQL(): string
    {
        if (!file_exists($this->modulePath)) {
            return "Error: Python module not found at {$this->modulePath}";
        }

        $cmd = "{$this->pythonPath} \"{$this->modulePath}\" start_mysql";
        $output = shell_exec($cmd . " 2>&1");

        return trim($output) ?: "No output from Python script.";
    }
}
