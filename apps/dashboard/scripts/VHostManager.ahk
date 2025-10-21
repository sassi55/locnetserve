#Requires AutoHotkey v2.0
#SingleInstance Force
#NoTrayIcon
Persistent(true)
; Inclure la bibliothèque JSON
#Include %A_WorkingDir%\..\..\..\lib\jp.ahk

; Configuration - chemins absolus
mybaseDir := A_WorkingDir
baseDir := StrReplace(mybaseDir, "\apps\dashboard\scripts", "")
config := Map(
    "vhosts_path", baseDir . "\bin\apache\conf\extra\vhosts\",
    "commands_file", baseDir . "\tmp\ahk_commands.json",
    "check_interval", 3000,
    "log_file", baseDir . "\tmp\vhost_manager.log",
    "ipc_file", baseDir . "\tmp\ahk_ipc.json" ; Fichier de communication
)

; Afficher un message de démarrage
ShowNotification("Gestionnaire d'hôtes virtuels démarré", "info")
LogMessage("=== Gestionnaire d'hôtes virtuels démarré ===")

; Vérifier et créer les dossiers nécessaires
InitializeFolders()

; Démarrer le monitoring des commandes
SetTimer CheckCommands, config["check_interval"]
CheckCommands()  ; Vérifier immédiatement au démarrage

; Fonction de logging
LogMessage(message) {
    global config
    try {
        timestamp := FormatTime(, "yyyy-MM-dd HH:mm:ss")
        logEntry := "[" . timestamp . "] " . message . "`n"
        FileAppend(logEntry, config["log_file"])
    } catch {
        ; Si le logging échoue, on continue sans logger
    }
}

; Initialiser les dossiers nécessaires
InitializeFolders() {
    global config
    
    ; Créer le dossier vhosts s'il n'existe pas
    if !DirExist(config["vhosts_path"]) {
        try {
            DirCreate(config["vhosts_path"])
            LogMessage("Dossier vhosts créé: " . config["vhosts_path"])
        } catch as err {
            LogMessage("ERREUR création dossier vhosts: " . err.Message)
        }
    }
    
    ; Créer le dossier tmp s'il n'existe pas
    tmpDir := "C:\MyServer\tmp\"
    if !DirExist(tmpDir) {
        try {
            DirCreate(tmpDir)
            LogMessage("Dossier tmp créé: " . tmpDir)
        } catch as err {
            LogMessage("ERREUR création dossier tmp: " . err.Message)
        }
    }
}

; Fonction pour vérifier les commandes
CheckCommands() {
    global config
    
    ; Vérifier si le fichier de commandes existe
    if !FileExist(config["commands_file"]) {
        return
    }
    
    try {
        ; Lire et traiter les commandes
        jsonText := FileRead(config["commands_file"])
        LogMessage("Commandes à traiter: " . jsonText)
        commands := JSON.parse(jsonText)
        
        LogMessage("Traitement de " . commands.Length . " commande(s)")
        
        for command in commands {
            ProcessCommand(command)
        }
        
        ; Effacer le fichier de commandes après traitement
        FileDelete(config["commands_file"])
        LogMessage("Commandes traitées avec succès - fichier supprimé")
        
    } catch as err {
        ; Log l'erreur
        errorMsg := "Erreur traitement commandes: " . err.Message
        LogMessage(errorMsg)
        ShowNotification("Erreur traitement commandes", "error")
    }
}

; Traiter une commande
ProcessCommand(command) {
    try {
        LogMessage("Traitement commande: " . command["type"] . " - " . command["name"])
        
        switch command["type"] {
            case "create_vhost":
                success := CreateVirtualHost(command["name"], command["path"])
                if success {
                    LogMessage("Hôte virtuel créé avec succès: " . command["name"])
                    ShowNotification("Hôte virtuel créé: " . command["name"], "success")
                    
                    ; Demander à LocNetServe de redémarrer Apache
                    RequestApacheRestart()
                } else {
                    LogMessage("Échec création hôte: " . command["name"] . " dans : " command["path"])
                    ShowNotification("Erreur création hôte: " . command["name"], "error")
                }
                
            case "delete_vhost":
                success := DeleteVirtualHost(command["name"])
                if success {
                    LogMessage("Hôte virtuel supprimé avec succès: " . command["name"])
                    ShowNotification("Hôte virtuel supprimé: " . command["name"], "success")
                    
                    ; Demander à LocNetServe de redémarrer Apache
                    RequestApacheRestart()
                } else {
                    LogMessage("Échec suppression hôte: " . command["name"])
                    ShowNotification("Erreur suppression hôte: " . command["name"], "error")
                }
        }
    } catch as err {
        errorMsg := "Erreur exécution commande: " . err.Message
        LogMessage(errorMsg)
        ShowNotification("Erreur exécution commande", "error")
    }
}

; Demander à LocNetServe de redémarrer Apache
RequestApacheRestart() {
    global config
    
    try {
        ; Créer une demande de redémarrage
        request := Map(
            "action", "restart_apache",
            "timestamp", A_Now,
            "reason", "virtual_host_change"
        )
        
        ; Écrire la demande dans le fichier IPC
        FileAppend(JSON.stringify(request, 4), config["ipc_file"])
        LogMessage("Demande de redémarrage Apache envoyée à LocNetServe")
        
    } catch as err {
        LogMessage("ERREUR envoi demande redémarrage: " . err.Message)
    }
}


; Créer un hôte virtuel
CreateVirtualHost(name, path) {
    global config
    
    ; Valider le nom (pas de caractères spéciaux)
    if !RegExMatch(name, "^[a-zA-Z0-9.-]+$") {
        ShowNotification("Nom d'hôte invalide: " name, "error")
        return false
    }
    
    ; Valider le chemin
    if !DirExist(path) {
        ShowNotification("Dossier introuvable: " path, "error")
        return false
    }
    
    ; Créer le dossier vhosts s'il n'existe pas
    if !DirExist(config["vhosts_path"]) {
        DirCreate(config["vhosts_path"])
    }
    
template := "
(
<VirtualHost *:80>
    ServerName {NAME}.localhost
    DocumentRoot "{PATH}"
    <Directory "{PATH}">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    ErrorLog logs/{NAME}-error.log
    CustomLog logs/{NAME}-access.log common
</VirtualHost>
)"
    
    ; Remplacer les placeholders
    content := StrReplace(template, "{NAME}", name)
    content := StrReplace(content, "{PATH}", path)
    
    ; Écrire le fichier de configuration
    configFile := config["vhosts_path"] name ".conf"
    
    try {
        FileAppend(content, configFile)
        ShowNotification("Fichier de configuration créé: " configFile, "info")
        return true
    } catch as err {
        errorMsg := "[" A_Now "] Error creating vhost: " err.Message "`n"
        FileAppend(errorMsg, A_WorkingDir "\error.log")
        return false
    }
	
	    ; CRÉATION AUTOMATIQUE DU DOSSIER (ajoutez cette section)
    if !DirExist(path) {
        try {
            DirCreate(path)
            LogMessage("Dossier projet créé: " . path)
            
            ; Créer un fichier index.html par défaut
            defaultHtml := "
            (
<!DOCTYPE html>
<html>
<head>
    <title>{NAME}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        h1 { color: #333; }
    </style>
</head>
<body>
    <h1>Bienvenue sur {NAME}</h1>
    <p>Votre hôte virtuel est configuré avec succès !</p>
    <p>Dossier : {PATH}</p>
</body>
</html>
            )"
            
            defaultHtml := StrReplace(defaultHtml, "{NAME}", name)
            defaultHtml := StrReplace(defaultHtml, "{PATH}", path)
            
            FileAppend(defaultHtml, path . "\index.html")
            LogMessage("Fichier index.html créé: " . path . "\index.html")
            
        } catch as err {
            LogMessage("ERREUR création dossier: " . err.Message)
            ; Continuer quand même avec la création du vhost
        }
    }
	
	
	
}
; Supprimer un hôte virtuel
DeleteVirtualHost(name) {
    global config
    
    configFile := config["vhosts_path"] name ".conf"
    
    if !FileExist(configFile) {
        ShowNotification("Fichier de configuration introuvable: " configFile, "error")
        return false
    }
    
    try {
        FileDelete(configFile)
        ShowNotification("Fichier de configuration supprimé: " configFile, "info")
        return true
    } catch as err {
        errorMsg := "[" A_Now "] Error deleting vhost: " err.Message "`n"
        FileAppend(errorMsg, A_WorkingDir "\error.log")
        return false
    }
}

; Afficher une notification
ShowNotification(message, type := "info") {
    icon := type = "error" ? 3 : type = "warning" ? 2 : 1
    TrayTip(message, "Gestionnaire d'hôtes virtuels", icon)
    SetTimer(() => TrayTip(), -3000)
    
    ; Aussi logger dans un fichier
    logMsg := "[" A_Now "] " type ": " message "`n"
    FileAppend(logMsg, A_WorkingDir "\vhost_manager.log")
}