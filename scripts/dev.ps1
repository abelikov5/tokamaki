$ErrorActionPreference = 'Stop'

$ProjectRoot = Split-Path -Parent $PSScriptRoot
$WebRoot = Join-Path $ProjectRoot 'apps/web'
$GeneratorRoot = Join-Path $ProjectRoot 'services/generator'
$PythonExecutable = Join-Path $GeneratorRoot '.venv/Scripts/python.exe'
$PhpExecutable = (Get-Command php -ErrorAction Stop).Source
$LogRoot = Join-Path ([System.IO.Path]::GetTempPath()) "tokamaki-dev-$PID"

if (-not (Test-Path $PythonExecutable)) {
    throw 'Python environment not found. Run .\scripts\setup-local.ps1 first.'
}
if (-not (Test-Path (Join-Path $WebRoot 'vendor/autoload.php'))) {
    throw 'Laravel dependencies not found. Run .\scripts\setup-local.ps1 first.'
}

New-Item -ItemType Directory -Path $LogRoot -Force | Out-Null
$GeneratorOutputLog = Join-Path $LogRoot 'generator-output.log'
$GeneratorErrorLog = Join-Path $LogRoot 'generator-error.log'
$WebOutputLog = Join-Path $LogRoot 'web-output.log'
$WebErrorLog = Join-Path $LogRoot 'web-error.log'

$GeneratorProcess = Start-Process -FilePath $PythonExecutable `
    -ArgumentList '-m', 'uvicorn', 'app.main:app', '--host', '127.0.0.1', '--port', '8001' `
    -WorkingDirectory $GeneratorRoot -WindowStyle Hidden -PassThru `
    -RedirectStandardOutput $GeneratorOutputLog -RedirectStandardError $GeneratorErrorLog
$WebProcess = Start-Process -FilePath $PhpExecutable `
    -ArgumentList '-S', '127.0.0.1:8000', '-t', 'public' `
    -WorkingDirectory $WebRoot -WindowStyle Hidden -PassThru `
    -RedirectStandardOutput $WebOutputLog -RedirectStandardError $WebErrorLog

$Processes = @($GeneratorProcess, $WebProcess)
$LogOffsets = @{}

function Show-NewLogLines {
    param([string]$Path, [string]$Prefix, [ConsoleColor]$Color)
    if (-not (Test-Path $Path)) { return }
    $Lines = @(Get-Content -LiteralPath $Path -Encoding UTF8)
    $Offset = if ($LogOffsets.ContainsKey($Path)) { $LogOffsets[$Path] } else { 0 }
    if ($Lines.Count -gt $Offset) {
        foreach ($Line in $Lines[$Offset..($Lines.Count - 1)]) {
            if ($Line) { Write-Host "[$Prefix] $Line" -ForegroundColor $Color }
        }
    }
    $LogOffsets[$Path] = $Lines.Count
}

Write-Host 'Service started: http://127.0.0.1:8000' -ForegroundColor Green
Write-Host "Live logs: $LogRoot" -ForegroundColor DarkGray
Write-Host 'Press Ctrl+C to stop.' -ForegroundColor DarkGray

try {
    while ($true) {
        foreach ($Process in $Processes) { $Process.Refresh() }
        Show-NewLogLines -Path $GeneratorOutputLog -Prefix 'generator' -Color Cyan
        Show-NewLogLines -Path $GeneratorErrorLog -Prefix 'generator' -Color Cyan
        Show-NewLogLines -Path $WebOutputLog -Prefix 'web' -Color DarkGray
        Show-NewLogLines -Path $WebErrorLog -Prefix 'web' -Color DarkGray
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
