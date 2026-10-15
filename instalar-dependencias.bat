@echo off
title F5Gol - Instalar dependencias
cd /d "%~dp0"

echo === F5Gol: instalar dependencias ===
echo.

rem Verificar que esten las herramientas necesarias
set FALTA=
where php >nul 2>nul || set FALTA=%FALTA% PHP
where composer >nul 2>nul || set FALTA=%FALTA% Composer
where node >nul 2>nul || set FALTA=%FALTA% Node.js
where npm >nul 2>nul || set FALTA=%FALTA% npm
if defined FALTA (
    echo ERROR: no se encontro:%FALTA%
    echo Instalalos y volve a ejecutar este archivo.
    pause
    exit /b 1
)

rem 1. Back (Laravel)
echo [1/3] Instalando dependencias del back (composer install)...
pushd backend
call composer install
if errorlevel 1 goto error

if not exist .env (
    echo Creando backend\.env a partir de .env.example...
    copy .env.example .env >nul
    call php artisan key:generate
)
popd

rem 2. Front (Angular)
echo.
echo [2/3] Instalando dependencias del front (npm install)...
pushd frontend
call npm install
if errorlevel 1 goto error
popd

rem 3. Angular CLI global (para poder usar "ng serve")
echo.
where ng >nul 2>nul
if errorlevel 1 (
    echo [3/3] Instalando Angular CLI 22 global...
    call npm install -g @angular/cli@22
    if errorlevel 1 goto error
) else (
    echo [3/3] Angular CLI ya esta instalado.
)

echo.
echo Listo. Dependencias instaladas.
echo.
echo Si es la primera vez:
echo   1. Crea la base "f5gol" en phpMyAdmin.
echo   2. Revisa los datos de la base en backend\.env
echo   3. En la carpeta backend corre: php artisan migrate --seed
echo.
echo Despues levanta todo con iniciar-f5gol.bat
pause
exit /b 0

:error
popd
echo.
echo ERROR: fallo la instalacion. Revisa los mensajes de arriba.
pause
exit /b 1
