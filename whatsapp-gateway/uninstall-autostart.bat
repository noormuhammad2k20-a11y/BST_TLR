@echo off
title Atelier WhatsApp Gateway - Remove Auto Start
cd /d "%~dp0"

net session >nul 2>&1
if errorlevel 1 (
  echo.
  echo  [X] Administrator rights are needed.
  echo      Right-click this file and choose "Run as administrator".
  echo.
  pause
  exit /b 1
)

set TASK=AtelierWhatsAppGateway

echo.
echo  Removing the scheduled task...
schtasks /delete /tn "%TASK%" /f >nul 2>&1

echo  Stopping the gateway...
for /f "tokens=5" %%a in ('netstat -ano ^| findstr ":3001" ^| findstr "LISTENING"') do taskkill /pid %%a /f >nul 2>&1

echo.
echo  [OK] Auto start removed.
echo.
echo  The gateway will no longer start with Windows.
echo  Run start-gateway.bat by hand when you need it.
echo.
pause
