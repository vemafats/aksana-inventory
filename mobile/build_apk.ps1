# Read version from pubspec.yaml
$version = (Select-String -Path "pubspec.yaml" -Pattern "^version:\s*(.+)\+").Matches[0].Groups[1].Value
$buildNumber = (Select-String -Path "pubspec.yaml" -Pattern "^version:\s*.+\+(\d+)").Matches[0].Groups[1].Value
flutter build apk --release
$src = "build\app\outputs\flutter-apk\app-release.apk"
$dest = "build\app\outputs\flutter-apk\aksana-v${version}+${buildNumber}.apk"
Copy-Item $src $dest -Force
Write-Host "Built: aksana-v${version}+${buildNumber}.apk"
