#Requires AutoHotkey v2.0



; dashboard\HttpServer.ahk
class HttpServer {
    static Start() {
        ; Démarrer la surveillance des fichiers
        SetTimer(HttpServer.CheckCommands, 1000)
        FileAppend("[" A_Now "] Service monitoring started`n", "server.log")
    }
    
    static CheckCommands() {
        ; Vérifier si le fichier de commande existe
        if FileExist("www\dashboard\command.txt") {
            try {
                content := FileRead("www\dashboard\command.txt")
                FileDelete("www\dashboard\command.txt")
                
                ; Lire la commande (format: service|action)
                parts := StrSplit(content, "|")
                if parts.Length >= 2 {
                    service := parts[1]
                    action := parts[2]
                    
                    ; Exécuter la commande
                    HttpServer.ExecuteCommand(service, action)
                }
            } catch {
                ; Ignorer les erreurs
            }
        }
    }
    
    static ExecuteCommand(service, action) {
        service := StrUpper(service) ; Convertir en majuscules
        
        if (service = "APACHE" || service = "MYSQL") {
            switch action {
                case "start": StartService(service)
                case "stop": StopService(service)
                case "restart": RestartService(service)
            }
        }
    }
    
    static SendCommand(service, action) {
        ; Méthode pour envoyer une commande
        try {
            FileAppend(service "|" action, "www\dashboard\command.txt")
            return true
        } catch {
            return false
        }
    }
}