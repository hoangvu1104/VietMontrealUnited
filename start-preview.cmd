@echo off
cd /d "%~dp0"
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0start-preview.ps1"
if errorlevel 1 (
  echo Could not start preview. Please send the error above.
  pause
  exit /b 1
)
start "" "http://127.0.0.1:8780/"
