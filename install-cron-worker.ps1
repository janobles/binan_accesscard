# Shim to run the real install script from the repository root
$target = Join-Path $PSScriptRoot 'scripts\install-cron-worker.ps1'
if (-not (Test-Path $target)) {
    Write-Error "Script not found: $target"
    exit 1
}

& $target @args
exit $LASTEXITCODE
