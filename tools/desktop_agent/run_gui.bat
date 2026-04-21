@echo off
setlocal
cd /d %~dp0

if not exist .venv (
  python -m venv .venv
)

call .venv\Scripts\activate.bat
python -c "import requests,playwright" >nul 2>nul
if errorlevel 1 (
  echo Installing desktop agent dependencies...
  python -m pip install --upgrade pip
  python -m pip install -r requirements.txt
  python -m playwright install chromium
)

python desktop_agent_gui.py

endlocal
