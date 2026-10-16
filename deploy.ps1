# Mobi Boost — build & package for Hostinger deployment.
#
# Two modes:
#
#   Full deploy (default)      -> mobiboost-deploy.zip
#       index.html + assets/ + brand icons   (React production build)
#       api/                                 (PHP REST API)
#       .htaccess                            (SPA fallback + Authorization passthrough)
#       uploads/                             (empty, writable media folder)
#
#   Frontend only (-FrontendOnly) -> mobiboost-frontend.zip
#       index.html + assets/ + brand icons only. Use this for UI changes: it
#       CANNOT touch the live api/ folder or your api/config.php credentials.
#
# Usage:
#    powershell -NoProfile -ExecutionPolicy Bypass -File .\deploy.ps1
#    powershell -NoProfile -ExecutionPolicy Bypass -File .\deploy.ps1 -FrontendOnly
#
# Uploading (hPanel File Manager):
#    1. Upload the ZIP into public_html/.
#    2. Extract it THERE. The entries have no wrapper folder, so extracting in
#       public_html/ drops index.html + assets/ exactly where they belong.
#    3. If your host created a wrapper folder (e.g. the zip name), open it, select
#       everything inside, MOVE it into public_html/ and delete the wrapper.
#   4. NEVER delete or replace public_html/api/ to "clean up" — it holds your
#       live config.php (database password + jwtSecret).
param(
    [switch]$FrontendOnly
)

$ErrorActionPreference = 'Stop'
$root    = $PSScriptRoot
$stage   = Join-Path $env:TEMP "mb-deploy-stage"
$zipName = if ($FrontendOnly) { "mobiboost-frontend.zip" } else { "mobiboost-deploy.zip" }
$zip     = Join-Path $root $zipName

Write-Host "==> npm run build (frontend)" -ForegroundColor Cyan
Push-Location $root
npm run build
if ($LASTEXITCODE -ne 0) { throw "Build failed" }
Pop-Location

if (Test-Path $stage) { Remove-Item $stage -Recurse -Force }
New-Item -ItemType Directory -Path $stage | Out-Null

Write-Host "==> staging deploy files" -ForegroundColor Cyan
Copy-Item (Join-Path $root "dist\*") $stage -Recurse -Force

if (-not $FrontendOnly) {
    Copy-Item (Join-Path $root "api")       (Join-Path $stage "api") -Recurse -Force
    Copy-Item (Join-Path $root ".htaccess") (Join-Path $stage ".htaccess") -Force
    New-Item -ItemType Directory -Path (Join-Path $stage "uploads") | Out-Null
    Set-Content -Path (Join-Path $stage "uploads\.gitkeep") -Value "" -Encoding Ascii
}

if (Test-Path $zip) { Remove-Item $zip -Force }

# Zip with forward-slash entry names (Compress-Archive writes "\" on Windows,
# which breaks extraction on Linux/Hostinger). Use .NET ZipArchive directly.
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$prefix = $stage.TrimEnd('\') + '\'
$fs = [System.IO.File]::Open($zip, [System.IO.FileMode]::Create)
$archive = New-Object System.IO.Compression.ZipArchive($fs, [System.IO.Compression.ZipArchiveMode]::Create)

# Keep empty folders (e.g. uploads/) as explicit entries ending with '/'.
foreach ($dir in [System.IO.Directory]::GetDirectories($stage, '*', [System.IO.SearchOption]::AllDirectories)) {
    if (([System.IO.Directory]::GetFileSystemEntries($dir) | Measure-Object).Count -eq 0) {
        $rel = $dir.Substring($prefix.Length).Replace('\', '/') + '/'
        [void]$archive.CreateEntry($rel)
    }
}

foreach ($file in [System.IO.Directory]::GetFiles($stage, '*', [System.IO.SearchOption]::AllDirectories)) {
    $rel = $file.Substring($prefix.Length).Replace('\', '/')
    $entry = $archive.CreateEntry($rel, [System.IO.Compression.CompressionLevel]::Optimal)
    $stream = $entry.Open()
    try {
        $bytes = [System.IO.File]::ReadAllBytes($file)
        $stream.Write($bytes, 0, $bytes.Length)
    } finally {
        $stream.Dispose()
    }
}

$archive.Dispose()
$fs.Dispose()
Remove-Item $stage -Recurse -Force

Write-Host "==> Done: $zip" -ForegroundColor Green
if ($FrontendOnly) {
    Write-Host "Frontend-only build. Upload it to public_html/ and extract it there." -ForegroundColor Yellow
    Write-Host "It replaces index.html + assets/ only - api/, uploads/ and .htaccess stay as they are." -ForegroundColor Yellow
} else {
    Write-Host "Full deploy build. Upload it to public_html/ and extract it there." -ForegroundColor Yellow
    Write-Host "WARNING: this zip contains api/config.php with PLACEHOLDER credentials." -ForegroundColor Red
    Write-Host "         Extracting it over a live install overwrites your real config.php." -ForegroundColor Red
    Write-Host "         Edit api/config.php again after uploading (or use -FrontendOnly)." -ForegroundColor Red
}
