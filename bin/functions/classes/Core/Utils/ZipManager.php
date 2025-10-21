<?php
/**
 * -------------------------------------------------------------
 *  ZipManager.php - Universal ZIP Archive Creator for LocNetServe
 * -------------------------------------------------------------
 *  Handles directory compression and archive creation by:
 *    - Using system commands (PowerShell, 7-Zip) for optimal performance
 *    - Providing PHP ZipArchive as reliable fallback option
 *    - Supporting recursive directory compression with file detection
 *    - Ensuring cross-platform compatibility for backup operations
 *
 *  Author : Sassi Souid
 *  Email  : locnetserve@gmail.com
 *  Project: LocNetServe
 *  Version: 1.0.0
 * -------------------------------------------------------------
 */

namespace Core\Utils;

class ZipManager
{
    /**
     * Create ZIP using system command or PHP fallback
     */
    public static function createZipWithSystemCommand(string $sourceDir, string $zipFile): bool
    {
        $sourceDir = rtrim($sourceDir, DIRECTORY_SEPARATOR);

        // ✅ Universal file detection (any file type)
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \FilesystemIterator::SKIP_DOTS)
        );

        $hasFiles = false;
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $hasFiles = true;
                break;
            }
        }

        if (!$hasFiles) {
            return false;
        }

        // --- Method 3: PHP ZipArchive fallback ---
        if (class_exists('ZipArchive')) {
            $zip = new \ZipArchive();
            if ($zip->open($zipFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($sourceDir, \FilesystemIterator::SKIP_DOTS)
                );
                foreach ($iterator as $file) {
                    $filePath = $file->getRealPath();
                    $relativePath = substr($filePath, strlen($sourceDir) + 1);
                    $zip->addFile($filePath, $relativePath);
                }
                $zip->close();
                if (file_exists($zipFile) && filesize($zipFile) > 0) {
                    return true;
                }
            }
        }

        return false;
    }
}
