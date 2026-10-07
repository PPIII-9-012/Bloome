$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $projectRoot
$phpExe = Join-Path $projectRoot '.tools/php/php.exe'
$mysqlExe = 'C:/Program Files/MySQL/MySQL Server 8.0/bin/mysqld.exe'
$mysqlData = Join-Path $projectRoot '.tools/mysql-data'

if (!(Test-Path -LiteralPath $phpExe) -or !(Test-Path -LiteralPath $mysqlData) -or !(Test-Path -LiteralPath $mysqlExe)) {
    throw 'Este lanzador utiliza el entorno local preparado en esta PC. En otra máquina seguí el arranque Docker del README.'
}

function Test-ProjectProcess([string] $PidFile, [string] $ExpectedExe) {
    if (!(Test-Path -LiteralPath $PidFile)) { return $false }
    $taskPid = [int](Get-Content -LiteralPath $PidFile -Raw).Trim()
    $taskProcess = Get-Process -Id $taskPid -ErrorAction SilentlyContinue
    if (!$taskProcess) { return $false }
    return $taskProcess.Path -and ($taskProcess.Path.Replace('\','/') -eq $ExpectedExe.Replace('\','/'))
}

$mysqlPidFile = Join-Path $projectRoot '.tools/mysql.pid'
if (!(Test-ProjectProcess $mysqlPidFile $mysqlExe)) {
    $mysqlArgs = @('--no-defaults', '--basedir="C:/Program Files/MySQL/MySQL Server 8.0"', ('--datadir="{0}"' -f $mysqlData), '--port=3308', '--bind-address=127.0.0.1', '--mysqlx=0', ('--log-error="{0}"' -f (Join-Path $projectRoot '.tools/mysql.log')))
    $mysqlProcess = Start-Process -FilePath $mysqlExe -ArgumentList $mysqlArgs -WindowStyle Hidden -PassThru
    $mysqlProcess.Id | Set-Content -LiteralPath $mysqlPidFile
}

$phpPidFile = Join-Path $projectRoot '.tools/php.pid'
if (!(Test-ProjectProcess $phpPidFile $phpExe)) {
    $phpProcess = Start-Process -FilePath $phpExe -ArgumentList '-S','127.0.0.1:8000','-t','public','public/router.php' -WorkingDirectory $projectRoot -WindowStyle Hidden -RedirectStandardOutput '.tools/server.stdout.log' -RedirectStandardError '.tools/server.stderr.log' -PassThru
    $phpProcess.Id | Set-Content -LiteralPath $phpPidFile
}
Write-Output 'Bloome: http://127.0.0.1:8000 — credenciales ADMIN_EMAIL y ADMIN_PASSWORD en .env.'
