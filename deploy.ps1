# Mobi Boost - build & package for Hostinger deployment.
#
# Three modes:
#
#   Full deploy (default)          -> mobiboost-deploy.zip
#       index.html + assets/ + brand icons   (React production build)
#       api/                                 (PHP REST API, WITHOUT config.php)
#       .htaccess                            (SPA fallback + Authorization passthrough)
#       uploads/                             (empty, writable media folder)
#
#   Frontend only (-FrontendOnly)  -> mobiboost-frontend.zip
#       index.html + assets/ + brand icons only. Use this for UI changes: it
#       CANNOT touch the live api/ folder or your credentials.
#
#   Publish branch (-Publish)      -> force-pushes mobiboost-deploy.zip's tree to
#       the `deploy` branch, which is what Hostinger should be configured to pull
#       (see HOSTINGER-GIT-DEPLOY.md). This is the local equivalent of
#       .github/workflows/deploy.yml - use it when you would rather not rely on
#       GitHub Actions. No zip is produced in this mode.
#
# Usage:
#    powershell -NoProfile -ExecutionPolicy Bypass -File .\deploy.ps1
#    powershell -NoProfile -ExecutionPolicy Bypass -File .\deploy.ps1 -FrontendOnly
#    powershell -NoProfile -ExecutionPolicy Bypass -File .\deploy.ps1 -Publish
#
# SECURITY: api/config.php is NEVER copied into the artifact. On the live site the
# real credentials live at <account-home>/mobi-private/config.php, i.e. outside
# public_html, where no deploy can overwrite or delete them.
param(
    [switch]$FrontendOnly,
    [switch]$Publish
)

if ($FrontendOnly -and $Publish) {
    throw "-FrontendOnly and -Publish are mutually exclusive: publishing a partial tree would drop api/ from the deployment."
}

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
    New-Item -ItemType Directory -Path (Join-Path $stage "uploads") -Force | Out-Null
    Set-Content -Path (Join-Path $stage "uploads\.gitkeep") -Value "" -Encoding Ascii

    # Credentials must never leave the machine.
    foreach ($secret in @('config.php', 'config.private.php')) {
        $leak = Join-Path $stage "api\$secret"
        if (Test-Path $leak) { Remove-Item $leak -Force }
    }

    # Refuse to build a broken web root.
    foreach ($required in @('index.html', 'api\_bootstrap.php', 'api\_config.php', '.htaccess')) {
        if (-not (Test-Path (Join-Path $stage $required))) {
            throw "Staged artifact is missing $required - aborting."
        }
    }
    if (Test-Path (Join-Path $stage "api\config.php")) {
        throw "api/config.php leaked into the staged artifact - aborting."
    }
}

if ($Publish) {
    Write-Host "==> publishing to the deploy branch" -ForegroundColor Cyan
    $origin = (& git -C $root remote get-url origin).Trim()
    if (-not $origin) { throw "No git 'origin' remote configured in $root" }

    Push-Location $stage
    try {
        & git init -q
        & git checkout -q -b deploy
        & git add -A
        & git -c user.name="deploy-bot" -c user.email="deploy-bot@localhost" `
            commit -q -m ("deploy: " + (Get-Date -Format 'yyyy-MM-ddTHH:mm:ssZ'))

        # git writes progress to stderr; do not let that look like a failure.
        $previous = $ErrorActionPreference
        $ErrorActionPreference = 'Continue'
        & git push -f $origin deploy 2>&1 | Out-Host
        $pushCode = $LASTEXITCODE
        $ErrorActionPreference = $previous
        if ($pushCode -ne 0) { throw "git push to the deploy branch failed (exit $pushCode)" }
    } finally {
        Pop-Location
    }

    Remove-Item $stage -Recurse -Force
    Write-Host "==> Done: the 'deploy' branch is up to date." -ForegroundColor Green
    Write-Host "Hostinger pulls that branch into public_html on every push - see HOSTINGER-GIT-DEPLOY.md." -ForegroundColor Yellow
    return
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
    Write-Host "It contains no credentials, so it is safe to extract over a live install." -ForegroundColor Yellow
}
