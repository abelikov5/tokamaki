$ErrorActionPreference = 'Stop'

$ProjectRoot = Split-Path -Parent $PSScriptRoot
$WebRoot = Join-Path $ProjectRoot 'apps/web'
$GeneratorRoot = Join-Path $ProjectRoot 'services/generator'
$PythonExecutable = Join-Path $GeneratorRoot '.venv/Scripts/python.exe'

Push-Location $WebRoot
try {
    composer install --no-interaction
    if (-not (Test-Path '.env')) { Copy-Item '.env.example' '.env' }
    if (-not (Test-Path 'database/database.sqlite')) { New-Item -ItemType File 'database/database.sqlite' | Out-Null }
    if (-not (Select-String -Path '.env' -Pattern '^APP_KEY=base64:' -Quiet)) {
        php artisan key:generate --force
    }
    php artisan migrate --force
} finally {
    Pop-Location
}

if (-not (Test-Path $PythonExecutable)) {
    python -m venv (Join-Path $GeneratorRoot '.venv')
}
& $PythonExecutable -m pip install -r (Join-Path $GeneratorRoot 'requirements.txt')

Write-Host 'Setup complete. Run .\scripts\dev.ps1' -ForegroundColor Green
