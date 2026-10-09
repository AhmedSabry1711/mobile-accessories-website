# Mobi Boost — build & package for Hostinger deployment.
# Produces mobiboost-deploy.zip containing exactly what goes into public_html/:
#   index.html + assets/   (React production build)
#   api/                   (PHP REST API)
#   .htaccess              (SPA fallback routing)
#   uploads/               (empty, writable media folder)
#
# Usage: right-click -> "Run with PowerShell", or:
#    powershell -NoProfile -ExecutionPolicy Bypass -File .\deploy.ps1
# Then upload the ZIP CONTENTS to public_html/ in hPanel File Manager and
# follow the README (configure api/config.php, run the seeder, change password).
$ErrorActionPreference = 'Stop'
$root   = $PSScriptRoot
$stage  = Join-Path $env:TEMP "mb-deploy-stage"
$zip    = Join-Path $root "mobiboost-deploy.zip"

Write-Host "==> npm run build (frontend)" -ForegroundColor Cyan
Push-Location $root
npm run build
if ($LASTEXITCODE -ne 0) { throw "Build failed" }
Pop-Location

if (Test-Path $stage) { Remove-Item $stage -Recurse -Force }
New-Item -ItemType Directory -Path $stage | Out-Null

Write-Host "==> staging deploy files" -ForegroundColor Cyan
Copy-Item (Join-Path $root "dist\*")      $stage -Recurse -Force
Copy-Item (Join-Path $root "api")         (Join-Path $stage "api") -Recurse -Force
Copy-Item (Join-Path $root ".htaccess")   (Join-Path $stage ".htaccess") -Force
New-Item -ItemType Directory -Path (Join-Path $stage "uploads") | Out-Null
Set-Content -Path (Join-Path $stage "uploads\.gitkeep") -Value "" -Encoding Ascii

if (Test-Path $zip) { Remove-Item $zip -Force }
Compress-Archive -Path (Join-Path $stage "*") -DestinationPath $zip -CompressionLevel Optimal
Remove-Item $stage -Recurse -Force

Write-Host "==> Done: $zip" -ForegroundColor Green
Write-Host "Upload the ZIP CONTENTS into public_html/ on Hostinger, then:" -ForegroundColor Yellow
Write-Host "   1) Edit api/config.php  (jwtSecret, corsOrigin, database creds)" -ForegroundColor Yellow
Write-Host "   2) Run once: php api/seed.php" -ForegroundColor Yellow
Write-Host "   3) Open /admin and change the default password" -ForegroundColor Yellow