@echo off
rem Runs the PowerShell installer without changing the machine's execution policy.
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0instalar-entorno-windows.ps1" %*
pause
