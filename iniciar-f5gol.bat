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

rem Programador de Laravel: cada 5 minutos avisa los partidos de matchmaking que empezaron sin completarse
start "F5Gol Programador" /min cmd /k "cd /d %~dp0backend && php artisan schedule:work"

rem 3. n8n en http://localhost:5678 (mail de bienvenida). Solo si esta instalado y no esta corriendo
where n8n >nul 2>nul
if errorlevel 1 (
    echo n8n no esta instalado: el registro funciona igual, pero sin mail de bienvenida. Ver docs\n8n\README.md
) else (
    netstat -ano | findstr ":5678" | findstr "LISTENING" >nul
    if errorlevel 1 (
        echo Iniciando n8n...
        start "F5Gol n8n" /min cmd /k "n8n start"
    ) else (
        echo n8n ya esta corriendo.
    )
)

rem 4. Front: Angular en http://localhost:4200
echo Iniciando el front (Angular)...
start "F5Gol Front" cmd /k "cd /d %~dp0frontend && ng serve --open"

echo.
echo Listo. Se abrieron las ventanas del Back, el Front y n8n (si esta instalado).
echo El navegador se abre solo cuando Angular termina de compilar.
echo Para cerrar todo, cerra esas ventanas (MySQL se apaga desde el panel de XAMPP).
timeout /t 8 >nul
