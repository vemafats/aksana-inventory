$versionLine = (Get-Content "pubspec.yaml" | Select-String "^version:\s*(.+)$").Matches[0].Groups[1].Value
$parts = $versionLine -split '\+'
$version = $parts[0]
$buildNumber = $parts[1]
flutter build apk --release
$src = "build\app\outputs\flutter-apk\app-release.apk"
$dest = "build\app\outputs\flutter-apk\aksana-v${version}+${buildNumber}.apk"
Copy-Item $src $dest -Force
Write-Host "Built: aksana-v${version}+${buildNumber}.apk"
