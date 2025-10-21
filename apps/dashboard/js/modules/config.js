/**
 * -------------------------------------------------------------
 *  config.js - Configuration and DOM Elements for LocNetServe
 * -------------------------------------------------------------
 *  This module contains all configuration constants and DOM
 *  element references for the LocNetServe dashboard.
 *  It provides:
 *    - API endpoint URLs
 *    - Configuration constants
 *    - DOM element references for the entire application
 *
 *  Author : Sassi Souid
 *  Email  : locnetserve@gmail.com
 *  Project: LocNetServe
 *  Version: 1.0.0
 * -------------------------------------------------------------
 */

// Configuration constants
export const CONFIG = {
    langUrl: 'scripts/lang.php',
    statsUrl: 'scripts/get_stats.php',
    commandUrl: 'scripts/command.php',
    vhostsUrl: 'scripts/vhosts.php', 
    refreshInterval: 5000
};

// DOM Elements
export const elements = {
    statsGrid: document.getElementById('stats-grid'),
    servicesGrid: document.getElementById('services-grid'),
    projectsGrid: document.getElementById('projects-grid'),
    vhostsGrid: document.getElementById('vhosts-grid'),
    refreshBtn: document.getElementById('refresh-btn'),
    addVhostBtn: document.getElementById('add-vhost-btn'),
    vhostModal: document.getElementById('vhost-modal'),
    vhostForm: document.getElementById('vhost-form'),
    vhostName: document.getElementById('vhost-name'),
    vhostPath: document.getElementById('vhost-path'),
    browseBtn: document.getElementById('browse-btn'),
    cancelBtn: document.getElementById('cancel-btn'),
    closeModal: document.querySelector('.close')
};