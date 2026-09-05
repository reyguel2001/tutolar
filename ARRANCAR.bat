@echo off
rem ============================================================
rem  TUTOLAR - arranque
rem
rem  Deja el proyecto listo y lo levanta. Se puede ejecutar las
rem  veces que haga falta: si algo ya esta hecho, se lo salta.
rem ============================================================
setlocal enabledelayedexpansion
cd /d "%~dp0"

echo.
echo   TUTOLAR
echo   ============================================
echo.

rem ---------- 1. Localizar PHP ----------
set "PHP="
where php >nul 2>nul && set "PHP=php"
if not defined PHP if exist "C:\xampp\php\php.exe" set "PHP=C:\xampp\php\php.exe"

if not defined PHP (
    echo   [X] No encuentro PHP.
    echo.
    echo       Se busca primero en el PATH y despues en
    echo       C:\xampp\php\php.exe, que es donde lo pone XAMPP.
    echo       Si tienes XAMPP en otra unidad, edita este archivo.
    echo.
    pause
    exit /b 1
)
echo   [OK] PHP encontrado: %PHP%

rem ---------- 2. Localizar Composer ----------
set "COMPOSER="
where composer >nul 2>nul && set "COMPOSER=composer"
if not defined COMPOSER if exist "composer.phar" set "COMPOSER=%PHP% composer.phar"

rem ---------- 3. Dependencias ----------
if exist "vendor\autoload.php" goto :autoload_ok

echo   [ ] Falta vendor\. Instalando dependencias...
if not defined COMPOSER (
    echo.
    echo   [X] No encuentro Composer y hace falta para crear vendor\.
    echo.
    echo       Dos salidas:
    echo         a^) instala Composer desde https://getcomposer.org
    echo         b^) copia la carpeta vendor\ entera desde
    echo            C:\xampp\htdocs\SORA\TUTOLAR y vuelve a ejecutar
    echo            este archivo
    echo.
    pause
    exit /b 1
)
%COMPOSER% install --no-interaction
if errorlevel 1 (
    echo.
    echo   [X] composer install ha fallado. Mira el mensaje de arriba.
    echo.
    pause
    exit /b 1
)
echo   [OK] Dependencias instaladas
goto :cachess

:autoload_ok
echo   [OK] vendor\ presente

rem Si el vendor\ viene copiado de la carpeta antigua, su autoload
rem todavia busca las clases en backend\app y no encontraria ninguna.
findstr /c:"backend/app" "vendor\composer\autoload_psr4.php" >nul 2>nul
if not errorlevel 1 (
    echo   [ ] El autoload apunta a la estructura antigua. Regenerando...
    if not defined COMPOSER (
        echo.
        echo   [X] Hace falta Composer para regenerarlo:
        echo       composer dump-autoload
        echo.
        pause
        exit /b 1
    )
    %COMPOSER% dump-autoload
    echo   [OK] Autoload regenerado
)

:cachess
rem ---------- 4. Limpiar cache de configuracion y vistas ----------
%PHP% artisan config:clear >nul 2>nul
%PHP% artisan view:clear   >nul 2>nul
echo   [OK] Cache limpia

rem ---------- 5. Migraciones pendientes ----------
%PHP% artisan migrate --force
if errorlevel 1 (
    echo.
    echo   [!] La migracion ha dado error. La aplicacion puede arrancar
    echo       igual si la base de datos ya estaba al dia.
    echo.
)

echo.
echo   ============================================
echo   Como quieres arrancar?
echo.
echo     [1] Servidor de Laravel   (no necesita XAMPP)
echo     [2] Apache de XAMPP       (arrancalo tu en el panel)
echo.
set "MODO="
set /p MODO=  Elige 1 o 2 y pulsa Enter:

if "%MODO%"=="2" goto :apache

rem ---------- Servidor propio de Laravel ----------
rem APP_URL se define aqui, en el entorno del proceso, y no en el .env:
rem Laravel da prioridad a las variables que ya existen en el entorno,
rem asi que esto manda sin tocar el archivo. Al cerrar esta ventana
rem desaparece, y el .env sigue valiendo para Apache.
set APP_URL=http://localhost:8000

echo.
echo   Abre esto en el navegador:
echo.
echo       http://localhost:8000
echo.
echo   Entra con  direccion@iescervantes.edu.es
echo   contrasena tutolar2026
echo.
echo   Para parar el servidor: Ctrl+C en esta ventana.
echo   ============================================
echo.
%PHP% artisan serve
goto :fin

:apache
echo.
echo   Arranca Apache en el panel de XAMPP y abre:
echo.
echo       http://localhost/SORA/tutolarV3/public
echo.
echo   Entra con  direccion@iescervantes.edu.es
echo   contrasena tutolar2026
echo   ============================================
echo.

:fin
pause
endlocal
