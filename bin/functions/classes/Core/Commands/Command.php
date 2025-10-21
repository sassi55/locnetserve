<?php
/**
 * -------------------------------------------------------------
 *  Command.php - Command Interface for LocNetServe
 * -------------------------------------------------------------
 *  Defines the standard interface for all command classes by:
 *    - Establishing consistent command execution pattern
 *    - Ensuring uniform help system across all commands
 *    - Providing contract for command implementation
 *
 *  Author : Sassi Souid
 *  Email  : locnetserve@gmail.com
 *  Project: LocNetServe
 *  Version: 1.0.0
 * -------------------------------------------------------------
 */

namespace Core\Commands;

interface Command
{
    /**
     * Execute the command with given action and arguments.
     *
     * @param string $action The action to perform
     * @param array $args Command arguments
     * @return string Result message
     */
    public function execute(string $action, array $args = []): string;

    /**
     * Get help information for the command.
     *
     * @return string Help message describing command usage
     */
    public function getHelp(): string;
}