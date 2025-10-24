<meta name="description" content="LocNetServe: Complete local web server for Windows with Apache, MySQL, PHP stack. Real-time dashboard, virtual host manager, and multi-interface control. Perfect alternative to XAMPP and WAMP.">
<meta name="keywords" content="local web server, localhost, phpmyadmin, apache, mysql, php, web development, windows web server, xampp alternative, development environment">
# 🚀 LocNetServe - Ultimate Local Web Server for Windows Development

[![Windows](https://img.shields.io/badge/Windows-10+-0078D6?style=for-the-badge&logo=windows)](https://www.microsoft.com/en-us/windows)
[![Apache](https://img.shields.io/badge/Apache-2.4-D22128?style=for-the-badge&logo=apache)](https://httpd.apache.org/)
[![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?style=for-the-badge&logo=mysql)](https://www.mysql.com/)
[![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?style=for-the-badge&logo=php)](https://www.php.net/)
[![AutoHotkey](https://img.shields.io/badge/AutoHotkey-v2-334455?style=for-the-badge&logo=autohotkey)](https://www.autohotkey.com/)
![GitHub release](https://img.shields.io/github/v/release/sassisouid/locnetserve)

---

## 🔥 Why Choose LocNetServe? The Ultimate Localhost Solution

LocNetServe is the **all-in-one local web server** that eliminates the complexity of setting up your **local development environment**. Perfect for **web developers**, **students**, and **agencies** who need a reliable **localhost server** with **phpMyAdmin** and full-stack capabilities.

### 🏆 What Makes Us Different?

| Feature | LocNetServe | XAMPP | WAMP | Laragon |
|---------|-------------|-------|------|---------|
| **Web Dashboard** | ✅ Real-time monitoring | ❌ | ❌ | Limited |
| **CLI + GUI** | ✅ Both interfaces | ❌ CLI only | ❌ GUI only | ✅ |
| **Multi-language** | ✅ EN/FR/ES | ❌ | ❌ | ❌ |
| **Auto Health Checks** | ✅ | ❌ | ❌ | ❌ |
| **Virtual Host Manager** | ✅ Visual interface | ❌ Manual | ❌ Manual | ✅ |
| **Real-time Stats** | ✅ | ❌ | ❌ | ❌ |

---
## 🌐 Website
**Live Demo & Documentation:** https://sassisouid.github.io/locnetserve
## 🎯 Perfect For These Use Cases

### 🚀 **Local Web Development**
- **PHP projects** with full **localhost** environment
- **WordPress development** and testing
- **Laravel, Symfony, CodeIgniter** frameworks
- **Custom web applications** with MySQL database

### 🎓 **Learning & Education**
- **Web development courses** - perfect setup for students
- **PHP & MySQL tutorials** - pre-configured environment
- **Frontend projects** with local server backend

### 💼 **Professional Development**
- **Agency workflows** - consistent environment across teams
- **Client project demos** - showcase on local network
- **Prototyping** - rapid setup for new ideas

---

## ✨ Key Features That Developers Love

### 🖥️ **Complete Web Stack**
- **Apache 2.4.65** - Industry-standard web server
- **MySQL 9.4.0** - Robust database management
- **PHP 8.2.29** - Latest PHP with common extensions
- **phpMyAdmin** - Web-based database management

### 🎛️ **Multiple Control Interfaces**
```bash
# Command Line (Power Users)
lns status
lns apache restart
lns mysql backup

# System Tray (Quick Actions)
# Right-click for instant service control

# Web Dashboard (Visual Management)
# http://localhost:8080 - Real-time monitoring
```

### 📊 **Advanced Monitoring**
- **Real-time CPU/Memory usage**
- **Service status tracking**
- **Request/Connection statistics**
- **Automatic health checks**

### 🌐 **Professional Tools**
- **Virtual Host Manager** - Easy project setup
- **Database backup/restore** - Never lose data
- **Multi-language support** - EN/FR/ES
- **Port management** - Conflict-free operation

---

## 🚀 Quick Start - Get Running in 2 Minutes

### Installation Steps

1. **Download & Extract**
   ```bash
   git clone https://github.com/sassisouid/locnetserve.git
   cd locnetserve
   ```

2. **Auto-Installation**
   ```bash
   # Run the installer
   cd install
   setup.exe
   # Follow the prompts - it's that simple!
   ```

3. **Launch & Enjoy**
   ```bash
   # Start everything
   lns start all
   
   # Access your environments:
   # Dashboard:    http://localhost/dashboard
   # phpMyAdmin:   http://localhost/phpmyadmin  
   # Localhost:    http://localhost/
   # PHP Info:     http://localhost/info.php
   ```

---

## 💻 Complete Command Reference

### 🛠️ **Essential Commands**
```bash
# Service Management
lns start all              # Start all services
lns stop all               # Stop all services  
lns status                 # Check service status
lns apache restart         # Restart Apache
lns mysql backup           # Backup databases

# Information
lns -v                     # Version info
lns help                   # Full command list
```

### 🗄️ **Database Operations**
```bash
# MySQL Management
lns mysql shell            # Open MySQL command line
lns mysql create mydb      # Create new database
lns mysql export mydb      # Export database to SQL
lns mysql import mydb file.sql  # Import SQL file

# User Management  
lns mysql users            # List all users
lns mysql user add john password123  # Create user
```

### 🌐 **Virtual Hosts & Projects**
```bash
# Project Management
lns vhosts show                    # List all virtual hosts
lns -vh open myproject.local       # Access project
lns -vh create blog /path/to/site  # Create new host
```

### ⚙️ **Advanced Features**
```bash
# PHP Management
lns php ext list           # Show all extensions
lns php ext enable curl    # Enable specific extension

# Utilities
lns utils backup all       # Full backup (DB + files)
lns utils health check     # System health check
lns utils show ports       # Check port usage
```

---

## 📊 Web Dashboard Features

Access `http://localhost/dashboard` for visual management:

### 🎯 **Real-time Monitoring**
- **Service status** with live indicators
- **System resources** (CPU, Memory, Disk)
- **Apache requests** and **MySQL connections**
- **Project overview** with quick access

### 🛠️ **Visual Controls**
- **One-click service** start/stop/restart
- **Virtual host management** with form interface
- **Database operations** without command line
- **Log viewer** for quick debugging

### 📈 **Statistics & Analytics**
- **Uptime tracking** for each service
- **Performance metrics** over time
- **Resource usage** trends
- **Project activity** monitoring

---


### Virtual Host Configuration
```bash
# Create custom virtual host
lns -vh create mysite.local C:\locnetserve\www\mysite

# Automatically updates:
# - Apache vhosts configuration
# - Windows hosts file
# - Creates project structure
```

---

## 🛠️ System Requirements

| Component | Requirement |
|-----------|-------------|
| **OS** | Windows 10, 11 (64-bit) |
| **RAM** | 2GB minimum (4GB recommended) |
| **Storage** | 500MB free space |
| **Architecture** | x64 |
| **Admin Rights** | Required for service installation |

---

## ❓ Frequently Asked Questions

### 🤔 **How is this different from XAMPP/WAMP?**
LocNetServe provides **modern interfaces** (CLI + GUI + Web), **real-time monitoring**, and **developer-focused tools** that traditional packages lack.

### 🔧 **Can I use it with existing projects?**
**Absolutely!** Just point your virtual hosts to existing project folders - no migration needed.

### 🌐 **Can I access from other devices?**
**Yes!** Your **local network server** can be accessed by other devices on the same network.

### 💾 **What about data safety?**
**Automatic MySQL backups** and **config versioning** ensure your work is always safe.

### 🆓 **Is it really free?**
**100% free and open-source** - no hidden costs, no premium features.

---

## 🚀 Use Cases & Success Stories

### 🏢 **Web Development Agencies**
*"We switched our entire team to LocNetServe. The consistent environment and virtual host manager saved us 10+ hours per week in setup time."*

### 🎓 **Coding Bootcamps**  
*"Our students can now focus on learning PHP and MySQL instead of fighting with server configuration. The web dashboard makes debugging so much easier."*

### 💼 **Freelance Developers**
*"I use LocNetServe for all client projects. The quick setup and reliable operation let me deliver faster and more professionally."*

---

## 🤝 Contributing & Community

We love our community! Here's how you can help:

### 🐛 **Report Issues**
Found a bug? [Open an issue](https://github.com/sassisouid/locnetserve/issues) with details.

### 💡 **Suggest Features**
Have an idea? We'd love to hear it!

### 🔧 **Code Contributions**
1. Fork the repository
2. Create your feature branch (`git checkout -b amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin amazing-feature`)
5. Open a Pull Request

### 🌍 **Translation Help**
Help us translate LocNetServe to more languages!

---

## 📞 Support & Resources

- **📧 Email**: locnetserve@gmail.com
- **🐛 Bug Reports**: [GitHub Issues](https://github.com/sassisouid/locnetserve/issues)
- **💬 Discussions**: [GitHub Discussions](https://github.com/sassisouid/locnetserve/discussions)
- **📚 Documentation**: [Wiki](https://github.com/sassisouid/locnetserve/wiki)

---

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

## 🙏 Acknowledgments

Thanks to the amazing open-source community and all our contributors who make LocNetServe better every day!

---

**⭐ Star us on GitHub if LocNetServe helps your development workflow!**

**🚀 Ready to transform your local development experience? Download LocNetServe today!**

---

*Keywords: local web server, localhost server, local development server, phpmyadmin localhost, web development environment, apache mysql php windows, local web server for windows, localhost php development, web server local network, development server with dashboard, local server manager, php development environment, mysql local server, apache localhost, web development tools, local server for web development, windows web server, local network development server, phpmyadmin web server, complete local development stack*
