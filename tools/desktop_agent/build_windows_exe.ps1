param(
  [string]$PythonExe = "python"
)

$ErrorActionPreference = "Stop"
$base = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $base

if (!(Test-Path ".venv")) {
  & $PythonExe -m venv .venv
}

& ".\.venv\Scripts\python.exe" -m pip install --upgrade pip
& ".\.venv\Scripts\python.exe" -m pip install -r requirements.txt pyinstaller

& ".\.venv\Scripts\python.exe" -m playwright install chromium

if (Test-Path "dist") { Remove-Item -Recurse -Force "dist" }
if (Test-Path "build") { Remove-Item -Recurse -Force "build" }

& ".\.venv\Scripts\pyinstaller.exe" `
  --noconfirm `
  --onefile `
  --windowed `
  --name "TikStarDesktopAgentLauncher" `
  --add-data "agent.py;." `
  desktop_agent_gui.py

Write-Host "Build done: $base\dist\TikStarDesktopAgentLauncher.exe"

