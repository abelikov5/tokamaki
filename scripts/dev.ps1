$ErrorActionPreference = 'Stop'

$ProjectRoot = Split-Path -Parent $PSScriptRoot
$WebRoot = Join-Path $ProjectRoot 'apps/web'
$GeneratorRoot = Join-Path $ProjectRoot 'services/generator'
$PythonExecutable = Join-Path $GeneratorRoot '.venv/Scripts/python.exe'
$PhpExecutable = (Get-Command php -ErrorAction Stop).Source

if (-not (Test-Path $PythonExecutable)) {
    throw 'Python environment not found. Run .\scripts\setup-local.ps1 first.'
}
if (-not (Test-Path (Join-Path $WebRoot 'vendor/autoload.php'))) {
    throw 'Laravel dependencies not found. Run .\scripts\setup-local.ps1 first.'
}

$GeneratorProcess = Start-Process -FilePath $PythonExecutable `
    -ArgumentList '-m', 'uvicorn', 'app.main:app', '--host', '127.0.0.1', '--port', '8001' `
    -WorkingDirectory $GeneratorRoot -WindowStyle Hidden -PassThru
$WebProcess = Start-Process -FilePath $PhpExecutable `
    -ArgumentList '-S', '127.0.0.1:8000', '-t', 'public' `
    -WorkingDirectory $WebRoot -WindowStyle Hidden -PassThru

$Processes = @($GeneratorProcess, $WebProcess)

Write-Host 'Service started: http://127.0.0.1:8000' -ForegroundColor Green
Write-Host 'Press Ctrl+C to stop.' -ForegroundColor DarkGray

try {
    while ($true) {
        foreach ($Process in $Processes) { $Process.Refresh() }
        if ($GeneratorProcess.HasExited) { throw 'Python generator stopped unexpectedly.' }
        if ($WebProcess.HasExited) { throw 'Laravel web server stopped unexpectedly.' }
        Start-Sleep -Seconds 1
    }
} finally {
    foreach ($Process in $Processes) {
        $Process.Refresh()
        if (-not $Process.HasExited) {
            & taskkill.exe /PID $Process.Id /T /F 2>$null | Out-Null
        }
    }
}
