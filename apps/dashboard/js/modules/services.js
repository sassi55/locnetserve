/**
 * -------------------------------------------------------------
 *  services.js - Services Management for LocNetServe
 * -------------------------------------------------------------
 *  This module handles all service operations for the
 *  LocNetServe dashboard. It provides:
 *    - Controlling Apache and MySQL services (start, stop, restart)
 *    - Checking service status and configuration
 *    - Fallback mechanisms for service control
 *
 *  Author : Sassi Souid
 *  Email  : locnetserve@gmail.com
 *  Project: LocNetServe
 *  Version: 1.0.0
 * -------------------------------------------------------------
 */

import { CONFIG } from './config.js';
import { showNotification } from './notifications.js';
import { loadStats } from './stats.js';

/**
 * Control a service (Apache, MySQL)
 * @param {string} service - Service name ('apache' or 'mysql')
 * @param {string} action - Action to perform ('start', 'stop', 'restart')
 */
export async function controlService(service, action) {
    try {
        // Check Apache configuration before restart
        if (service.toLowerCase() === 'apache' && action === 'restart') {
            const configCheck = await checkApacheConfig();
            if (!configCheck.valid) {
                showNotification('Invalid Apache configuration: ' + configCheck.output, 'error');
                return;
            }
        }
        
        // Build URL with GET parameters
        const url = `${CONFIG.commandUrl}?service=${service}&action=${action}&t=${new Date().getTime()}`;
        
        // Send GET request
        const response = await fetch(url);
        
        if (response.ok) {
            const result = await response.json();
            
            if (result.success) {
                showNotification(`${service} ${action} successful`, 'success');
            } else {
                showNotification(`Error: ${result.message || 'Action not performed'}`, 'error');
            }
        } else {
            throw new Error('Server response error');
        }
        
        // Reload stats after a delay
        setTimeout(loadStats, 2000);
        
    } catch (error) {
        console.log('Fallback mode activated:', error);
        controlServiceFallback(service, action);
    }
}

/**
 * Check Apache configuration validity
 * @returns {Promise<Object>} Configuration check result
 */
export async function checkApacheConfig() {
    try {
        const response = await fetch('check_apache_config.php');
        return await response.json();
    } catch (error) {
        console.error('Apache configuration check error:', error);
        return { valid: false, output: 'Check error' };
    }
}

/**
 * Check service status
 * @param {string} service - Service name ('apache' or 'mysql')
 * @returns {Promise<boolean>} True if service is running
 */
export async function checkServiceStatus(service) {
    try {
        const response = await fetch(`${CONFIG.statsUrl}?t=${new Date().getTime()}`);
        if (response.ok) {
            const data = await response.json();
            return data[service]?.status === 'running';
        }
        return false;
    } catch (error) {
        console.error('Service status check error:', error);
        return false;
    }
}

/**
 * Fallback method for service control
 * @param {string} service - Service name
 * @param {string} action - Action to perform
 */
export async function controlServiceFallback(service, action) {
    try {
        const commandData = { 
            service: service, 
            action: action, 
            timestamp: new Date().toISOString() 
        };
        
        localStorage.setItem('ahkCommand', JSON.stringify(commandData));
        
        showNotification(`Command ${action} sent to ${service} (fallback mode)`, 'warning');
        setTimeout(loadStats, 2000);
        
    } catch (fallbackError) {
        console.error('Fallback mode error:', fallbackError);
        showNotification('Critical communication error', 'error');
    }
}