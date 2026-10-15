@echo off
title F5Gol - Inicio
cd /d "%~dp0"

echo === F5Gol ===

rem 1. MySQL de XAMPP (solo si no esta corriendo ya en el puerto 3306)
netstat -ano | findstr ":3306" | findstr "LISTENING" >nul
if errorlevel 1 (
    echo Iniciando MySQL...
    start "MySQL" /min C:\xampp\mysql\bin\mysqld.exe --defaults-file=C:\xampp\mysql\bin\my.ini --standalone
    timeout /t 5 /nobreak >nul
) else (
    echo MySQL ya esta corriendo.
)

rem 2. Back: Laravel en http://127.0.0.1:8000
echo Iniciando el back (Laravel)...
start "F5Gol Back" cmd /k "cd /d %~dp0backend && php artisan serve"

rem 3. Front: Angular en http://localhost:4200
echo Iniciando el front (Angular)...
start "F5Gol Front" cmd /k "cd /d %~dp0frontend && ng serve --open"

echo.
echo Listo. Se abrieron dos ventanas (Back y Front).
echo El navegador se abre solo cuando Angular termina de compilar.
echo Para cerrar todo, cerra esas ventanas (MySQL se apaga desde el panel de XAMPP).
timeout /t 8 >nul
