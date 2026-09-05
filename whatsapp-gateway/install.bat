@echo off
title Atelier WhatsApp Gateway - Install
cd /d "%~dp0"

echo.
echo  ====================================================
echo   Atelier WhatsApp Gateway - Install
echo  ====================================================
echo.

where node >nul 2>nul
if errorlevel 1 (
  echo  [X] Node.js is not installed.
  echo.
  echo      1. Open https://nodejs.org
  echo      2. Download the LTS version
  echo      3. Install it, then run this file again
  echo.
  pause
  exit /b 1
)

echo  [OK] Node.js found:
node -v
echo.
echo  Downloading the required files. This takes 1-2 minutes...
echo.

call npm install
if errorlevel 1 (
  echo.
  echo  [X] Install failed. Check your internet connection and try again.
  echo.
  pause
  exit /b 1
)

echo.
echo  ====================================================
echo   Done. Now run: start-gateway.bat
echo  ====================================================
echo.
pause
