param(
    [string]$Source = "database/seed-data/readirect",
    [string]$Destination = "content-bank/export",
    [string]$ZipPath = "content-bank/export/readirect-content-bank-export.zip"
)

$ErrorActionPreference = "Stop"

$mapping = @{
    "task1_letter_pronunciation.csv" = "assessment"
    "task2a_rhyming_words.csv" = "assessment"
    "task2b_word_in_sentence.csv" = "assessment"
    "reading_passages.csv" = "assessment"
    "comprehension_questions.csv" = "assessment"
    "module1_letter_sound_activities.csv" = "modules"
    "module2_word_reading_activities.csv" = "modules"
    "module3_sentence_fluency_activities.csv" = "modules"
    "module_activity_selection_rules.csv" = "modules"
    "agent_scripts.csv" = "agents"
    "agent_commentary_templates.csv" = "agents"
    "module_placement_rules.csv" = "rules"
    "reading_classification_rules.csv" = "rules"
    "feedback_templates.csv" = "feedback"
    "module_feedback_templates.csv" = "feedback"
    "README.md" = "docs"
    "README_MODULES.md" = "docs"
}

foreach ($folder in @("assessment", "modules", "agents", "rules", "feedback", "prompts", "docs")) {
    New-Item -ItemType Directory -Force -Path (Join-Path $Destination $folder) | Out-Null
}

$existingTargets = foreach ($entry in $mapping.GetEnumerator()) {
    $targetPath = Join-Path (Join-Path $Destination $entry.Value) $entry.Key
    if (Test-Path -LiteralPath $targetPath) {
        $targetPath
    }
}

if ($existingTargets.Count -gt 0) {
    Write-Host "The export will overwrite these existing files:"
    $existingTargets | ForEach-Object { Write-Host " - $_" }
    $confirmation = Read-Host "Continue? Type YES to overwrite"

    if ($confirmation -ne "YES") {
        Write-Host "Export cancelled."
        exit 0
    }
}

foreach ($entry in $mapping.GetEnumerator()) {
    $sourcePath = Join-Path $Source $entry.Key

    if (-not (Test-Path -LiteralPath $sourcePath)) {
        Write-Host "Skipped missing source: $sourcePath"
        continue
    }

    $targetPath = Join-Path (Join-Path $Destination $entry.Value) $entry.Key
    Copy-Item -LiteralPath $sourcePath -Destination $targetPath -Force
    Write-Host "Copied $sourcePath -> $targetPath"
}

$zipParent = Split-Path -Parent $ZipPath
New-Item -ItemType Directory -Force -Path $zipParent | Out-Null

if (Test-Path -LiteralPath $ZipPath) {
    $confirmation = Read-Host "Overwrite existing ZIP $ZipPath? Type YES to continue"
    if ($confirmation -ne "YES") {
        Write-Host "ZIP export skipped."
        exit 0
    }

    Remove-Item -LiteralPath $ZipPath -Force
}

$zipSources = @("assessment", "modules", "agents", "rules", "feedback", "prompts", "docs", "README.md") |
    ForEach-Object { Join-Path $Destination $_ } |
    Where-Object { Test-Path -LiteralPath $_ }

Compress-Archive -LiteralPath $zipSources -DestinationPath $ZipPath
Write-Host "Created content bank ZIP: $ZipPath"
