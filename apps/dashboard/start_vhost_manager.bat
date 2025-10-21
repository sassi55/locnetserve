@echo off
cd /d "C:\MyServer\apps\dashboard"
echo Démarrage du gestionnaire d'hôtes virtuels...
"C:\Program Files\AutoHotkey\v2\AutoHotkey64.exe" "VHostManager.ahk"
echo Gestionnaire démarré avec PID: %errorlevel%
pause