/**
 * -------------------------------------------------------------
 *  stats.js - Statistics Management for LocNetServe
 * -------------------------------------------------------------
 *  This module handles all statistics operations for the
 *  LocNetServe dashboard. It provides:
 *    - Loading system and service statistics
 *    - Updating dashboard with statistics data
 *    - Fallback mechanisms for error handling
 *    - System, services and projects data display
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

/**
 * Main function to load statistics from server
 */
export async function loadStats() {
    try {
        const response = await fetch(`${CONFIG.statsUrl}?t=${new Date().getTime()}`);
        
        if (response.ok) {
            const data = await response.json();
            console.log('Received data:', data); // Debug: see data structure
            
            // Check if response contains default data due to error
            if (data.error) {
                console.error('Server error:', data.message);
                if (data.default_data) {
                    updateDashboard(data.default_data);
                } else {
                    loadDefaultStats();
                }
                showNotification('Using default data: ' + data.message, 'warning');
            } 
            // Check if there's a warning but valid data
            else if (data.warning) {
                console.warn('Warning:', data.warning_message);
                updateDashboard(data);
                showNotification('Warning: ' + data.warning_message, 'warning');
            }
            // Normal data
            else {
                updateDashboard(data);
            }
        } else {
            loadDefaultStats();
            showNotification('Server connection error', 'error');
        }
    } catch (error) {
        console.log('Connection error, using default data:', error);
        loadDefaultStats();
        showNotification('Connection error, using default data', 'error');
    }
}

/**
 * Fallback function with default data
 */
export function loadDefaultStats() {
    const defaultData = {
        system: {
            cpu_usage: "15.3",
            memory_usage: "45.8",
            disk_usage: "62.1",
            network_usage: "125.4"
        },
        apache: {
            status: "running",
            cpu: "2.5",
            memory: "120.5",
            uptime: "02:45:30",
            requests: "1245"
        },
        mysql: {
            status: "running",
            cpu: "1.8",
            memory: "85.2",
            uptime: "02:45:30",
            connections: "8"
        },
        projects: {
            projet1: {
                name: "projet1",
                size: "15.8",
                files: "42",
                last_modified: new Date().toISOString().replace('T', ' ').substring(0, 19)
            },
            projet2: {
                name: "projet2",
                size: "8.3",
                files: "23",
                last_modified: new Date(Date.now() - 86400000).toISOString().replace('T', ' ').substring(0, 19)
            }
        }
    };
    
    updateDashboard(defaultData);
}

/**
 * Update the entire dashboard with data
 * @param {Object} data - Statistics data object
 */
export function updateDashboard(data) {
    // Check if data has expected structure
    if (data && data.system) {
        updateSystemStats(data.system);
    } else {
        console.error('Invalid system data structure:', data);
        showNotification('Error: missing system data', 'error');
    }
    
    if (data && data.apache && data.mysql) {
        updateServices(data.apache, data.mysql);
    } else {
        console.error('Invalid services data structure:', data);
        showNotification('Error: missing services data', 'error');
    }
    
    if (data && data.projects) {
        updateProjects(data.projects);
    } else {
        console.error('Invalid projects data structure:', data);
        showNotification('Error: missing projects data', 'error');
    }
}

/**
 * Update system statistics display
 * @param {Object} system - System statistics object
 */
export function updateSystemStats(system) {
    // Check that system object exists and has required properties
    if (!system) {
        console.error('System data not defined');
        return;
    }
    
    const t = currentTranslations.ui || {};
    
    // Use default values if some properties are missing
    const cpuUsage = system.cpu_usage || "0.0";
    const memoryUsage = system.memory_usage || "0.0";
    const diskUsage = system.disk_usage || "0.0";
    const networkUsage = system.network_usage || "0.0";
    
    elements.statsGrid.innerHTML = `
        <div class="stat-card">
            <div class="stat-card-header">
                <div class="stat-card-title">${t.cpu_usage || 'CPU Usage'}</div>
                <div class="stat-card-icon bg-primary">
                    <i class="fas fa-microchip"></i>
                </div>
            </div>
            <div class="stat-card-value">${cpuUsage}%</div>
            <div class="stat-card-label">${t.system_processor || 'System Processor'}</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-card-header">
                <div class="stat-card-title">${t.memory_usage || 'Memory Usage'}</div>
                <div class="stat-card-icon bg-warning">
                    <i class="fas fa-memory"></i>
                </div>
            </div>
            <div class="stat-card-value">${memoryUsage}%</div>
            <div class="stat-card-label">${t.ram_memory || 'RAM Memory'}</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-card-header">
                <div class="stat-card-title">${t.disk_usage || 'Disk Usage'}</div>
                <div class="stat-card-icon bg-success">
                    <i class="fas fa-hard-drive"></i>
                </div>
            </div>
            <div class="stat-card-value">${diskUsage}%</div>
            <div class="stat-card-label">${t.disk_space || 'Disk Space'}</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-card-header">
                <div class="stat-card-title">${t.network_usage || 'Network'}</div>
                <div class="stat-card-icon bg-info">
                    <i class="fas fa-network-wired"></i>
                </div>
            </div>
            <div class="stat-card-value">${networkUsage} KB/s</div>
            <div class="stat-card-label">${t.network_activity || 'Network Activity'}</div>
        </div>
    `;
}

/**
 * Update services display
 * @param {Object} apache - Apache service statistics
 * @param {Object} mysql - MySQL service statistics
 */
export function updateServices(apache, mysql) {
    // Check that apache and mysql objects exist
    if (!apache || !mysql) {
        console.error('Services data not defined');
        return;
    }
    
    const t = currentTranslations.ui || {};
    const m = currentTranslations.menu || {};
    const s = currentTranslations.services || {};
    
    // Use default values if some properties are missing
    const apacheStatus = apache.status || "stopped";
    const apacheCpu = apache.cpu || "0.0";
    const apacheMemory = apache.memory || "0.0";
    const apacheUptime = apache.uptime || "00:00:00";
    const apacheRequests = apache.requests || "0";
    
    const mysqlStatus = mysql.status || "stopped";
    const mysqlCpu = mysql.cpu || "0.0";
    const mysqlMemory = mysql.memory || "0.0";
    const mysqlUptime = mysql.uptime || "00:00:00";
    const mysqlConnections = mysql.connections || "0";
    
    elements.servicesGrid.innerHTML = `
        <div class="service-card">
            <div class="service-header">
                <div class="service-title">${s.apache || 'Apache'}</div>
                <div class="service-status ${apacheStatus === 'running' ? 'status-running' : 'status-stopped'}">
                    ${apacheStatus === 'running' ? m.state?.running || 'Running' : m.state?.stopped || 'Stopped'}
                </div>
            </div>
            <div class="service-stats">
                <div class="service-stat">
                    <div class="service-stat-value">${apacheCpu}%</div>
                    <div class="service-stat-label">${t.service_cpu || 'CPU'}</div>
                </div>
                <div class="service-stat">
                    <div class="service-stat-value">${apacheMemory} MB</div>
                    <div class="service-stat-label">${t.service_memory || 'Memory'}</div>
                </div>
                <div class="service-stat">
                    <div class="service-stat-value">${apacheUptime}</div>
                    <div class="service-stat-label">${t.service_uptime || 'Uptime'}</div>
                </div>
                <div class="service-stat">
                    <div class="service-stat-value">${apacheRequests}</div>
                    <div class="service-stat-label">${t.service_requests || 'Requests'}</div>
                </div>
            </div>
            <div class="service-actions">
                <button class="btn btn-danger" onclick="controlService('apache', 'stop')">
                    <i class="fas fa-stop"></i> ${m.main?.stop || 'Stop'}
                </button>
                <button class="btn btn-warning" onclick="controlService('apache', 'restart')">
                    <i class="fas fa-redo"></i> ${m.main?.restart || 'Restart'}
                </button>
            </div>
        </div>
        
        <div class="service-card">
            <div class="service-header">
                <div class="service-title">${s.mysql || 'MySQL'}</div>
                <div class="service-status ${mysqlStatus === 'running' ? 'status-running' : 'status-stopped'}">
                    ${mysqlStatus === 'running' ? m.state?.running || 'Running' : m.state?.stopped || 'Stopped'}
                </div>
            </div>
            <div class="service-stats">
                <div class="service-stat">
                    <div class="service-stat-value">${mysqlCpu}%</div>
                    <div class="service-stat-label">${t.service_cpu || 'CPU'}</div>
                </div>
                <div class="service-stat">
                    <div class="service-stat-value">${mysqlMemory} MB</div>
                    <div class="service-stat-label">${t.service_memory || 'Memory'}</div>
                </div>
                <div class="service-stat">
                    <div class="service-stat-value">${mysqlUptime}</div>
                    <div class="service-stat-label">${t.service_uptime || 'Uptime'}</div>
                </div>
                <div class="service-stat">
                    <div class="service-stat-value">${mysqlConnections}</div>
                    <div class="service-stat-label">${t.service_connections || 'Connections'}</div>
                </div>
            </div>
            <div class="service-actions">
                <button class="btn btn-primary" onclick="controlService('MySQL', 'start')">
                    <i class="fas fa-play"></i> ${m.main?.start || 'Start'}
                </button>
                <button class="btn btn-danger" onclick="controlService('MySQL', 'stop')">
                    <i class="fas fa-stop"></i> ${m.main?.stop || 'Stop'}
                </button>
                <button class="btn btn-warning" onclick="controlService('MySQL', 'restart')">
                    <i class="fas fa-redo"></i> ${m.main?.restart || 'Restart'}
                </button>
            </div>
        </div>
    `;
}

/**
 * Update projects display
 * @param {Object} projects - Projects data object
 */
export function updateProjects(projects) {
    // Check that projects exists
    if (!projects) {
        console.error('Projects data not defined');
        elements.projectsGrid.innerHTML = `
            <div class="project-card">
                <div class="project-header">
                    <div class="project-title">Error</div>
                    <i class="fas fa-exclamation-triangle" style="color: #ef4444;"></i>
                </div>
                <p>Unable to load projects.</p>
            </div>
        `;
        return;
    }
    
    const t = currentTranslations.ui || {};
    
    let projectsHTML = '';
    
    for (const [name, project] of Object.entries(projects)) {
        // Use default values if some properties are missing
        const projectName = project.name || name;
        const projectSize = project.size || "0.0";
        const projectFiles = project.files || "0";
        const projectLastModified = project.last_modified || new Date().toISOString().replace('T', ' ').substring(0, 19);
        
        projectsHTML += `
            <div class="project-card">
                <div class="project-header">
                    <div class="project-title">${projectName}</div>
                    <i class="fas fa-folder" style="color: #f59e0b;"></i>
                </div>
                <div class="project-stats">
                    <div class="service-stat">
                        <div class="service-stat-value">${projectSize} MB</div>
                        <div class="service-stat-label">${t.project_size || 'Size'}</div>
                    </div>
                    <div class="service-stat">
                        <div class="service-stat-value">${projectFiles}</div>
                        <div class="service-stat-label">${t.project_files || 'Files'}</div>
                    </div>
                    <div class="service-stat">
                        <div class="service-stat-value">${projectLastModified.split(' ')[0]}</div>
                        <div class="service-stat-label">${t.project_last_modified || 'Last Modified'}</div>
                    </div>
                </div>
                <div style="margin-top: 15px;">
                    <a href="http://localhost/${name}" target="_blank" class="btn btn-primary" style="display: block; text-align: center;">
                        <i class="fas fa-external-link-alt"></i> ${t.open_project || 'Open'}
                    </a>
                </div>
            </div>
        `;
    }
    
    elements.projectsGrid.innerHTML = projectsHTML || `
        <div class="project-card">
            <div class="project-header">
                <div class="project-title">${t.projects || 'Projects'}</div>
                <i class="fas fa-folder" style="color: #64748b;"></i>
            </div>
            <p>No projects detected in the main directory.</p>
        </div>
    `;
}