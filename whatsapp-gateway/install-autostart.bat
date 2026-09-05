@echo off
title Atelier WhatsApp Gateway - Auto Start Setup
cd /d "%~dp0"

net session >nul 2>&1
if errorlevel 1 (
  echo.
  echo  [X] Administrator rights are needed.
  echo.
  echo      Right-click this file and choose
  echo      "Run as administrator", then try again.
  echo.
  pause
  exit /b 1
)

echo.
echo  ====================================================
echo   Atelier WhatsApp Gateway - Auto Start Setup
echo  ====================================================
echo.
echo  This makes the gateway start on its own with Windows,
echo  with no window on screen, and restart itself if it stops.
echo.

if not exist "node_modules" (
  echo  [X] Not installed yet. Run install.bat first.
  echo.
  pause
  exit /b 1
)

set TASK=AtelierWhatsAppGateway

schtasks /query /tn "%TASK%" >nul 2>&1
if not errorlevel 1 (
  echo  Removing the previous task...
  schtasks /delete /tn "%TASK%" /f >nul 2>&1
)

echo  Creating the scheduled task...
echo.

schtasks /create /tn "%TASK%" ^
  /tr "wscript.exe \"%~dp0run-hidden.vbs\"" ^
  /sc onlogon ^
  /rl highest ^
  /f >nul

if errorlevel 1 (
  echo  [X] Could not create the task.
  echo.
  pause
  exit /b 1
)

REM Keep retrying if it ever stops, and never kill it for running too long.
schtasks /change /tn "%TASK%" /ri 5 /du 9999:59 >nul 2>&1

echo  [OK] Done.
echo.
echo  The gateway will now start automatically every time
echo  you sign in to Windows. No window will appear.
echo.
echo  Starting it now so you do not have to restart...
start "" wscript.exe "%~dp0run-hidden.vbs"

timeout /t 4 /nobreak >nul

echo.
echo  ====================================================
echo   Check it worked - open this in your browser:
echo   http://localhost:3001/qr
echo  ====================================================
echo.
echo  To undo this later, run: uninstall-autostart.bat
echo.
pause
