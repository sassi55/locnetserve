#Requires AutoHotkey v2.0

#Include "..\" A_ScriptDir "..\..\..\lib\jp.ahk"

GetProcessStartTime(procName) {
    query := "SELECT CreationDate FROM Win32_Process WHERE Name='" procName "'"
    earliestDate := ""
    
    for process in ComObjGet("winmgmts:").ExecQuery(query) {
        date := SubStr(process.CreationDate, 1, 14)  ; AAAAMMJJhhmmss
        
        ; Find the oldest date (first process started)
        if (earliestDate = "" || date < earliestDate) {
            earliestDate := date
        }
    }
    return earliestDate
}

CalcUptime(procName) {
    start := GetProcessStartTime(procName)
    if (start = "")
        return "00:00:00"
    startSecs := Abs(DateDiff(A_Now, start, "Seconds"))
    hours   := Floor(startSecs / 3600)
    minutes := Floor(Mod(startSecs, 3600) / 60)
    seconds := Mod(startSecs, 60)
    return Format("{:02}:{:02}:{:02}", hours, minutes, seconds)
}
; === End of uptime functions ===

GetBasePath() {
    ; Go up two levels from the script folder
    basePath := A_ScriptDir . "\..\..\"
    ; Convert to normalized absolute path
    basePath := RegExReplace(basePath, "\\[^\\]+\\\.\.\\?$", "\")
    basePath := RegExReplace(basePath, "[^\\]+\\\.\.\\", "")
    return basePath
}
class StatsUpdater {
    static __New() {
        this.basePath :=  A_ScriptDir
        this.statsFile := this.basePath . "\\apps\\dashboard\\stats.json"
        this.configFile := this.basePath . "\\config\\config.json"
        this.binPath := this.basePath . "\\bin\\"
        this.wwwPath := this.basePath . "\\www\\"
        this.timer := 0
        this.config := Map()
        this.loadConfig()
    }
   
    static loadConfig() {
        try {
            configContent := FileRead(this.configFile)
            this.config := JSON.parse(configContent)
        } catch Error as e {
            MsgBox "Error loading config: " this.configFile
        }
    }
    
    static start() {
        this.updateStats()
        this.timer := SetTimer(this.updateStats.Bind(this), 5000)
    }
    
    static stop() {
        if this.timer {
            SetTimer(this.timer, 0)
            this.timer := 0
        }
    }
    
static updateStats() {
    try {
        stats := Map()
        
        ; System stats
        stats["system"] := this.getSystemStats()
        
        ; Apache stats
        stats["apache"] := this.getApacheStats()
        
        ; MySQL stats
        stats["mysql"] := this.getMySQLStats()
        
        ; Projects stats
        stats["projects"] := this.getProjectsStats()
        
        ; Méthode garantie sans BOM
        this.writeJsonWithoutBOM(this.statsFile, stats)
        
    } catch Error as e {
        ; MsgBox "Error updating stats: " e.Message
    }
}

static writeJsonWithoutBOM(filePath, data) {
    jsonString := JSON.stringify(data)
    
   ; Open in binary mode to avoid any BOM
    file := FileOpen(filePath, "w")
    file.Write(jsonString)
    file.Close()
    
   ; Checking that the file does not have a BOM
    content := FileRead(filePath)
    if (SubStr(content, 1, 1) == Chr(0xFEFF)) {
       ; If BOM present, rewrite without BOM
        file := FileOpen(filePath, "w")
        file.Write(SubStr(content, 2))
        file.Close()
    }
}
    
    static getSystemStats() {
        sysStats := Map()
        
        ; CPU usage
        try {
		    wmi := ComObjGet("winmgmts:")
            cpu := wmi.ExecQuery("Select * from Win32_Processor")
        
            for processor in cpu
                sysStats["cpu_usage"] := Round(processor.LoadPercentage, 1)
        } catch {
            sysStats["cpu_usage"] := "0.0"
        }
        
        ; Memory usage
        try {
            
			wmi := ComObjGet("winmgmts:")
    mem := wmi.ExecQuery("Select * from Win32_OperatingSystem")
    
			for os in mem {
                totalMem := os.TotalVisibleMemorySize
                freeMem := os.FreePhysicalMemory
                usedMem := totalMem - freeMem
                sysStats["memory_usage"] := Round((usedMem / totalMem) * 100, 1)
            }
        } catch {
            sysStats["memory_usage"] := "0.0"
        }
        
        ; Disk usage
        try {
           wmi := ComObjGet("winmgmts:")
    drive := wmi.ExecQuery("Select * from Win32_LogicalDisk Where DeviceID='C:'")
    for disk in drive {
        totalSize := disk.Size
        freeSpace := disk.FreeSpace
        usedSpace := totalSize - freeSpace
        sysStats["disk_usage"] := Round((usedSpace / totalSize) * 100, 1)
    }
        } catch {
            sysStats["disk_usage"] := "0.0"
        }
        
        ; Network usage
        sysStats["network_usage"] := "125.4"
        
        return sysStats
    }
    
static getApacheStats() {
    apacheStats := Map()
    processName := "httpd.exe"
    
    ; Vérifier si Apache est en cours d'exécution
    if ProcessExist(processName) {
        apacheStats["status"] := "running"
        
 try {
    wmi := ComObjGet("winmgmts:")
    processes := wmi.ExecQuery("Select * from Win32_Process Where Name='" processName "'")
    for process in processes {
        apacheStats["cpu"] := Round(process.KernelModeTime / 10000000, 1)
        apacheStats["memory"] := Round(process.WorkingSetSize / 1024 / 1024, 2)
        
        ; Calculate the actual uptime
        processCreationDate := process.CreationDate
        if (processCreationDate) {
            ; Convert WMI date to compatible format
            wmiDate := SubStr(processCreationDate, 1, 14) ; yyyymmddHHMMSS
            wmiTime := wmiDate ; Format: yyyymmddHHMMSS
            
            ; Get the current time in the same format
            currentTime := FormatTime(, "yyyyMMddHHmmss")
            
            ; Calculate the difference in seconds
            timeDiff := currentTime - wmiTime
            uptimeSeconds := timeDiff
            
            ; Convert to hours, minutes, seconds
            hours := Floor(uptimeSeconds / 3600)
            minutes := Floor(Mod(uptimeSeconds, 3600) / 60)
            seconds := Mod(uptimeSeconds, 60)
            
            apacheStats["uptime"] := CalcUptime("httpd.exe")
        } else {
            apacheStats["uptime"] := CalcUptime("httpd.exe")
        }
        break 
    }
}
catch Error as e {
    apacheStats["cpu"] := "0"
    apacheStats["memory"] := "0"
    apacheStats["uptime"] := CalcUptime("httpd.exe")
}
        
        apacheStats["requests"] := Format("{}",Random(100, 500))
        
    } else {
        apacheStats["status"] := "stopped"
        apacheStats["cpu"] := "0"
        apacheStats["memory"] := "0"
        apacheStats["uptime"] := CalcUptime("httpd.exe")
        apacheStats["requests"] := Format("{}", 0)
    }
    
    return apacheStats
}
    
static getMySQLStats() {
    mysqlStats := Map()
    processName := "mysqld.exe"
    
    ; Check if MySQL is running
    if ProcessExist(processName) {
        mysqlStats["status"] := "running"
        
        ; CPU et mémoire
        try {
            wmi := ComObjGet("winmgmts:")
            processes := wmi.ExecQuery("Select * from Win32_Process Where Name='" processName "'")
            
            for process in processes {
                mysqlStats["cpu"] := Round(process.KernelModeTime / 10000000, 1)
                mysqlStats["memory"] := Round(process.WorkingSetSize / 1024 / 1024, 2)
                
               
                processCreationDate := process.CreationDate
                if (processCreationDate && StrLen(processCreationDate) >= 14) {
                    try {
                        
                        wmiDate := SubStr(processCreationDate, 1, 14) ; yyyymmddHHMMSS
                        wmiTime := wmiDate
                        currentTime := FormatTime(, "yyyyMMddHHmmss")
                        
                        
                        timeDiff := currentTime - wmiTime
                        
                        hours := Floor(timeDiff / 3600)
                        minutes := Floor(Mod(timeDiff, 3600) / 60)
                        seconds := Mod(timeDiff, 60)
                        
                        mysqlStats["uptime"] := CalcUptime("mysqld.exe")
                    } catch {
                        mysqlStats["uptime"] := CalcUptime("mysqld.exe")
                    }
                } else {
                    mysqlStats["uptime"] := CalcUptime("mysqld.exe")
                }
                break 
            }
        } catch {
            mysqlStats["cpu"] := "0.3"
            mysqlStats["memory"] := "26.11"
            mysqlStats["uptime"] := CalcUptime("mysqld.exe")
        }
        
        mysqlStats["connections"] := Format("{}", Random(5, 15))
        
    } else {
        mysqlStats["status"] := "stopped"
        mysqlStats["cpu"] := "0"
        mysqlStats["memory"] := "0"
        mysqlStats["uptime"] := CalcUptime("mysqld.exe")
        mysqlStats["connections"] := "0"
    }
    
    return mysqlStats
}
    
    static getProjectsStats() {
        projectsStats := Map()
        projectsDir := this.wwwPath
        
        
        Loop Files projectsDir . "*", "D" {
            if A_LoopFileName != "dashboard" {
                project := Map()
                project["name"] := A_LoopFileName
                project["files"] := Format("{}", this.countFiles(projectsDir . A_LoopFileName))
                project["size"] := this.getFolderSize(projectsDir . A_LoopFileName)
                project["last_modified"] := this.getLastModified(projectsDir . A_LoopFileName)
                
                projectsStats[A_LoopFileName] := project
            }
        }
        
        return projectsStats
    }
    
    static countFiles(folderPath) {
        fileCount := 0
        Loop Files folderPath . "\*", "R" {
            fileCount++
        }
        return fileCount
    }
    
    static getFolderSize(folderPath) {
        totalSize := 0
        Loop Files folderPath . "\*", "R" {
            totalSize += A_LoopFileSize
        }
        return Round(totalSize / 1024 / 1024, 1)
    }
    
    static getLastModified(folderPath) {
        latestTime := 0
        Loop Files folderPath . "\*", "R" {
            fileTime := FileGetTime(A_LoopFileFullPath)
            if fileTime > latestTime {
                latestTime := fileTime
            }
        }
        
        if latestTime {
		formattedTime := FormatTime(latestTime, "yyyy-MM-dd HH:mm:ss")
       
            return formattedTime
        }
        
        return FormatTime(, "yyyy-MM-dd HH:mm:ss")
    }
}

; Example usage:
; StatsUpdater.stop() ; Stop updating
; StatsUpdater.updateStats() ; Manual updating
; StatsUpdater.start()