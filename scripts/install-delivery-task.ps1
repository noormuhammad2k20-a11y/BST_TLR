param(
    [string]$PhpWin = 'D:\xamp\php\php-win.exe',
    [switch]$Interactive
)
$ErrorActionPreference = 'Stop'
$project = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path
$binary = (Resolve-Path -LiteralPath $PhpWin).Path
if ([IO.Path]::GetFileName($binary) -ne 'php-win.exe') { throw 'Use the windowless php-win.exe executable.' }
$taskName = 'Atelier-Delivery-Attention-' + (Split-Path $project -Leaf)
$runner = (Resolve-Path -LiteralPath (Join-Path $project 'scripts/delivery-background.php')).Path
# Disable shell/process creation in this worker as an enforced no-popup guarantee.
$action = New-ScheduledTaskAction -Execute $binary -Argument ('-d display_errors=0 -d log_errors=1 -d disable_functions=exec,shell_exec,system,passthru,popen,proc_open "' + $runner + '"') -WorkingDirectory $project
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 1)
$settings = New-ScheduledTaskSettingsSet -Hidden -StartWhenAvailable -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 5) -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries
$logon = if ($Interactive) { 'Interactive' } else { 'S4U' }
$principal = New-ScheduledTaskPrincipal -UserId ([Security.Principal.WindowsIdentity]::GetCurrent().Name) -LogonType $logon -RunLevel Limited
Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Settings $settings -Principal $principal -Description 'Windowless local delivery attention; no shell, no customer SMS. S4U is the production default.' -Force -ErrorAction Stop | Out-Null
Start-ScheduledTask -TaskName $taskName -ErrorAction Stop
Get-ScheduledTask -TaskName $taskName | Select-Object TaskName,State,@{N='Executable';E={$_.Actions.Execute}},@{N='LogonType';E={$_.Principal.LogonType}}
