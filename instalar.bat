@echo off
rem Sets up the three parts of ArchivumParfums on Windows: the API, the Web client and the database.
rem It can run as often as needed: every step that is already done is skipped,
rem so a second run never clones again, replaces .env or duplicates data.
setlocal EnableExtensions DisableDelayedExpansion
chcp 65001 >nul
title ArchivumParfums: instalación

set "API_REPO=https://github.com/JAguilar4k/ArchivumParfums-Hexagonal.git"
set "WEB_REPO=https://github.com/JAguilar4k/ArchivumParfums-Web.git"
set "HERE=%~dp0"
set "HERE=%HERE:~0,-1%"

rem From a clone of the API, the Web client is cloned next to it; downloaded alone, everything goes in this folder.
if exist "%HERE%\archivum-api-hexagonal\artisan" (
    set "API_DIR=%HERE%"
    for %%I in ("%HERE%\..") do set "WORKSPACE=%%~fI"
) else (
    set "WORKSPACE=%HERE%"
    set "API_DIR=%HERE%\ArchivumParfums-Hexagonal"
)
set "LARAVEL=%API_DIR%\archivum-api-hexagonal"
set "WEB_DIR=%WORKSPACE%\ArchivumParfums-Web"
set "ENV_FILE=%LARAVEL%\.env"

echo.
echo  ArchivumParfums: instalación del entorno local
echo  API y base de datos: %API_DIR%
echo  Cliente web:         %WEB_DIR%
echo  Configuración:       %ENV_FILE%
echo.

rem ---------------------------------------------------------------- 1. Tools
echo [1/7] Herramientas
set "MISSING="
call :require git "Git"
call :require php "PHP 8.4"
call :require composer "Composer 2"
call :require node "Node.js 26"
call :require npm "npm"
call :require psql "PostgreSQL 18"
call :require pg_isready "PostgreSQL 18"
if defined MISSING goto :missing_tools

call php -r "exit(version_compare(PHP_VERSION, '8.4.0', '>=') ? 0 : 1);"
if errorlevel 1 (
    echo   Falta PHP 8.4 o superior. Revise con: php -v
    goto :failed
)
rem Extensions the API needs at run time; Composer only checks the ones its packages declare.
call php -r "$missing = array_values(array_filter(['pdo_pgsql', 'intl', 'mbstring', 'openssl', 'fileinfo', 'curl', 'ctype', 'tokenizer'], fn (string $name): bool => ! extension_loaded($name))); if ($missing !== []) { echo '  Faltan extensiones de PHP: ', implode(', ', $missing), '.', PHP_EOL, '  Actívelas en ', php_ini_loaded_file() ?: 'php.ini', ' quitando el ; de cada línea extension=, y abra una consola nueva.', PHP_EOL; exit(1); }"
if errorlevel 1 goto :failed
node -e "process.exit(Number(process.versions.node.split('.')[0]) >= 26 ? 0 : 1)"
if errorlevel 1 (
    echo   Falta Node.js 26 o superior. Revise con: node -v
    goto :failed
)
rem psql prints "psql (PostgreSQL) 18.1": the third word is the version and its first part the major version.
set "PG_MAJOR=0"
for /f "tokens=3" %%V in ('psql --version') do for /f "delims=." %%M in ("%%V") do set "PG_MAJOR=%%M"
if %PG_MAJOR% LSS 18 (
    echo   Falta PostgreSQL 18: psql es de la versión %PG_MAJOR%. Instale PostgreSQL 18 y ponga su carpeta bin en el PATH.
    goto :failed
)
echo   [listo] Git, PHP 8.4 con sus extensiones, Composer, Node.js 26, npm y PostgreSQL %PG_MAJOR%.

rem ---------------------------------------------------------------- 2. Repositories
echo.
echo [2/7] Repositorios: API, base de datos y cliente web
if exist "%API_DIR%\.git" (
    echo   [ya estaba] API en %API_DIR%
) else (
    git clone --recurse-submodules "%API_REPO%" "%API_DIR%"
    if errorlevel 1 goto :failed
    echo   [hecho] API clonada.
)
if exist "%LARAVEL%\database\migrations\roles.sql" (
    echo   [listo] Base de datos: migraciones y roles en archivum-api-hexagonal\database\migrations
) else (
    git -C "%API_DIR%" submodule update --init --recursive
    if errorlevel 1 goto :failed
    echo   [hecho] Base de datos descargada: submódulo ArchivumParfums-Database.
)
if exist "%WEB_DIR%\.git" (
    echo   [ya estaba] Cliente web en %WEB_DIR%
) else (
    git clone "%WEB_REPO%" "%WEB_DIR%"
    if errorlevel 1 goto :failed
    echo   [hecho] Cliente web clonado.
)
for %%I in ("%API_DIR%") do if /I not "%%~nxI"=="ArchivumParfums-Hexagonal" (
    echo   Aviso: el cliente web busca el .env en ..\ArchivumParfums-Hexagonal. Renombre la carpeta %%~nxI para que lo encuentre.
)

rem ---------------------------------------------------------------- 3. One .env
echo.
echo [3/7] Configuración: un solo .env para la API, la base de datos y el cliente web
if exist "%ENV_FILE%" (
    echo   [ya estaba] Se conserva %ENV_FILE%
) else (
    copy "%LARAVEL%\.env.example" "%ENV_FILE%" >nul
    if errorlevel 1 goto :failed
    echo   [hecho] Se creó .env desde .env.example. Revise DB_HOST y DB_PORT si PostgreSQL no usa 127.0.0.1:5432.
)
for %%F in (.env .env.local) do if exist "%WEB_DIR%\%%F" (
    echo   Aviso: el cliente web ya no usa %WEB_DIR%\%%F; pase sus variables VITE_ al .env de la API y bórrelo.
)
rem The server must answer before anything is installed. PG_ names, so these values never override
rem the DB_ variables that Laravel reads from .env.
set "PG_HOST=127.0.0.1"
set "PG_PORT=5432"
for /f "usebackq tokens=1,* delims==" %%A in (`findstr /B /C:"DB_HOST=" /C:"DB_PORT=" "%ENV_FILE%"`) do (
    if /I "%%A"=="DB_HOST" set "PG_HOST=%%B"
    if /I "%%A"=="DB_PORT" set "PG_PORT=%%B"
)
pg_isready -q -h "%PG_HOST%" -p "%PG_PORT%"
if errorlevel 1 (
    echo   PostgreSQL no responde en %PG_HOST%:%PG_PORT%. Enciéndalo o corrija DB_HOST y DB_PORT en .env.
    goto :failed
)
echo   [listo] PostgreSQL responde en %PG_HOST%:%PG_PORT%.

rem ---------------------------------------------------------------- 4. API dependencies
echo.
echo [4/7] Dependencias de la API: solo instala lo que falta o cambió
pushd "%LARAVEL%"
rem Every PHP extension and version that the locked packages declare, before downloading them.
call composer check-platform-reqs --lock --no-interaction
if errorlevel 1 (
    echo   A PHP le falta lo marcado como missing arriba. Actívelo en php.ini y abra una consola nueva.
    goto :failed_in_folder
)
rem Without the Composer scripts, because on Windows they cannot read the postgres password (step 5).
call composer install --no-interaction --no-scripts
if errorlevel 1 goto :failed_in_folder
call composer dump-autoload --no-interaction
if errorlevel 1 goto :failed_in_folder

rem ---------------------------------------------------------------- 5. Database
echo.
echo [5/7] Base de datos: clave, roles, bases, migraciones pendientes y datos de demostración
echo   Antes de crear roles o migrar se exige PostgreSQL 18 o superior en el servidor.
echo   Si los roles y las bases ya existen se reutilizan, y si ya hay usuarios no se siembra de nuevo.
call php artisan archivum:install --ansi
if errorlevel 1 goto :failed_in_folder
if exist "public\storage" (
    echo   [ya estaba] Enlace public\storage para las fotos.
) else (
    call php artisan storage:link
    if errorlevel 1 goto :failed_in_folder
)
popd

rem ---------------------------------------------------------------- 6. Web client dependencies
echo.
echo [6/7] Dependencias del cliente web
if exist "%WEB_DIR%\node_modules" (
    echo   [ya estaba] node_modules. Si cambió package-lock.json, ejecute npm ci en %WEB_DIR%
) else (
    pushd "%WEB_DIR%"
    call npm ci
    if errorlevel 1 goto :failed_in_folder
    popd
    echo   [hecho] Dependencias instaladas con npm ci.
)

rem ---------------------------------------------------------------- 7. Check
echo.
echo [7/7] Comprobación
pushd "%LARAVEL%"
call php artisan db:show --database=pgsql --no-ansi > "%TEMP%\archivum-db-show.txt" 2>&1
if errorlevel 1 (
    type "%TEMP%\archivum-db-show.txt"
    del "%TEMP%\archivum-db-show.txt" >nul 2>&1
    echo   La comprobación final con php artisan db:show falló; el motivo es el error de arriba.
    goto :failed_in_folder
)
echo   [listo] La aplicación se conecta con el rol archivum_app al servidor:
findstr /C:"PostgreSQL" "%TEMP%\archivum-db-show.txt"
del "%TEMP%\archivum-db-show.txt" >nul 2>&1
echo   Integraciones sin llave en .env. Solo hacen falta para probar esa integración:
call php -r "$empty = 0; foreach (file('.env') as $line) { [$name, $value] = array_pad(explode('=', trim($line), 2), 2, null); if ($value === '' && preg_match('/\A(ONVO|PAYPAL|GEMINI|TWILIO|R2)_/', $name)) { echo '    - ', $name, PHP_EOL; $empty++; } } if ($empty === 0) { echo '    ninguna', PHP_EOL; }"
popd

echo.
echo  Instalación lista. Para trabajar:
echo    API:   cd /d "%LARAVEL%" y composer run dev
echo    Cola:  cd /d "%LARAVEL%" y php artisan queue:work
echo    Web:   cd /d "%WEB_DIR%" y npm run dev
echo.
rem With no answer in 60 seconds nothing starts, so the script also ends when nobody is at the console.
choice /C SN /T 60 /D N /M "¿Iniciar ahora la API, la cola y el cliente web en ventanas aparte"
if errorlevel 2 goto :done
start "ArchivumParfums API" /D "%LARAVEL%" cmd /k composer run dev
start "ArchivumParfums cola" /D "%LARAVEL%" cmd /k php artisan queue:work
start "ArchivumParfums web" /D "%WEB_DIR%" cmd /k npm run dev

:done
echo.
pause
exit /b 0

:require
where %1 >nul 2>&1
if errorlevel 1 (
    echo   Falta %~2: no se encontró el comando %1 en el PATH.
    set "MISSING=1"
)
exit /b 0

:missing_tools
echo.
echo  Instale lo que falta, abra una consola nueva y vuelva a ejecutar instalar.bat.
goto :failed

:failed_in_folder
popd

:failed
echo.
echo  La instalación se detuvo. Corrija el error de arriba y vuelva a ejecutar instalar.bat:
echo  los pasos que ya quedaron hechos se omiten.
echo.
pause
exit /b 1
