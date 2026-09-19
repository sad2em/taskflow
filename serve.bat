@echo off
setlocal
cd /d "%~dp0taskflow"
where php >nul 2>nul || (echo PHP was not found in PATH.& exit /b 1)
echo Starting TaskFlow on http://127.0.0.1:8099
php -S 127.0.0.1:8099 -t .
