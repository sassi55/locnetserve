/**
 * -------------------------------------------------------------
 *  translations.js - Translations Management for LocNetServe
 * -------------------------------------------------------------
 *  This module handles all translation operations for the
 *  LocNetServe dashboard. It provides:
 *    - Loading language configuration and translations
 *    - Updating UI elements with translated content
 *    - Language change detection and handling
 *
 *  Messages are translated according to the language defined in
 *  config/config.json and corresponding locale file in config/locales.
 *
 *  Author : Sassi Souid
 *  Email  : locnetserve@gmail.com
 *  Project: LocNetServe
 *  Version: 1.0.0
 * -------------------------------------------------------------
 */

import { CONFIG, elements } from './config.js';
import { loadVhosts } from './vhosts.js';

// Global variables for translations
export let currentTranslations = {};
export let currentLanguage = 'fr';
let vhostsLoaded = false;




/**
 * Load language configuration and translations
 */
export async function loadLanguageAndTranslations() {
    try {
        // Get configured language
        const langResponse = await fetch(`${CONFIG.langUrl}?action=get_config_language&t=${new Date().getTime()}`);
        if (langResponse.ok) {
            const langData = await langResponse.json();
            if (langData.success) {
                currentLanguage = langData.language;
                
                // Load translations for this language
                const transResponse = await fetch(`${CONFIG.langUrl}?action=get_translations&lang=${currentLanguage}&t=${new Date().getTime()}`);
                if (transResponse.ok) {
                    const transData = await transResponse.json();
                    if (transData.success) {
                        currentTranslations = transData.translations;
                        
                        // Update interface with translations
                        updateUIWithTranslations();
                    }
                }
            }
        }
    } catch (error) {
        console.error('Error loading translations:', error);
    }
}

/**
 * Update UI elements with translations
 */

export function updateUIWithTranslations() {
    if (!currentTranslations.ui) return;

    /**
     * Fonction récursive : cherche la clé dans currentTranslations
     */
function getTranslation(key) {
    const keys = key.includes('.') ? key.split('.') : ["ui", key];
    let value = currentTranslations;
    for (const k of keys) {
        if (value && value[k] !== undefined) {
            value = value[k];
        } else {
            return null;
        }
    }
    return value;
}


    // Mettre à jour automatiquement tous les éléments avec data-translate
    const elements = document.querySelectorAll('[data-translate]');
elements.forEach(el => {
    const key = el.getAttribute('data-translate');
    const translatedText = getTranslation(key);

    if (translatedText) {
        if (el.placeholder !== undefined && el.tagName === "INPUT") {
            el.placeholder = translatedText;
        } else if (el.children.length > 0) {
            // remplace seulement le texte, garde les icônes intactes
            el.childNodes.forEach(node => {
                if (node.nodeType === Node.TEXT_NODE) {
                    node.nodeValue = translatedText;
                }
            });
        } else {
            el.textContent = translatedText;
        }
    }
});


    // Mettre à jour aussi le titre de la page
    if (currentTranslations.ui.dashboard_title) {
        document.title = currentTranslations.ui.dashboard_title;
    }
}


/**
 * Check current language and reload page if changed
 * @returns {Promise<string>} Current language code
 */
export async function loadLanguage() {
    try {
        // Build URL with timestamp to avoid caching
        const url = `${CONFIG.langUrl}?action=get_config_language&t=${new Date().getTime()}`;
        
        // Send GET request
        const response = await fetch(url);
        
        if (response.ok) {
            const data = await response.json();
            
            if (data.success) {
                if (currentLanguage !== data.language) {
                    console.log(`Language changed from ${currentLanguage} to ${data.language}, reloading page...`);
                    // Reload page after short delay
                    setTimeout(() => {
                        window.location.reload();
                    }, 500);
                }
                return data.language;
            } else {
                console.error('Response error:', data.message);
                return 'fr'; // Default value in case of error
            }
        } else {
            throw new Error('Server response error');
        }
    } catch (error) {
        console.error('Error checking language:', error);
        return 'fr'; // Default value in case of error
    }
}