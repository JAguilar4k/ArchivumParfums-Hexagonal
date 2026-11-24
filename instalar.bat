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
call :update_clone "%API_DIR%" "La API" warn
call :update_clone "%WEB_DIR%" "El cliente web"

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
rem A .env from an older installation keeps its values and gains only the variables that .env.example
rem added since, with their example values; without the VITE_ ones the web client shows a blank page.
pushd "%LARAVEL%"
call php -r "$present = []; foreach (file('.env', FILE_IGNORE_NEW_LINES) as $line) { if (preg_match('/\A\s*([A-Z][A-Z0-9_]*)=/', $line, $m)) { $present[$m[1]] = true; } } $added = []; foreach (file('.env.example', FILE_IGNORE_NEW_LINES) as $line) { if (preg_match('/\A([A-Z][A-Z0-9_]*)=/', $line, $m) && ! isset($present[$m[1]])) { $added[$m[1]] = $line; } } if ($added === []) { echo '  [listo] .env tiene todas las variables de .env.example.', PHP_EOL; exit(0); } file_put_contents('.env', PHP_EOL.'# Added by instalar.bat from .env.example'.PHP_EOL.implode(PHP_EOL, $added).PHP_EOL, FILE_APPEND); echo '  [hecho] Se agregaron a .env, con su valor de ejemplo: ', implode(', ', array_keys($added)), PHP_EOL;"
if errorlevel 1 goto :failed_in_folder
popd
rem Uploaded photos get their address from APP_URL, so it must include the port of composer run dev.
call php -r "foreach (file('%ENV_FILE%', FILE_IGNORE_NEW_LINES) as $line) { if (in_array(trim($line), ['APP_URL=http://localhost', 'APP_URL=http://localhost/'], true)) { exit(1); } }"
if errorlevel 1 (
    echo   Aviso: APP_URL=http://localhost no lleva el puerto de la API y las fotos subidas no cargan. Cámbielo a APP_URL=http://localhost:8000 en .env.
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
pushd "%WEB_DIR%"
rem npm ls fails when node_modules is missing or no longer matches package-lock.json, as after a git pull.
call npm ls --depth=0 >nul 2>&1
if errorlevel 1 (
    call npm ci
    if errorlevel 1 goto :failed_in_folder
    echo   [hecho] Dependencias instaladas con npm ci.
) else (
    echo   [ya estaba] node_modules coincide con package-lock.json.
)
popd

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
rem The worker prints nothing until a job arrives, so the window says first that it is waiting.
start "ArchivumParfums cola" /D "%LARAVEL%" cmd /k "echo Cola de trabajos: espera trabajos y muestra cada uno cuando llega. Si no aparece nada, no está trabada; déjela abierta. & php artisan queue:work"
start "ArchivumParfums web" /D "%WEB_DIR%" cmd /k npm run dev

:done
echo.
pause
exit /b 0

rem An old clone misses configuration, such as the shared .env of the web client. A clean clone on main
rem is updated; the API only gets a warning, because pulling it could change this script while it runs.
:update_clone
set "BRANCH=sin rama"
for /f "delims=" %%B in ('git -C "%~1" branch --show-current') do set "BRANCH=%%B"
if /I not "%BRANCH%"=="main" (
    echo   Aviso: %~2 está en la rama %BRANCH%, no en main. A una rama vieja le puede faltar configuración: guarde sus cambios y cambie a main.
    exit /b 0
)
git -C "%~1" fetch -q origin main
if errorlevel 1 (
    echo   Aviso: no se pudo consultar main de %~2 en GitHub; se sigue con lo que hay.
    exit /b 0
)
set "BEHIND=0"
for /f %%N in ('git -C "%~1" rev-list --count HEAD..origin/main') do set "BEHIND=%%N"
if "%BEHIND%"=="0" (
    echo   [listo] %~2 está al día con main.
    exit /b 0
)
set "DIRTY="
for /f %%L in ('git -C "%~1" status --porcelain') do set "DIRTY=1"
if "%~3"=="warn" set "DIRTY=1"
if defined DIRTY (
    echo   Aviso: %~2 tiene %BEHIND% cambios nuevos en main. Ejecute git pull en %~1 y vuelva a ejecutar instalar.bat.
    exit /b 0
)
git -C "%~1" merge -q --ff-only origin/main
if errorlevel 1 (
    echo   Aviso: no se pudo actualizar %~2; ejecute git pull en %~1.
) else (
    echo   [hecho] %~2 se actualizó con %BEHIND% cambios de main.
)
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
