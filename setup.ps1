# Windows (PowerShell): unduh CodeIgniter 3.1.13 dan salin folder "system" (sekali saja)
Set-Location $PSScriptRoot
if (Test-Path "system\core") { Write-Host "Folder system sudah ada."; exit }
Invoke-WebRequest "https://github.com/bcit-ci/CodeIgniter/archive/refs/tags/3.1.13.zip" -OutFile ci3.zip
Expand-Archive ci3.zip -DestinationPath ci3_tmp -Force
if (Test-Path "system") { Remove-Item system -Recurse -Force }
Copy-Item "ci3_tmp\CodeIgniter-3.1.13\system" -Destination "system" -Recurse
Remove-Item ci3_tmp -Recurse -Force; Remove-Item ci3.zip
Write-Host "Selesai. Lanjut: edit application/config/local.php lalu import database."
