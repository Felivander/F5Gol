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
echo [1/4] Instalando dependencias del back (composer install)...
pushd backend
call composer install
if errorlevel 1 (
    echo Reintentando con --ignore-platform-req=php...
    call composer install --ignore-platform-req=php
    if errorlevel 1 goto error
)

if not exist .env (
    echo Creando backend\.env a partir de .env.example...
    copy .env.example .env >nul
    call php artisan key:generate
)

rem Enlace public/storage para que se vean las fotos de las canchas
if not exist public\storage (
    call php artisan storage:link
)
popd

rem 2. Base de datos: tablas y datos de prueba (necesita MySQL andando y la base f5gol creada)
echo.
echo [2/4] Creando las tablas de la base (php artisan migrate --seed)...
pushd backend
call php artisan migrate --seed --force
if errorlevel 1 (
    set FALLO_BASE=1
    echo AVISO: no se pudo migrar la base. Revisa que MySQL este corriendo,
    echo que exista la base "f5gol" y los datos de backend\.env
)
popd

rem 3. Front (Angular)
echo.
echo [3/4] Instalando dependencias del front (npm install)...
pushd frontend
call npm install
if errorlevel 1 goto error
popd

rem 4. Angular CLI global (para poder usar "ng serve")
echo.
where ng >nul 2>nul
if errorlevel 1 (
    echo [4/4] Instalando Angular CLI 22 global...
    call npm install -g @angular/cli@22
    if errorlevel 1 goto error
) else (
    echo [4/4] Angular CLI ya esta instalado.
)

echo.
echo Listo. Dependencias instaladas.
if defined FALLO_BASE (
    echo.
    echo Falta la base de datos:
    echo   1. Crea la base "f5gol" en phpMyAdmin.
    echo   2. Revisa los datos de la base en backend\.env
    echo   3. Volve a ejecutar este archivo, o en la carpeta backend corre: php artisan migrate --seed
)
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
