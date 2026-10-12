<#
.SYNOPSIS
    Instala el entorno de desarrollo de ArchivumParfums en Windows.

.DESCRIPTION
    Deja lista una PC con Windows 10 u 11 para trabajar en archivum-api-hexagonal:
      - Laravel Herd, que administra PHP y trae Composer, el instalador de Laravel y Node.
      - PHP en la version de $PhpVersion, con pdo_pgsql, pgsql, intl, mbstring y curl activas.
      - PostgreSQL en la version de $PostgresVersion (instalador oficial de EDB), con psql en el PATH.
      - curl.
    Laravel 13 no se instala en el sistema: es una dependencia de composer.json y llega con
    `composer install` dentro de archivum-api-hexagonal. El script comprueba que el PHP elegido
    lo soporta.

    Cada paso revisa si ya esta hecho, asi que se puede ejecutar varias veces sin riesgo.

.PARAMETER SoloVerificar
    No instala nada: solo muestra que esta instalado y en que version.

.EXAMPLE
    Doble clic en scripts\instalar-entorno-windows.cmd
    o, desde PowerShell:  .\scripts\instalar-entorno-windows.ps1 -SoloVerificar
#>
[CmdletBinding()]
param(
    [switch]$SoloVerificar
)

# ---------------------------------------------------------------------------
# Versions. Change them here and nowhere else.
# ---------------------------------------------------------------------------
$PhpVersion        = '8.4'    # Laravel 13 requires PHP 8.3 or newer. The project stack uses 8.4.
$PostgresVersion   = '18.6'   # PostgreSQL major.minor; the EDB build suffix is added below.
$PostgresBuild     = '1'      # EDB installer build: postgresql-<version>-<build>-windows-x64.exe
$PostgresPort      = 5432
$LaravelMajor      = 13
$MinPhpForLaravel  = [version]'8.3'

$HerdDownloadUrl   = 'https://herd.laravel.com/download/windows'
$PostgresMajor     = $PostgresVersion.Split('.')[0]
$PostgresInstaller = "postgresql-$PostgresVersion-$PostgresBuild-windows-x64.exe"
$PostgresUrl       = "https://get.enterprisedb.com/postgresql/$PostgresInstaller"
$PostgresHome      = Join-Path $env:ProgramFiles "PostgreSQL\$PostgresMajor"
$HerdBin           = Join-Path $env:USERPROFILE '.config\herd\bin'
$PhpExtensions     = @('pdo_pgsql', 'pgsql', 'intl', 'mbstring', 'curl', 'fileinfo', 'openssl', 'zip')

$ErrorActionPreference = 'Stop'
$ProgressPreference    = 'SilentlyContinue'   # Invoke-WebRequest is much faster without the progress bar.
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

function Write-Step([string]$Text) { Write-Host "`n==> $Text" -ForegroundColor Cyan }
function Write-Ok([string]$Text)   { Write-Host "    [ok] $Text" -ForegroundColor Green }
function Write-Warn([string]$Text) { Write-Host "    [!]  $Text" -ForegroundColor Yellow }
function Write-Fail([string]$Text) { Write-Host "    [x]  $Text" -ForegroundColor Red }

function Test-Admin {
    $identity = [Security.Principal.WindowsIdentity]::GetCurrent()
    (New-Object Security.Principal.WindowsPrincipal $identity).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
}

function Update-SessionPath {
    $machine = [Environment]::GetEnvironmentVariable('Path', 'Machine')
    $user    = [Environment]::GetEnvironmentVariable('Path', 'User')
    $env:Path = "$machine;$user"
    if ((Test-Path $HerdBin) -and ($env:Path -notlike "*$HerdBin*")) { $env:Path = "$HerdBin;$env:Path" }
}

function Add-ToUserPath([string]$Dir) {
    $user = [Environment]::GetEnvironmentVariable('Path', 'User')
    if (-not $user) { $user = '' }
    if (($user.Split(';') | Where-Object { $_ -ieq $Dir }).Count -eq 0) {
        [Environment]::SetEnvironmentVariable('Path', ($user.TrimEnd(';') + ";$Dir").TrimStart(';'), 'User')
        Write-Ok "$Dir agregado al PATH del usuario."
    }
    Update-SessionPath
}

function Get-CommandPath([string]$Name) {
    $cmd = Get-Command $Name -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($cmd) { return $cmd.Source }
    return $null
}

function Invoke-Download([string]$Url, [string]$OutFile) {
    Write-Host "    Descargando $Url"
    Invoke-WebRequest -Uri $Url -OutFile $OutFile -UseBasicParsing
}

function Get-PhpVersion {
    if (-not (Get-CommandPath 'php')) { return $null }
    $raw = & php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION.".".PHP_RELEASE_VERSION;' 2>$null
    if ($LASTEXITCODE -ne 0 -or -not $raw) { return $null }
    return [version]$raw
}

# ---------------------------------------------------------------------------
# Steps
# ---------------------------------------------------------------------------

function Install-Herd {
    Write-Step 'Laravel Herd'
    Update-SessionPath
    if (Get-CommandPath 'herd') { Write-Ok 'Herd ya esta instalado.'; return }

    $installer = Join-Path $env:TEMP 'HerdSetup.exe'
    Invoke-Download $HerdDownloadUrl $installer
    Write-Host '    Se abre el instalador de Herd. Complete el asistente y vuelva a esta ventana.'
    Start-Process -FilePath $installer -Wait
    Update-SessionPath

    if (-not (Get-CommandPath 'herd')) {
        throw 'Herd no quedo disponible. Abra Herd una vez, cierre esta ventana y ejecute el instalador de nuevo.'
    }
    Write-Ok 'Herd instalado.'
}

function Set-HerdPhp {
    Write-Step "PHP $PhpVersion con Herd"
    $current = Get-PhpVersion
    if ($current -and ("$($current.Major).$($current.Minor)" -eq $PhpVersion)) {
        Write-Ok "PHP $current ya es la version activa."
    } else {
        # `herd use` downloads the version if Herd does not have it yet and makes it the global PHP.
        & herd use $PhpVersion
        Update-SessionPath
        $current = Get-PhpVersion
        if (-not $current -or ("$($current.Major).$($current.Minor)" -ne $PhpVersion)) {
            throw "PHP $PhpVersion no quedo activo. En Herd, abra la seccion PHP, instale $PhpVersion, marquela como global y ejecute el instalador de nuevo."
        }
        Write-Ok "PHP $current activo."
    }

    if ($current -lt $MinPhpForLaravel) {
        throw "Laravel $LaravelMajor necesita PHP $MinPhpForLaravel o superior y la version elegida es $current."
    }
}

function Enable-PhpExtensions {
    Write-Step 'Extensiones de PHP'
    $loaded  = (& php -m) | ForEach-Object { $_.Trim().ToLower() }
    $missing = $PhpExtensions | Where-Object { $loaded -notcontains $_ }
    if (-not $missing) { Write-Ok ("Activas: " + ($PhpExtensions -join ', ')); return }

    $ini = (& php -r 'echo php_ini_loaded_file();')
    if (-not $ini -or -not (Test-Path $ini)) { throw "No se encontro el php.ini de PHP $PhpVersion para activar: $($missing -join ', ')." }

    Copy-Item $ini "$ini.bak" -Force
    $content = Get-Content $ini
    foreach ($ext in $missing) {
        $pattern = "^\s*;\s*extension\s*=\s*(php_)?$ext(\.dll)?\s*$"
        if ($content -match $pattern) {
            $content = $content -replace $pattern, "extension=$ext"
        } else {
            $content += "extension=$ext"
        }
    }
    Set-Content -Path $ini -Value $content -Encoding ASCII
    Write-Ok "php.ini actualizado ($ini, respaldo en php.ini.bak)."

    $loaded = (& php -m) | ForEach-Object { $_.Trim().ToLower() }
    $still  = $PhpExtensions | Where-Object { $loaded -notcontains $_ }
    if ($still) { Write-Warn ("Siguen sin cargar: " + ($still -join ', ') + ". Reinicie Herd y vuelva a verificar.") }
    else { Write-Ok ("Activas: " + ($PhpExtensions -join ', ')) }
}

function Test-ComposerAndLaravel {
    Write-Step "Composer e instalador de Laravel $LaravelMajor"
    if (-not (Get-CommandPath 'composer')) { throw 'Composer no esta disponible. Herd lo incluye: abra Herd una vez y vuelva a ejecutar.' }
    Write-Ok ((& composer --version 2>$null | Select-Object -First 1))

    if (-not (Get-CommandPath 'laravel')) {
        & composer global require laravel/installer
        Update-SessionPath
    }
    if (Get-CommandPath 'laravel') { Write-Ok ((& laravel --version) | Select-Object -First 1) }
    else { Write-Warn 'El comando laravel no quedo en el PATH. No es necesario para trabajar en el repo.' }
    Write-Host "    Laravel $LaravelMajor llega con 'composer install' en archivum-api-hexagonal."
}

function Install-Postgres {
    Write-Step "PostgreSQL $PostgresVersion"
    $psql = Join-Path $PostgresHome 'bin\psql.exe'
    if (Test-Path $psql) {
        $installed = (& $psql --version) -replace '^.*\)\s*', ''
        if ($installed -eq $PostgresVersion) { Write-Ok "PostgreSQL $installed ya esta instalado." }
        else { Write-Warn "Ya hay PostgreSQL $installed en $PostgresHome. Se deja como esta; actualicelo con el instalador de EDB si necesita $PostgresVersion." }
        Add-ToUserPath (Join-Path $PostgresHome 'bin')
        return
    }

    $password = Read-Host -AsSecureString "    Clave para el usuario postgres (no se guarda en ningun archivo)"
    $plain = [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($password))
    if ([string]::IsNullOrWhiteSpace($plain)) { throw 'La clave de postgres no puede quedar vacia.' }

    $installer = Join-Path $env:TEMP $PostgresInstaller
    try { Invoke-Download $PostgresUrl $installer }
    catch { throw "No se pudo descargar $PostgresUrl. Revise que la version $PostgresVersion exista en https://www.enterprisedb.com/downloads/postgres-postgresql-downloads y ajuste `$PostgresVersion o `$PostgresBuild." }

    Write-Host '    Instalando en modo desatendido (tarda unos minutos)...'
    $arguments = @(
        '--mode', 'unattended',
        '--unattendedmodeui', 'minimal',
        '--superpassword', $plain,
        '--serverport', "$PostgresPort",
        '--prefix', "`"$PostgresHome`"",
        '--datadir', "`"$PostgresHome\data`"",
        '--enable-components', 'server,pgAdmin,commandlinetools',
        '--disable-components', 'stackbuilder'
    )
    $process = Start-Process -FilePath $installer -ArgumentList $arguments -Wait -PassThru
    $plain = $null
    if ($process.ExitCode -ne 0) { throw "El instalador de PostgreSQL termino con codigo $($process.ExitCode)." }

    Add-ToUserPath (Join-Path $PostgresHome 'bin')
    Write-Ok "PostgreSQL $PostgresVersion instalado como servicio en el puerto $PostgresPort."
}

function Install-Curl {
    Write-Step 'curl'
    $curl = Join-Path $env:SystemRoot 'System32\curl.exe'
    if (Test-Path $curl) { Write-Ok ((& $curl --version) | Select-Object -First 1); return }
    if (Get-CommandPath 'winget') {
        & winget install --id cURL.cURL --exact --silent --accept-package-agreements --accept-source-agreements
        Update-SessionPath
        if (Get-CommandPath 'curl.exe') { Write-Ok 'curl instalado con winget.'; return }
    }
    Write-Warn 'No se pudo instalar curl. Descarguelo de https://curl.se/windows/.'
}

function Show-Summary {
    Write-Step 'Resumen'
    Update-SessionPath
    $rows = @(
        @{ Name = 'Herd';       Cmd = { if (Get-CommandPath 'herd') { 'instalado' } } },
        @{ Name = 'PHP';        Cmd = { Get-PhpVersion } },
        @{ Name = 'Composer';   Cmd = { if (Get-CommandPath 'composer') { (& composer --version 2>$null) -replace '^Composer version (\S+).*$', '$1' } } },
        @{ Name = 'Laravel CLI';Cmd = { if (Get-CommandPath 'laravel') { (& laravel --version) -replace '^.*?(\d[\d.]*).*$', '$1' } } },
        @{ Name = 'Node';       Cmd = { if (Get-CommandPath 'node') { & node --version } } },
        @{ Name = 'PostgreSQL'; Cmd = { $p = Join-Path $PostgresHome 'bin\psql.exe'; if (Test-Path $p) { (& $p --version) -replace '^.*\)\s*', '' } } },
        @{ Name = 'curl';       Cmd = { $c = Get-CommandPath 'curl.exe'; if ($c) { ((& $c --version) | Select-Object -First 1).Split(' ')[1] } } }
    )
    $ok = $true
    foreach ($row in $rows) {
        $value = $null
        try { $value = & $row.Cmd } catch { }
        if ($value) { Write-Ok ("{0,-12} {1}" -f $row.Name, $value) }
        else { Write-Fail ("{0,-12} no encontrado" -f $row.Name); $ok = $false }
    }

    $php = Get-PhpVersion
    if ($php) {
        $loaded = (& php -m) | ForEach-Object { $_.Trim().ToLower() }
        $missing = $PhpExtensions | Where-Object { $loaded -notcontains $_ }
        if ($missing) { Write-Fail ("Extensiones de PHP faltantes: " + ($missing -join ', ')); $ok = $false }
        else { Write-Ok ("Extensiones de PHP: " + ($PhpExtensions -join ', ')) }
    }
    return $ok
}

# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------

Write-Host "Entorno de ArchivumParfums: PHP $PhpVersion (Herd), PostgreSQL $PostgresVersion, Laravel $LaravelMajor, curl" -ForegroundColor White

if ($SoloVerificar) {
    if (Show-Summary) { exit 0 } else { exit 1 }
}

if (-not (Test-Admin)) {
    Write-Host 'Se necesitan permisos de administrador para instalar PostgreSQL. Se abre una ventana elevada.' -ForegroundColor Yellow
    Start-Process -FilePath 'powershell.exe' -Verb RunAs -ArgumentList @('-NoProfile', '-ExecutionPolicy', 'Bypass', '-NoExit', '-File', "`"$PSCommandPath`"")
    exit 0
}

try {
    Install-Herd
    Set-HerdPhp
    Enable-PhpExtensions
    Test-ComposerAndLaravel
    Install-Postgres
    Install-Curl
} catch {
    Write-Fail $_.Exception.Message
    exit 1
}

if (Show-Summary) {
    Write-Host "`nListo. Abra una terminal nueva para que el PATH se actualice." -ForegroundColor Green
    Write-Host 'Siguiente paso: los comandos de la seccion "Puesta en marcha" del README.'
    exit 0
}
Write-Host "`nAlgunas piezas faltan; revise los mensajes de arriba y ejecute el instalador de nuevo." -ForegroundColor Yellow
exit 1
