<?php
/**
 * Remove BOM (Byte Order Mark) from string
 *
 * Supports UTF-8, UTF-16 (BE/LE), and UTF-32 (BE/LE).
 *
 * @param string $string
 * @return string
 */
function remove_bom(string $string): string
{
    // UTF-8 BOM
    $string = preg_replace('/^\xEF\xBB\xBF/', '', $string);

    // UTF-16 BOM (BE and LE)
    $string = preg_replace('/^\xFE\xFF|^\xFF\xFE/', '', $string);

    // UTF-32 BOM (BE and LE)
    $string = preg_replace('/^\x00\x00\xFE\xFF|^\xFF\xFE\x00\x00/', '', $string);

    return $string;
}