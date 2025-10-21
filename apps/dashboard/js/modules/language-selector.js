/**
 * ============================================================
 * File Name      : language-selector.js
 * Description    : Handles language selection and switching
 *                  for the LocNetServe user interface.
 * Author         : Sassi Souid
 * Email         : locnetserve@gmail.com
 * Version        : 1.0
 * Created On     : 2025-10-18
 * ============================================================
 * Main Features:
 *  - Initializes the language selector on page load.
 *  - Detects and highlights the currently active language.
 *  - Updates and saves the language preference via backend.
 *  - Reloads the page to apply the selected language instantly.
 * ============================================================
 */

import { currentLanguage } from './translations.js';
import { CONFIG } from './config.js';

/**
 * Initialize the language selector dropdown
 */
export function initLanguageSelector() {
    const select = document.getElementById('language-select');
    if (!select) return;

    // Wait until translations.js defines the currentLanguage variable
    setTimeout(() => {
        const activeLang = window.currentLanguage || currentLanguage || 'en';
        const options = Array.from(select.options);

        // Find and activate the current language option
        const activeOption = options.find(opt => opt.value === activeLang);
        if (activeOption) {
            activeOption.selected = true;
            // Move the active language option to the top of the list
            select.insertBefore(activeOption, select.firstChild);
        }
    }, 500);

    // Listen for user language changes
    select.addEventListener('change', async (e) => {
        const newLang = e.target.value;
        try {
            // Send request to backend to update language in config.json
            const response = await fetch(`${CONFIG.langUrl}?action=set_config_language&lang=${newLang}`, {
                method: 'POST'
            });

            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    console.log(`Language successfully changed to: ${newLang}. Reloading...`);
                    setTimeout(() => window.location.reload(), 500);
                } else {
                    console.error(`Server error: ${data.message}`);
                }
            } else {
                console.error('HTTP error while attempting to change language.');
            }
        } catch (err) {
            console.error('JavaScript internal error while changing language:', err);
        }
    });
}
