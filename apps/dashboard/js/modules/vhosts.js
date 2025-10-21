/**
 * -------------------------------------------------------------
 *  vhosts.js - Virtual Hosts Management for LocNetServe
 * -------------------------------------------------------------
 *  This module handles all virtual hosts operations for the
 *  LocNetServe dashboard. It provides:
 *    - Listing and displaying virtual hosts
 *    - Creating new virtual hosts
 *    - Deleting existing virtual hosts
 *    - Managing the virtual hosts modal interface
 *
 *  Author : Sassi Souid
 *  Email  : locnetserve@gmail.com
 *  Project: LocNetServe
 *  Version: 1.0.0
 * -------------------------------------------------------------
 */

import { CONFIG, elements } from './config.js';
import { showNotification } from './notifications.js';
import { currentTranslations } from './translations.js';

// Global variables for vhosts
let vhostsLoaded = false;
let isLoadingVhosts = false;

/**
 * Initialize virtual hosts functionality
 */
export function initVhosts() {
    // Set up event listeners for virtual hosts
    if (elements.addVhostBtn) {
        elements.addVhostBtn.addEventListener('click', showVhostModal);
    }
    
    if (elements.browseBtn) {
        elements.browseBtn.addEventListener('click', browseFolder);
    }
    
    if (elements.vhostForm) {
        elements.vhostForm.addEventListener('submit', createVhost);
    }
    
    if (elements.cancelBtn) {
        elements.cancelBtn.addEventListener('click', hideVhostModal);
    }
    
    if (elements.closeModal) {
        elements.closeModal.addEventListener('click', hideVhostModal);
    }
    
    // Close modal when clicking outside
    if (elements.vhostModal) {
        elements.vhostModal.addEventListener('click', function(e) {
            if (e.target === elements.vhostModal) {
                hideVhostModal();
            }
        });
    }
    
    // Load virtual hosts
    loadVhosts();
}

/**
 * Show the virtual host creation modal
 */
export function showVhostModal() {
    if (elements.vhostModal) {
        elements.vhostModal.style.display = 'block';
    }
}

/**
 * Hide the virtual host creation modal
 */
export function hideVhostModal() {
    if (elements.vhostModal) {
        elements.vhostModal.style.display = 'none';
        if (elements.vhostForm) {
            elements.vhostForm.reset();
        }
    }
}

/**
 * Browse for project folder (placeholder implementation)
 */
export function browseFolder() {
    // This would require integration with the backend
    // Currently using a simple approach
    const path = prompt('Enter the project path (ex: C:\\MyServer\\www\\myproject):');
    if (path) {
        if (elements.vhostPath) {
            elements.vhostPath.value = path;
        }
    }
}

/**
 * Load virtual hosts from server
 */
export async function loadVhosts() {
    // Prevent multiple simultaneous calls
    if (isLoadingVhosts) return;
    isLoadingVhosts = true;
    
    try {
        // CORRECT CALL: add ?action=list
        const response = await fetch(`${CONFIG.vhostsUrl}?action=list`);
        
        // Check if response is valid
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        
        if (result.success && elements.vhostsGrid) {
            updateVhosts(result.vhosts);
        } else {
            console.error('Error loading virtual hosts:', result.message);
            showNotification('Error loading virtual hosts', 'error');
        }
    } catch (error) {
        console.error('Connection error:', error);
        showNotification('Unable to load virtual hosts', 'error');
        
        if (elements.vhostsGrid) {
            elements.vhostsGrid.innerHTML = `
                <div class="vhost-card">
                    <div class="vhost-header">
                        <div class="vhost-title">Connection Error</div>
                        <i class="fas fa-exclamation-triangle" style="color: #ef4444;"></i>
                    </div>
                    <p>Unable to load virtual hosts.</p>
                </div>
            `;
        }
    } finally {
        isLoadingVhosts = false;
    }
}

/**
 * Create a new virtual host
 * @param {Event} e - Form submit event
 */
export async function createVhost(e) {
    e.preventDefault();
    
    if (!elements.vhostName || !elements.vhostPath) {
        return;
    }
    
    const name = elements.vhostName.value;
    const path = elements.vhostPath.value;
    
    if (!name || !path) {
        showNotification('Please fill all fields', 'error');
        return;
    }
    
    try {
        // CORRECT CALL: add ?action=create&name=...&path=...
        const url = `${CONFIG.vhostsUrl}?action=create&name=${encodeURIComponent(name)}&path=${encodeURIComponent(path)}`;
        console.log("Called URL:", url);
        
        const response = await fetch(url);
        const result = await response.json();
        
        if (result.success) {
            showNotification(result.message, 'success');
            hideVhostModal();
            loadVhosts(); // Reload the list
        } else {
            showNotification(result.message, 'error');
        }
    } catch (error) {
        console.error('Creation error:', error);
        showNotification('Communication error with server', 'error');
    }
}

/**
 * Update the virtual hosts display
 * @param {Array} vhosts - Array of virtual host objects
 */
export function updateVhosts(vhosts) {
    // Enhanced DOM element checking
    if (!elements.vhostsGrid || !elements.vhostsGrid.innerHTML) {
        console.error('vhostsGrid element not found');
        return;
    }
    
    const t = currentTranslations.ui || {};
    
    if (!vhosts || vhosts.length === 0) {
        elements.vhostsGrid.innerHTML = `
            <div class="vhost-card">
                <div class="vhost-header">
                    <div class="vhost-title">${t.no_vhosts || 'No virtual hosts'}</div>
                    <i class="fas fa-globe" style="color: #64748b;"></i>
                </div>
                <p>${t.no_vhosts_message || 'No virtual hosts configured. Add one to get started.'}</p>
            </div>
        `;
        return;
    }
    
    let vhostsHTML = '';
    
    vhosts.forEach(vhost => {
        vhostsHTML += `
            <div class="vhost-card">
                <div class="vhost-header">
                    <div class="vhost-title">${vhost.name}</div>
                    <div class="vhost-actions">
                        <button class="btn-icon" onclick="openVhost('${vhost.name}')" title="${t.open_vhost || 'Open'}">
                            <i class="fas fa-external-link-alt"></i>
                        </button>
                        <button class="btn-icon btn-danger" onclick="deleteVhost('${vhost.name}')" title="${t.delete_vhost || 'Delete'}">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                <div class="vhost-details">
                    <div class="vhost-detail">
                        <i class="fas fa-folder"></i>
                        <span>${vhost.path}</span>
                    </div>
                    <div class="vhost-detail">
                        <i class="fas fa-cog"></i>
                        <span>${vhost.configFile}</span>
                    </div>
                </div>
            </div>
        `;
    });
    
    elements.vhostsGrid.innerHTML = vhostsHTML;
}

/**
 * Open a virtual host in a new tab
 * @param {string} name - Virtual host name
 */
export function openVhost(name) {
    window.open(`http://${name}`, '_blank');
}

/**
 * Delete a virtual host
 * @param {string} name - Virtual host name
 */
export async function deleteVhost(name) {
    if (!confirm(`Are you sure you want to delete the virtual host "${name}"?`)) {
        return;
    }
    
    try {
        // CORRECT CALL: add ?action=delete&name=...
        const response = await fetch(`${CONFIG.vhostsUrl}?action=delete&name=${encodeURIComponent(name)}`);
        const result = await response.json();
        
        if (result.success) {
            showNotification(result.message, 'success');
            loadVhosts(); // Reload the list
        } else {
            showNotification(result.message, 'error');
        }
    } catch (error) {
        console.error('Deletion error:', error);
        showNotification('Communication error with server', 'error');
    }
}

/**
 * Start the virtual host manager
 */
export async function startVHostManager() {
    try {
        const response = await fetch('scripts/start_vhost_manager.php');
        const result = await response.json();
        
        if (result.success) {
            showNotification('Virtual host manager started', 'success');
            // Reload virtual hosts after startup
            setTimeout(loadVhosts, 2000);
        } else {
            showNotification('Error: ' + result.message, 'error');
        }
    } catch (error) {
        console.error('Manager startup error:', error);
        showNotification('Communication error', 'error');
    }
}

/**
 * Check virtual host manager status
 */
export async function checkVHostManagerStatus() {
    // Implementation for checking vhost manager status
    console.log('Checking VHost manager status...');
    // Add actual implementation here
}