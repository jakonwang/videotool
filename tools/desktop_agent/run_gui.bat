@echo off
setlocal
cd /d %~dp0

if not exist .venv (
  echo [INFO] Creating virtual environment...
  python -m venv .venv
)

call .venv\Scripts\activate.bat
python -c "import playwright" >nul 2>nul
if errorlevel 1 (
  echo [INFO] Installing desktop agent dependencies...
  python -m pip install --upgrade pip
  python -m pip install -r requirements.txt
  python -m playwright install chromium
)

python desktop_agent_gui.py

endlocal
