param(
    [string]$VenvPath = ".venv"
)

$ErrorActionPreference = "Stop"

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $scriptDir

$venvFullPath = Join-Path $scriptDir $VenvPath
if (-not (Test-Path $venvFullPath)) {
    Write-Host "Creating Python virtual environment at $venvFullPath"
    $pyLauncherAvailable = $null -ne (Get-Command py -ErrorAction SilentlyContinue)
    if ($pyLauncherAvailable) {
        try {
            py -3.12 -m venv $venvFullPath
        } catch {
            Write-Host "Python 3.12 not available via py launcher, falling back to default python"
            python -m venv $venvFullPath
        }
    } else {
        python -m venv $venvFullPath
    }
}

$pythonExe = Join-Path $venvFullPath "Scripts\python.exe"
if (-not (Test-Path $pythonExe)) {
    throw "Python executable not found in virtual environment: $pythonExe"
}

Write-Host "Upgrading pip"
& $pythonExe -m pip install --upgrade pip

Write-Host "Ensuring setuptools and wheel are installed"
& $pythonExe -m pip install --upgrade setuptools wheel

Write-Host "Installing OCR dependencies"
& $pythonExe -m pip install -r (Join-Path $scriptDir "requirements.txt")

Write-Host "Done. To use this environment in PowerShell:"
Write-Host "  & '$venvFullPath\Scripts\Activate.ps1'"
