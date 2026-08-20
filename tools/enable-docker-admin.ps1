$log = 'C:\Users\HP\Desktop\wirdscrepss\logs\enable-docker-admin.log'
New-Item -ItemType Directory -Force -Path (Split-Path $log) | Out-Null
Start-Transcript -Path $log -Force
$ErrorActionPreference = 'Continue'
Write-Host '=== Features ==='
dism.exe /online /enable-feature /featurename:Microsoft-Windows-Subsystem-Linux /all /norestart
dism.exe /online /enable-feature /featurename:VirtualMachinePlatform /all /norestart
Write-Host '=== WSL install (no distro) ==='
wsl.exe --install --no-distribution
Write-Host '=== Docker service ==='
Start-Service com.docker.service -ErrorAction SilentlyContinue
sc.exe start com.docker.service | Out-Host
$dockerDesktop = 'C:\Program Files\Docker\Docker\Docker Desktop.exe'
if (Test-Path $dockerDesktop) {
  Start-Process $dockerDesktop
}
Write-Host 'DONE'
Stop-Transcript
exit 0
