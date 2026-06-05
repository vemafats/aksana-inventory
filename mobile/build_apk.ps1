# ──────────────────────────────────────────────
# Aksana Inventory — APK Build Script
# Auto-increments build number on every build
# Version (x.y.z) is manual — edit pubspec.yaml
# ──────────────────────────────────────────────

$pubspec = "pubspec.yaml"
$content = Get-Content $pubspec -Raw

# Parse current version
if ($content -match "version:\s*(\d+\.\d+\.\d+)\+(\d+)") {
    $version = $Matches[1]
    $oldBuild = [int]$Matches[2]
    $newBuild = $oldBuild + 1
} else {
    Write-Host "ERROR: Could not parse version from pubspec.yaml" -ForegroundColor Red
    exit 1
}

# Update pubspec.yaml with new build number
$newVersionLine = "version: ${version}+${newBuild}"
$content = $content -replace "version:\s*\d+\.\d+\.\d+\+\d+", $newVersionLine
Set-Content $pubspec $content -NoNewline
Write-Host "Version: $version+$oldBuild -> $version+$newBuild" -ForegroundColor Cyan

# Build release APK
Write-Host "Building APK..." -ForegroundColor Yellow
flutter build apk --release
if ($LASTEXITCODE -ne 0) {
    Write-Host "BUILD FAILED" -ForegroundColor Red
    exit 1
}

# Copy with versioned filename
$src = "build\app\outputs\flutter-apk\app-release.apk"
$dest = "build\app\outputs\flutter-apk\aksana-v${version}+${newBuild}.apk"
Copy-Item $src $dest -Force

Write-Host ""
Write-Host "========================================" -ForegroundColor Green
Write-Host " BUILD SUCCESS" -ForegroundColor Green
Write-Host " APK: aksana-v${version}+${newBuild}.apk" -ForegroundColor Green
Write-Host " Version: $version  Build: $newBuild" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green
