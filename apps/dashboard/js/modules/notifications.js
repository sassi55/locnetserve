/**
 * -------------------------------------------------------------
 *  notifications.js - Notifications System for LocNetServe
 * -------------------------------------------------------------
 *  This module handles all notification operations for the
 *  LocNetServe dashboard. It provides:
 *    - Displaying success, error, warning and info notifications
 *    - Automatic notification cleanup and timeout management
 *    - Error message display with retry functionality
 *
 *  Author : Sassi Souid
 *  Email  : locnetserve@gmail.com
 *  Project: LocNetServe
 *  Version: 1.0.0
 * -------------------------------------------------------------
 */

import { elements } from './config.js';
import { loadStats } from './stats.js';

/**
 * Show a notification message
 * @param {string} message - Notification message to display
 * @param {string} type - Notification type ('success', 'error', 'warning', 'info')
 */
export function showNotification(message, type) {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.notification');
    existingNotifications.forEach(notification => notification.remove());
    
    // Create new notification
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    
    // Add styles for different notification types
    switch (type) {
        case 'success':
            notification.style.backgroundColor = '#10b981';
            notification.style.color = 'white';
            break;
        case 'error':
            notification.style.backgroundColor = '#ef4444';
            notification.style.color = 'white';
            break;
        case 'warning':
            notification.style.backgroundColor = '#f59e0b';
            notification.style.color = 'black';
            break;
        case 'info':
            notification.style.backgroundColor = '#3b82f6';
            notification.style.color = 'white';
            break;
        default:
            notification.style.backgroundColor = 'black';
            notification.style.color = 'white';
    }
    
    // Apply common styles
    notification.style.position = 'fixed';
    notification.style.top = '20px';
    notification.style.right = '20px';
    notification.style.padding = '12px 20px';
    notification.style.borderRadius = '6px';
    notification.style.zIndex = '10000';
    notification.style.fontWeight = '500';
    notification.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.15)';
    notification.style.maxWidth = '350px';
    notification.style.wordWrap = 'break-word';
    
    document.body.appendChild(notification);
    
    // Auto-remove after 3 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 3000);
}

/**
 * Show error message with retry button
 * @param {string} message - Error message to display
 */
export function showErrorMessage(message) {
    if (!elements.statsGrid) return;
    
    elements.statsGrid.innerHTML = `
        <div class="stat-card" style="grid-column: 1 / -1; text-align: center;">
            <div style="color: var(--danger); font-size: 48px; margin-bottom: 20px;">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div style="font-size: 18px; margin-bottom: 10px;">${message}</div>
            <div style="color: var(--secondary); font-size: 14px;">
                Statistics server is not available
            </div>
            <button class="refresh-btn" onclick="loadStats()" style="margin-top: 20px;">
                <i class="fas fa-sync-alt"></i> Try Again
            </button>
        </div>
    `;
}

/**
 * Show loading notification
 * @param {string} message - Loading message to display
 * @returns {Function} Function to hide the loading notification
 */
export function showLoadingNotification(message = 'Loading...') {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.notification');
    existingNotifications.forEach(notification => notification.remove());
    
    // Create loading notification
    const notification = document.createElement('div');
    notification.className = 'notification notification-info';
    notification.innerHTML = `
        <div style="display: flex; align-items: center;">
            <div class="spinner" style="margin-right: 10px;"></div>
            <span>${message}</span>
        </div>
    `;
    
    // Add spinner styles
    const style = document.createElement('style');
    style.textContent = `
        .spinner {
            border: 2px solid #f3f3f3;
            border-top: 2px solid #3b82f6;
            border-radius: 50%;
            width: 16px;
            height: 16px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    `;
    document.head.appendChild(style);
    
    // Apply styles
    notification.style.backgroundColor = '#3b82f6';
    notification.style.color = 'white';
    notification.style.position = 'fixed';
    notification.style.top = '20px';
    notification.style.right = '20px';
    notification.style.padding = '12px 20px';
    notification.style.borderRadius = '6px';
    notification.style.zIndex = '10000';
    notification.style.fontWeight = '500';
    notification.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.15)';
    
    document.body.appendChild(notification);
    
    // Return function to hide the notification
    return function hideLoading() {
        if (notification.parentNode) {
            notification.remove();
        }
        if (style.parentNode) {
            style.remove();
        }
    };
}

/**
 * Show confirmation dialog
 * @param {string} message - Confirmation message
 * @param {Function} onConfirm - Callback when confirmed
 * @param {Function} onCancel - Callback when cancelled
 */
export function showConfirmation(message, onConfirm, onCancel = () => {}) {
    // Create confirmation overlay
    const overlay = document.createElement('div');
    overlay.style.position = 'fixed';
    overlay.style.top = '0';
    overlay.style.left = '0';
    overlay.style.width = '100%';
    overlay.style.height = '100%';
    overlay.style.backgroundColor = 'rgba(0, 0, 0, 0.5)';
    overlay.style.display = 'flex';
    overlay.style.justifyContent = 'center';
    overlay.style.alignItems = 'center';
    overlay.style.zIndex = '10000';
    
    // Create confirmation dialog
    const dialog = document.createElement('div');
    dialog.style.backgroundColor = 'white';
    dialog.style.padding = '20px';
    dialog.style.borderRadius = '8px';
    dialog.style.boxShadow = '0 4px 20px rgba(0, 0, 0, 0.15)';
    dialog.style.maxWidth = '400px';
    dialog.style.width = '90%';
    
    dialog.innerHTML = `
        <div style="margin-bottom: 20px;">
            <p style="margin: 0; line-height: 1.5;">${message}</p>
        </div>
        <div style="display: flex; justify-content: flex-end; gap: 10px;">
            <button class="btn btn-secondary" style="padding: 8px 16px;">Cancel</button>
            <button class="btn btn-danger" style="padding: 8px 16px;">Confirm</button>
        </div>
    `;
    
    const cancelBtn = dialog.querySelector('.btn-secondary');
    const confirmBtn = dialog.querySelector('.btn-danger');
    
    cancelBtn.addEventListener('click', () => {
        onCancel();
        overlay.remove();
    });
    
    confirmBtn.addEventListener('click', () => {
        onConfirm();
        overlay.remove();
    });
    
    overlay.appendChild(dialog);
    document.body.appendChild(overlay);
    
    // Return function to remove the dialog
    return function removeDialog() {
        if (overlay.parentNode) {
            overlay.remove();
        }
    };
}