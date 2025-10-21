<?php
/**
 * -------------------------------------------------------------
 *  FolderZipper.php - ZIP Folder Utility for LocNetServe
 * -------------------------------------------------------------
 *  Creates ZIP archives from directories by:
 *    - Using PHP's native ZipArchive class for compression
 *    - Supporting recursive directory traversal and file inclusion
 *    - Handling file overwriting and archive validation
 *    - Providing reliable backup and file packaging capabilities
 *
 *  Author : Sassi Souid
 *  Email  : locnetserve@gmail.com
 *  Project: LocNetServe
 *  Version: 1.0.0
 * -------------------------------------------------------------
 */

namespace Core\Utils;

use ZipArchive;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

class FolderZipper
{
   /**
	* Creates a ZIP file from a given folder.
	*
	* @param string $sourceDir Path to the source folder to compress
	* @param string $zipFile Full path to the output ZIP file
	* @return bool True if successful, False otherwise
	*/
    public static function createZip(string $sourceDir, string $zipFile): bool
    {
        if (!is_dir($sourceDir)) {
            echo "❌ Error: Source directory not found: {$sourceDir}\n";
            //return false;
        }

        
        if (file_exists($zipFile)) {
            unlink($zipFile);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            echo "❌ Error: Cannot create ZIP file at {$zipFile}\n";
           // return false;
        }

        
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if (!$file->isFile()) continue;

            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($sourceDir) + 1);
            $zip->addFile($filePath, $relativePath);
        }

        $zip->close();

        if (file_exists($zipFile)) {
            echo "✅ ZIP created successfully: {$zipFile}\n";
            //return true;
        } else {
            echo "❌ ZIP creation failed.\n";
            //return false;
        }
    }
}
