$ErrorActionPreference = "Continue"
$log = "C:\Users\HP\Desktop\wirdscrepss\dism-enable.log"
Start-Transcript -Path $log -Force
Write-Host "Enabling Microsoft-Windows-Subsystem-Linux..."
dism.exe /online /enable-feature /featurename:Microsoft-Windows-Subsystem-Linux /all /norestart
Write-Host "Enabling VirtualMachinePlatform..."
dism.exe /online /enable-feature /featurename:VirtualMachinePlatform /all /norestart
Write-Host "---- STATUS WSL ----"
dism.exe /online /get-featureinfo /featurename:Microsoft-Windows-Subsystem-Linux
Write-Host "---- STATUS VMP ----"
dism.exe /online /get-featureinfo /featurename:VirtualMachinePlatform
Stop-Transcript
