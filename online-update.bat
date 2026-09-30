@echo off
setlocal
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0online-update.ps1" %*
endlocal
