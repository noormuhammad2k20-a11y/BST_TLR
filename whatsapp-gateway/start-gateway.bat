@echo off
title Atelier WhatsApp Gateway - KEEP THIS WINDOW OPEN
cd /d "%~dp0"

if not exist "node_modules" (
  echo.
  echo  [X] Not installed yet. Run install.bat first.
  echo.
  pause
  exit /b 1
)

:run
echo.
echo  ====================================================
echo   Atelier WhatsApp Gateway
echo   Keep this window open while the shop is trading.
echo   Closing it stops automatic WhatsApp messages.
echo  ====================================================
echo.

node server.js

echo.
echo  Gateway stopped. Restarting in 5 seconds...
echo  (Press Ctrl+C twice to quit for good.)
timeout /t 5 /nobreak >nul
goto run
