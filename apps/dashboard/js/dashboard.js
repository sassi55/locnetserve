/**
 * -------------------------------------------------------------
 *  dashboard.js - Main Application File for LocNetServe
 * -------------------------------------------------------------
 *  This is the main entry point for the LocNetServe dashboard.
 *  It initializes all modules and handles the application lifecycle.
 *  
 *  Modules used:
 *    - config.js: Configuration and DOM elements
 *    - vhosts.js: Virtual hosts management
 *    - stats.js: Statistics loading and display
 *    - translations.js: Language and translations management
 *    - notifications.js: Notification system
 *    - services.js: Service control (Apache, MySQL)
 *
 *  Author : Sassi Souid
 *  Email  : locnetserve@gmail.com
 *  Project: LocNetServe
 *  Version: 1.0.0
 * -------------------------------------------------------------
 */

// Import all modules
import { CONFIG, elements } from './modules/config.js';
import { initLanguageSelector } from './modules/language-selector.js';
import { 
    initVhosts, 
    showVhostModal, 
    hideVhostModal, 
    browseFolder, 
    createVhost, 
    loadVhosts, 
    updateVhosts, 
    openVhost, 
    deleteVhost, 
    startVHostManager, 
    checkVHostManagerStatus 
} from './modules/vhosts.js';

import { 
    loadStats, 
    loadDefaultStats, 
    updateDashboard, 
    updateSystemStats, 
    updateServices, 
    updateProjects 
} from './modules/stats.js';

import { 
    loadLanguageAndTranslations, 
    loadLanguage, 
    updateUIWithTranslations,
    currentTranslations,
    currentLanguage
} from './modules/translations.js';

import { 
    showNotification, 
    showErrorMessage,
    showLoadingNotification,
    showConfirmation
} from './modules/notifications.js';

import { 
    controlService, 
    checkServiceStatus, 
    controlServiceFallback, 
    checkApacheConfig 
} from './modules/services.js';

// Global variables
let vhostsLoaded = false;

/**
 * Initialize the application
 */
function initializeApp() {
    // Load language and translations
    loadLanguageAndTranslations();
    // Initialize language selector
    initLanguageSelector();
    // Load statistics on startup
    loadStats();
    
    // Configure refresh intervals
    setInterval(loadStats, CONFIG.refreshInterval);
    setInterval(loadLanguage, CONFIG.refreshInterval);
    
    // Configure refresh button
    if (elements.refreshBtn) {
        elements.refreshBtn.addEventListener('click', loadStats);
    }
    
    // Initialize virtual hosts
    initVhosts();
    
    // Start virtual host manager
    startVHostManager();
}

/**
 * Check virtual host manager status periodically
 */
setInterval(() => {
    checkVHostManagerStatus();
}, 30000);

// Initialize application when DOM is loaded
document.addEventListener('DOMContentLoaded', initializeApp);

// Make functions globally available for HTML onclick attributes
window.openVhost = openVhost;
window.deleteVhost = deleteVhost;
window.controlService = controlService;
window.browseFolder = browseFolder;
window.showVhostModal = showVhostModal;
window.hideVhostModal = hideVhostModal;

// Export for potential use in other modules
export {
    CONFIG,
    elements,
    loadStats,
    loadVhosts,
    showNotification,
    currentTranslations,
    currentLanguage
};