param(
    [string]$Source = "content-bank/import",
    [string]$Destination = "database/seed-data/readirect"
)

$ErrorActionPreference = "Stop"

if (-not (Test-Path -LiteralPath $Source)) {
    throw "Import folder not found: $Source"
}

New-Item -ItemType Directory -Force -Path $Destination | Out-Null

$files = Get-ChildItem -LiteralPath $Source -File |
    Where-Object { -not $_.Attributes.ToString().Contains("Hidden") -and -not $_.Name.StartsWith(".") -and $_.Name -ne "README.md" }

if ($files.Count -eq 0) {
    Write-Host "No import files found in $Source."
    exit 0
}

Write-Host "The following files will be copied into ${Destination}:"
$files | ForEach-Object { Write-Host " - $($_.Name)" }

$confirmation = Read-Host "Continue and overwrite matching files? Type YES to continue"
if ($confirmation -ne "YES") {
    Write-Host "Import cancelled."
    exit 0
}

foreach ($file in $files) {
    $targetPath = Join-Path $Destination $file.Name
    Copy-Item -LiteralPath $file.FullName -Destination $targetPath -Force
    Write-Host "Copied $($file.FullName) -> $targetPath"
}
