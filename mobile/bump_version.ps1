# ──────────────────────────────────────────────
# Bump version (major.minor.patch) manually
# Usage: .\bump_version.ps1 1.4.0
# ──────────────────────────────────────────────

param(
    [Parameter(Mandatory=$true)]
    [string]$NewVersion
)

if ($NewVersion -notmatch "^\d+\.\d+\.\d+$") {
    Write-Host "ERROR: Version must be in format x.y.z (e.g., 1.4.0)" -ForegroundColor Red
    exit 1
}

$pubspec = "pubspec.yaml"
$content = Get-Content $pubspec -Raw

if ($content -match "version:\s*\d+\.\d+\.\d+\+(\d+)") {
    $buildNumber = $Matches[1]
} else {
    Write-Host "ERROR: Could not parse version from pubspec.yaml" -ForegroundColor Red
    exit 1
}

$newVersionLine = "version: ${NewVersion}+${buildNumber}"
$content = $content -replace "version:\s*\d+\.\d+\.\d+\+\d+", $newVersionLine
Set-Content $pubspec $content -NoNewline

Write-Host "Version bumped to: ${NewVersion}+${buildNumber}" -ForegroundColor Green
Write-Host "Run .\build_apk.ps1 to build with this version" -ForegroundColor Cyan
