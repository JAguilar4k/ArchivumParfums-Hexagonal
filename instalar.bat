@echo off
rem Prepara en Windows las tres partes de ArchivumParfums: la API, el cliente web y la base de datos.
rem Se puede ejecutar todas las veces que haga falta: cada paso que ya esta hecho se omite,
rem asi que una segunda ejecucion no clona de nuevo, no reemplaza .env y no duplica datos.
setlocal EnableExtensions DisableDelayedExpansion
chcp 65001 >nul
title ArchivumParfums: instalación

set "API_REPO=https://github.com/JAguilar4k/ArchivumParfums-Hexagonal.git"
set "WEB_REPO=https://github.com/JAguilar4k/ArchivumParfums-Web.git"
set "HERE=%~dp0"
set "HERE=%HERE:~0,-1%"

rem Desde un clon de la API, el cliente web se clona a su lado; descargado solo, todo queda en esta carpeta.
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

rem ---------------------------------------------------------------- 1. Herramientas
echo [1/7] Herramientas
set "MISSING="
call :require git "Git"
call :require php "PHP 8.4"
call :require composer "Composer 2"
call :require node "Node.js 26"
call :require npm "npm"
if defined MISSING goto :missing_tools

call php -r "exit(version_compare(PHP_VERSION, '8.4.0', '>=') ? 0 : 1);"
if errorlevel 1 (
    echo   Falta PHP 8.4 o superior. Revise con: php -v
    goto :failed
)
call php -r "exit(extension_loaded('pdo_pgsql') ? 0 : 1);"
if errorlevel 1 (
    echo   Falta la extensión pdo_pgsql de PHP. Actívela en php.ini.
    goto :failed
)
node -e "process.exit(Number(process.versions.node.split('.')[0]) >= 26 ? 0 : 1)"
if errorlevel 1 (
    echo   Falta Node.js 26 o superior. Revise con: node -v
    goto :failed
)
echo   [listo] Git, PHP 8.4 con pdo_pgsql, Composer, Node.js 26 y npm.
echo   PostgreSQL 18 debe estar en ejecución; su versión se comprueba en el paso 7.

rem ---------------------------------------------------------------- 2. Repositorios
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

rem ---------------------------------------------------------------- 3. Un solo .env
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

rem ---------------------------------------------------------------- 4. Dependencias de la API
echo.
echo [4/7] Dependencias de la API: solo instala lo que falta o cambió
pushd "%LARAVEL%"
rem Sin los scripts de Composer, porque en Windows no dejan escribir la contraseña de postgres (paso 5).
call composer install --no-interaction --no-scripts
if errorlevel 1 goto :failed_in_folder
call composer dump-autoload --no-interaction
if errorlevel 1 goto :failed_in_folder

rem ---------------------------------------------------------------- 5. Base de datos
echo.
echo [5/7] Base de datos: clave, roles, bases, migraciones pendientes y datos de demostración
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

rem ---------------------------------------------------------------- 6. Dependencias del cliente web
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

rem ---------------------------------------------------------------- 7. Comprobación
echo.
echo [7/7] Comprobación
pushd "%LARAVEL%"
call php artisan db:show --database=pgsql --no-ansi > "%TEMP%\archivum-db-show.txt" 2>&1
if errorlevel 1 (
    type "%TEMP%\archivum-db-show.txt"
    echo   La aplicación no se pudo conectar con el rol archivum_app.
    goto :failed_in_folder
)
findstr /R /C:"PostgreSQL[ .]*18\." "%TEMP%\archivum-db-show.txt" >nul
if errorlevel 1 (
    findstr /C:"PostgreSQL" "%TEMP%\archivum-db-show.txt"
    echo   Aviso: el servidor no es PostgreSQL 18, que es la versión del proyecto.
) else (
    echo   [listo] La aplicación se conecta a PostgreSQL 18 con el rol archivum_app.
)
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
rem Sin respuesta en 60 segundos no inicia nada, para que el script tambien termine sin nadie frente a el.
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
