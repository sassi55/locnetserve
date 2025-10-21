<?php
/**
 * -------------------------------------------------------------
 *  ValidationErrorHandler.php - Centralized Error Formatter for LocNetServe
 * -------------------------------------------------------------
 *  Provides consistent validation error messaging by:
 *    - Generating colorized error messages for command validation
 *    - Handling category, command, and argument validation errors
 *    - Supporting missing subcommand detection and suggestions
 *    - Standardizing error output format across validators
 *
 *  Author : Sassi Souid
 *  Email  : locnetserve@gmail.com
 *  Project: LocNetServe
 *  Version: 1.0.0
 * -------------------------------------------------------------
 */

namespace Core\Utils;

class ValidationErrorHandler
{
    private array $colors;

    public function __construct(array $colors = [])
    {
        $this->colors = $colors;
    }

    /**
     * Return a structured validation result
     */
    private function result(bool $ok, string $msg = '', int $expectedArgs = 0): array
    {
        return [$ok, $msg, $expectedArgs];
    }

    // -------------------------------------------------------------
    // CATEGORY / COMMAND ERRORS
    // -------------------------------------------------------------
    public function unknownCategory(string $category): array
    {
        return $this->result(false, $this->colors['RED'] . "Unknown category: $category" . $this->colors['RESET']);
    }

    public function unknownCommand(string $command): array
    {
        return $this->result(false, $this->colors['RED'] . "Unknown command: $command" . $this->colors['RESET']);
    }

    public function unknownUtilsCommand(string $command): array
    {
        return $this->result(false, $this->colors['RED'] . "Unknown utils command: $command" . $this->colors['RESET']);
    }

    // -------------------------------------------------------------
    // ARGUMENT VALIDATION ERRORS
    // -------------------------------------------------------------
    public function tooManyArgs(string $command, string $arg): array
    {
        return $this->result(
            false,
            $this->colors['RED'] . "'$command' does not accept argument: \"$arg\"" . $this->colors['RESET']
        );
    }

    public function missingArgs(string $command, int $expectedArgs): array
    {
        return $this->result(
            false,
            $this->colors['YELLOW'] . "'$command' expects $expectedArgs argument(s)." . $this->colors['RESET'],
            $expectedArgs
        );
    }

    /**
     * Provide a friendly message when a command requires a subcommand (e.g., 'utils backup').
     *
     * @param string $command     The base command (e.g., "utils backup")
     * @param array  $options     Array of available subcommands (strings)
     * @return array
     */
    public function missingSubcommand(string $command, array $options): array
    {
        $opts = !empty($options) ? implode(', ', $options) : '(no subcommands)';
        $msg  = $this->colors['YELLOW'] . "'$command' requires a subcommand. Available: $opts" . $this->colors['RESET'];
        return $this->result(false, $msg, 0);
    }

    // -------------------------------------------------------------
    // SUCCESS RESULT
    // -------------------------------------------------------------
    public function ok(int $expectedArgs = 0): array
    {
        return $this->result(true, '', $expectedArgs);
    }
}
