$log = 'C:\Users\HP\Desktop\wirdscrepss\logs\enable-hypervisor.log'
Start-Transcript -Path $log -Force
bcdedit /set hypervisorlaunchtype auto
Write-Host 'hypervisorlaunchtype set'
bcdedit
Stop-Transcript
