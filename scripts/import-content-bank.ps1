param(
    [string]$Source = "content-bank/import",
    [string]$Destination = "database/seed-data/readirect",
    [string]$ZipPath = "",
    [switch]$DryRun,
    [switch]$Force
)

$ErrorActionPreference = "Stop"

if ($ZipPath) {
    if (-not (Test-Path -LiteralPath $ZipPath)) {
        throw "Import ZIP not found: $ZipPath"
    }

    $workRoot = "content-bank/work/import-preview"
    if (Test-Path -LiteralPath $workRoot) {
        Remove-Item -LiteralPath $workRoot -Recurse -Force
    }
    New-Item -ItemType Directory -Force -Path $workRoot | Out-Null
    Expand-Archive -LiteralPath $ZipPath -DestinationPath $workRoot -Force
    $Source = $workRoot
}

if (-not (Test-Path -LiteralPath $Source)) {
    throw "Import source not found: $Source"
}

New-Item -ItemType Directory -Force -Path $Destination | Out-Null

$allowedExtensions = @(".csv", ".md", ".txt", ".json")
$files = Get-ChildItem -LiteralPath $Source -File -Recurse |
    Where-Object {
        -not $_.Attributes.ToString().Contains("Hidden") -and
        -not $_.Name.StartsWith(".") -and
        $_.Name -ne "README.md" -and
        $allowedExtensions -contains $_.Extension.ToLowerInvariant()
    }

if ($files.Count -eq 0) {
    Write-Host "No import files found in $Source."
    exit 0
}

Write-Host "The following files will be copied into ${Destination}:"
$files | ForEach-Object { Write-Host " - $($_.Name)" }

if ($DryRun) {
    Write-Host "Dry run complete. No files were copied."
    exit 0
}

if (-not $Force) {
    $confirmation = Read-Host "Continue and overwrite matching seed files? Type YES to continue"
    if ($confirmation -ne "YES") {
        Write-Host "Import cancelled."
        exit 0
    }
}

foreach ($file in $files) {
    $targetPath = Join-Path $Destination $file.Name
    Copy-Item -LiteralPath $file.FullName -Destination $targetPath -Force
    Write-Host "Copied $($file.FullName) -> $targetPath"
}
