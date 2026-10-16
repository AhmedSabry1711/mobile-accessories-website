# Mobi Boost - build & package for Hostinger deployment.
#
# Three modes:
#
#   Full deploy (default)          -> mobiboost-deploy.zip
#       index.html + assets/ + brand icons   (React production build)
#       api/                                 (PHP REST API)
#       .htaccess                            (SPA fallback + Authorization passthrough)
#       uploads/                             (empty, writable media folder)
#
#   Frontend only (-FrontendOnly)  -> mobiboost-frontend.zip
#       index.html + assets/ + brand icons only. Use this for UI/design changes:
#       it CANNOT touch the live api/ folder or your live credentials.
#
#   API only (-ApiOnly)            -> mobiboost-api.zip
#       The api/ folder ONLY, and WITHOUT config.php. Use this after changing PHP:
#       extracting it in public_html/ refreshes public_html/api/ and leaves your
#       live config.php (database password + jwtSecret) exactly as it is.
#
# Usage:
#    powershell -NoProfile -ExecutionPolicy Bypass -File .\deploy.ps1
#    powershell -NoProfile -ExecutionPolicy Bypass -File .\deploy.ps1 -FrontendOnly
#    powershell -NoProfile -ExecutionPolicy Bypass -File .\deploy.ps1 -ApiOnly
#
# Uploading (hPanel File Manager):
#    1. Upload the ZIP into public_html/.
#    2. Extract it THERE. The entries carry their own api/ prefix (or no wrapper
#       folder for frontend builds), so extracting in public_html/ lands them
#       exactly where they belong.
#    3. If your host created a wrapper folder (e.g. named after the zip), open it,
#       select everything inside, MOVE it into public_html/ and delete the wrapper.
#    4. NEVER delete public_html/api/ to "clean up" - it holds the live config.php.
param(
    [switch]$FrontendOnly,
    [switch]$ApiOnly
)

if ($FrontendOnly -and $ApiOnly) {
    throw "Use either -FrontendOnly or -ApiOnly, not both."
}

$ErrorActionPreference = 'Stop'
$root  = $PSScriptRoot
$stage = Join-Path $env:TEMP "mb-deploy-stage"

if ($ApiOnly) {
    $zipName = "mobiboost-api.zip"
} elseif ($FrontendOnly) {
    $zipName = "mobiboost-frontend.zip"
} else {
    $zipName = "mobiboost-deploy.zip"
}
$zip = Join-Path $root $zipName

# The API is plain PHP: nothing to build.
if (-not $ApiOnly) {
    Write-Host "==> npm run build (frontend)" -ForegroundColor Cyan
    Push-Location $root
    npm run build
    if ($LASTEXITCODE -ne 0) { throw "Build failed" }
    Pop-Location
}

if (Test-Path $stage) { Remove-Item $stage -Recurse -Force }
New-Item -ItemType Directory -Path $stage | Out-Null

Write-Host "==> staging deploy files" -ForegroundColor Cyan

if ($ApiOnly) {
    Copy-Item (Join-Path $root "api") (Join-Path $stage "api") -Recurse -Force
} else {
    Copy-Item (Join-Path $root "dist\*") $stage -Recurse -Force
}

if (-not $FrontendOnly) {
    if (-not $ApiOnly) {
        Copy-Item (Join-Path $root "api") (Join-Path $stage "api") -Recurse -Force
        Copy-Item (Join-Path $root ".htaccess") (Join-Path $stage ".htaccess") -Force
        New-Item -ItemType Directory -Path (Join-Path $stage "uploads") | Out-Null
        Set-Content -Path (Join-Path $stage "uploads\.gitkeep") -Value "" -Encoding Ascii
    }

    # Credentials must never leave this machine / never overwrite the live file.
    foreach ($secret in @('config.php', 'config.private.php')) {
        $leak = Join-Path $stage "api\$secret"
        if (Test-Path $leak) { Remove-Item $leak -Force }
    }

    foreach ($required in @('api\_bootstrap.php', 'api\lib\store.php', 'api\products.php', 'api\settings.php', 'api\auth.php')) {
        if (-not (Test-Path (Join-Path $stage $required))) {
            throw "Staged artifact is missing $required - aborting."
        }
    }
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
if ($ApiOnly) {
    Write-Host "API-only build (no config.php inside). Upload it to public_html/ and extract it there." -ForegroundColor Yellow
    Write-Host "It refreshes public_html/api/ and leaves your live config.php untouched." -ForegroundColor Yellow
} elseif ($FrontendOnly) {
    Write-Host "Frontend-only build. Upload it to public_html/ and extract it there." -ForegroundColor Yellow
    Write-Host "It replaces index.html + assets/ only - api/, uploads/ and .htaccess stay as they are." -ForegroundColor Yellow
} else {
    Write-Host "Full deploy build. Upload it to public_html/ and extract it there." -ForegroundColor Yellow
    Write-Host "WARNING: this zip contains api/config.php with PLACEHOLDER credentials." -ForegroundColor Red
    Write-Host "         Extracting it over a live install overwrites your real config.php." -ForegroundColor Red
    Write-Host "         Edit api/config.php again after uploading (or use -FrontendOnly / -ApiOnly)." -ForegroundColor Red
}
