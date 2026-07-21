@echo off
setlocal

set "XAMPP_DIR=C:\xampp"
set "SITE_URL=http://localhost/Auto%%201/"

if not exist "%XAMPP_DIR%\apache\bin\httpd.exe" (
  echo [ERROR] Apache not found in %XAMPP_DIR%
  pause
  exit /b 1
)

echo Checking Apache status...
tasklist | findstr /I "httpd.exe" >nul
if errorlevel 1 (
  echo Starting Apache...
  if exist "%XAMPP_DIR%\apache_start.bat" (
    call "%XAMPP_DIR%\apache_start.bat" >nul 2>&1
  ) else (
    start "" "%XAMPP_DIR%\apache\bin\httpd.exe"
  )
  timeout /t 2 /nobreak >nul
) else (
  echo Apache is already running.
)

echo Opening website...
start "" "%SITE_URL%"

exit /b 0
