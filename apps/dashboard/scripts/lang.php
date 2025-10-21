<?php
/**
 * -------------------------------------------------------------
 *  lang.php - Language API for LocNetServe
 * -------------------------------------------------------------
 *  This script provides language management for the dashboard.
 *  It allows:
 *    - Reading the default language from config.json
 *    - Loading translations from /config/locales/*.json
 *    - Listing all available languages
 *
 *  Messages are translated according to the language defined in
 *  config/config.json and corresponding locale file in config/locales.
 *
 *  Author : Sassi Souid
 *  Email  : locnetserve@gmail.com
 *  Project: LocNetServe
 *  Version: 1.1
 * -------------------------------------------------------------
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// -------------------------------------------------------------
// Load Translator
// -------------------------------------------------------------
require_once __DIR__ . '\translator.php';

// Detect root path
$rootPath = dirname(dirname(dirname(__DIR__)));
Translator::init($rootPath);

// -------------------------------------------------------------
// Dynamic paths
// -------------------------------------------------------------
$configPath  = $rootPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.json';
$localesPath = $rootPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'locales' . DIRECTORY_SEPARATOR;

// -------------------------------------------------------------
// Functions
// -------------------------------------------------------------

// Read language from config.json
function getConfigLanguage($configPath) {
    if (!file_exists($configPath)) {
        return 'fr'; // default
    }

    $configContent = file_get_contents($configPath);
    $config = json_decode($configContent, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return 'fr';
    }

    return isset($config['settings']['language']) ? $config['settings']['language'] : 'fr';
}

// Load translations
function loadTranslations($language, $localesPath) {
    $filePath = $localesPath . $language . '.json';

    if (!file_exists($filePath)) {
        $filePath = $localesPath . 'fr.json'; // fallback
    }

    if (!file_exists($filePath)) {
        return ['error' => Translator::t('messages.notifications.not_found', ['item' => 'language file'])];
    }

    $content = file_get_contents($filePath);
    $translations = json_decode($content, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['error' => Translator::t('messages.notifications.error', ['message' => 'Invalid JSON in language file'])];
    }

    return $translations;
}

// Get available languages
function getAvailableLanguages($localesPath) {
    $languages = [];
    $files = glob($localesPath . '*.json');

    foreach ($files as $file) {
        $languageCode = pathinfo($file, PATHINFO_FILENAME);
        $content = file_get_contents($file);
        $data = json_decode($content, true);

        if (isset($data['language'])) {
            $languages[$languageCode] = [
                'name'   => $data['language']['name'],
                'code'   => $data['language']['code'],
                'author' => $data['language']['author']
            ];
        }
    }

    return $languages;
}

// -------------------------------------------------------------
// Main
// -------------------------------------------------------------
if (isset($_GET['action'])) {
    $action = $_GET['action'];

    switch ($action) {
        case 'get_config_language':
            $language = getConfigLanguage($configPath);
            echo json_encode([
                'success'  => true,
                'language' => $language
            ]);
            break;

        case 'get_translations':
            $language = isset($_GET['lang']) ? $_GET['lang'] : getConfigLanguage($configPath);
            $translations = loadTranslations($language, $localesPath);

            if (isset($translations['error'])) {
                echo json_encode([
                    'success' => false,
                    'message' => $translations['error']
                ]);
            } else {
                echo json_encode([
                    'success'      => true,
                    'language'     => $language,
                    'translations' => $translations
                ]);
            }
            break;

        case 'get_available_languages':
            $languages = getAvailableLanguages($localesPath);
            echo json_encode([
                'success'   => true,
                'languages' => $languages
            ]);
            break;
		case 'set_language':
			$lang = $_GET['lang'] ?? '';
			if (in_array($lang, ['fr', 'en', 'es'])) {
				// Mettre à jour le fichier config.json
				$config = json_decode(file_get_contents($configPath), true);
				$config['settings']['language'] = $lang;
				$config['settings']['t_lang'] ="".time()."";
				file_put_contents($configPath, json_encode($config, JSON_PRETTY_PRINT));
				
				echo json_encode([
					'success' => true,
					'message' => 'Language updated successfully'
				]);
			} else {
				echo json_encode([
					'success' => false,
					'message' => 'Invalid language code'
				]);
			}
			break;	

        default:
            echo json_encode([
                'success' => false,
                'message' => Translator::t('messages.notifications.error', ['message' => 'Unknown action'])
            ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => Translator::t('messages.notifications.error', ['message' => 'No action specified'])
    ]);
}
?>
