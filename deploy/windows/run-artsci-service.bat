@echo off
REM ==============================================================================
REM ARTSCI Attendance - Manual Windows Service Launcher
REM Use this script to start PHP FastCGI and Caddy in the foreground for testing
REM ==============================================================================

echo Starting ARTSCI Attendance Services...
echo.

set PHP_FCGI_MAX_REQUESTS=1000

REM 1. Start PHP FastCGI on 127.0.0.1:9000
echo [1/2] Starting PHP-CGI FastCGI on 127.0.0.1:9000...
start "ARTSCI-PHP-CGI" /MIN php-cgi.exe -b 127.0.0.1:9000

REM 2. Start Caddy Web Server
echo [2/2] Starting Caddy Server...
start "ARTSCI-Caddy" caddy.exe run --config C:\artsci-attendance\deploy\windows\Caddyfile

echo.
echo Services launched!
echo Access the application locally at: https://attendance.artsci.ng
pause
