@echo off
title Atelier WhatsApp Gateway - Status
cd /d "%~dp0"

echo.
echo  ====================================================
echo   Atelier WhatsApp Gateway - Status
echo  ====================================================
echo.

netstat -ano | findstr ":3001" | findstr "LISTENING" >nul 2>&1
if errorlevel 1 (
  echo  [X] The gateway is NOT running.
  echo.
  echo      Start it with: start-gateway.bat
  echo      Or set it to start by itself: install-autostart.bat
) else (
  echo  [OK] The gateway is running.
  echo.
  echo       Open http://localhost:3001/qr to check the link status.
)

echo.
schtasks /query /tn "AtelierWhatsAppGateway" >nul 2>&1
if errorlevel 1 (
  echo  [ ] Auto start is OFF - you must start it by hand each time.
  echo      Turn it on with: install-autostart.bat  (run as administrator)
) else (
  echo  [OK] Auto start is ON - it will start with Windows.
)

echo.
echo  ---- Last 15 log lines ----
echo.
if exist gateway.log (
  powershell -NoProfile -Command "Get-Content gateway.log -Tail 15"
) else (
  echo  No log yet.
)

echo.
pause
