# Installation Guide

## System Requirements
- Windows 10 or newer (64-bit)
- 2GB RAM minimum (4GB recommended)
- 500MB free disk space
- Administrator privileges for service installation

## Quick Installation

### Method 1: Automated Installer (Recommended)
1. Download the latest release
2. Run `install/setup.exe`
3. Open Command Prompt as Administrator
4. Execute: `serve install`
5. Wait for installation to complete

### Method 2: Manual Installation
1. Extract LocNetServe to `C:\MyServer\`
2. Configure paths in `config/config.json`
3. Run `LocNetServe.exe` for GUI interface
4. Use `bin\lns.exe` for CLI interface

## Verification

After installation, verify everything works:

```bash
# Check service status
lns status

# Access web interfaces
# Dashboard: http://localhost/dashboard
# phpMyAdmin: http://localhost/phpmyadmin  
# Localhost: http://localhost/