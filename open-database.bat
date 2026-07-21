@echo off
setlocal

set "SITE_URL=http://localhost/Auto%%201/admin/database.php"
set "LOGIN_URL=http://localhost/Auto%%201/admin/login.php"

echo.
echo InnovaAuto - База данных в браузере
echo ====================================
echo.
echo 1) Сначала войдите в админку (если еще не вошли):
echo    %LOGIN_URL%
echo.
echo    Email:  admin@innovaauto.local
echo    Логин:  admin
echo    Пароль: Admin123!
echo    Роль:   super_admin (только эта роль видит базу)
echo.
echo 2) Откроется страница базы данных:
echo    %SITE_URL%
echo.

start "" "%LOGIN_URL%"
timeout /t 1 /nobreak >nul
start "" "%SITE_URL%"

exit /b 0
