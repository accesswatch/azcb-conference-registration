#!/usr/bin/env pwsh
# Build the deployable ZIP for the AZCB Conference Registration plugin.
# Usage: .\build-zip.ps1

$ErrorActionPreference = 'Stop'

$root    = Split-Path -Parent $MyInvocation.MyCommand.Definition
$source  = Join-Path $root 'azcb-conference-registration'
$distDir = Join-Path $root 'dist'
$zipPath = Join-Path $distDir 'azcb-conference-registration.zip'

if (!(Test-Path $source)) {
    Write-Error "Plugin folder not found: $source"
    exit 1
}

if (!(Test-Path $distDir)) {
    New-Item -ItemType Directory -Path $distDir | Out-Null
}

if (Test-Path $zipPath) {
    Remove-Item $zipPath -Force
}

Compress-Archive -Path $source -DestinationPath $zipPath
$info = Get-Item $zipPath
Write-Host "Created: $zipPath ($([math]::Round($info.Length / 1KB, 1)) KB)"
