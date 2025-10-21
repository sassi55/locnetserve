<?php
/**
 * -------------------------------------------------------------
 *  translator.php - Translation system for LocNetServe
 * -------------------------------------------------------------
 *  Provides multilingual support by:
 *    - Reading current language from config.json
 *    - Loading the appropriate locale file
 *    - Offering a __t($key, $params=[]) function for translation
 *
 *  Author : Sassi Souid
 *  Email  : locnetserve@gmail.com
 *  Project: LocNetServe
 *  Version: 1.0.0
 * -------------------------------------------------------------
 */

class Translator {
    private static $translations = [];
    private static $language = 'en';

    /**
     * Initialize the translator by reading config.json
     */
    public static function init($rootPath) {
        $configPath  = $rootPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.json';
        $localesPath = $rootPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'locales' . DIRECTORY_SEPARATOR;

        // Load config.json
        if (file_exists($configPath)) {
            $configContent = file_get_contents($configPath);
            $config = json_decode($configContent, true);

            if (isset($config['settings']['language'])) {
                self::$language = $config['settings']['language'];
            }
        }

        // Load translations
        $localeFile = $localesPath . self::$language . '.json';
        if (!file_exists($localeFile)) {
            // fallback to English
            $localeFile = $localesPath . 'en.json';
            self::$language = 'en';
        }

        if (file_exists($localeFile)) {
            $content = file_get_contents($localeFile);
            self::$translations = json_decode($content, true);
        }
    }

    /**
     * Translate a key using dot notation (ex: "messages.notifications.error")
     */
    public static function t($key, $params = []) {
        $keys = explode('.', $key);
        $value = self::$translations;

        foreach ($keys as $k) {
            if (isset($value[$k])) {
                $value = $value[$k];
            } else {
                return $key; // return key if not found
            }
        }

        // Replace placeholders like {service}, {path}, {error}, etc.
        if (is_string($value) && !empty($params)) {
            foreach ($params as $pKey => $pValue) {
                $value = str_replace('{' . $pKey . '}', $pValue, $value);
            }
        }

        return $value;
    }

    /**
     * Get current language code
     */
    public static function getLanguage() {
        return self::$language;
    }
}
