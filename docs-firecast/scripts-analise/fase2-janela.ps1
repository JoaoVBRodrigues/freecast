# Sonda somente leitura; não captura tela, lê texto de conversas ou envia entrada.
param([string]$OutputPath)
Add-Type -AssemblyName UIAutomationClient
Add-Type -AssemblyName UIAutomationTypes
Add-Type -TypeDefinition @'
using System;
using System.Collections.Generic;
using System.Runtime.InteropServices;
public class FirecastWindowProbe {
 public delegate bool EnumCallback(IntPtr h, IntPtr l);
 [DllImport("user32.dll")] public static extern bool EnumWindows(EnumCallback callback, IntPtr l);
 [DllImport("user32.dll")] public static extern uint GetWindowThreadProcessId(IntPtr h, out uint pid);
 [DllImport("user32.dll")] public static extern bool IsWindowVisible(IntPtr h);
 public static long[] Find(int target) { var found=new List<long>(); EnumWindows((h,l)=>{uint p;GetWindowThreadProcessId(h,out p);if(p==target && IsWindowVisible(h))found.Add(h.ToInt64());return true;},IntPtr.Zero); return found.ToArray(); }
}
'@
$firecastResults = @()
foreach ($firecastProcess in @(Get-Process -Name Firecast -ErrorAction SilentlyContinue)) {
    if ($firecastProcess.Path -ne 'C:\Users\João\AppData\Local\Firecast\Firecast.exe') { continue }
    $firecastWindows = @([FirecastWindowProbe]::Find($firecastProcess.Id))
    $roomMatches = @()
    foreach ($firecastWindowHandle in $firecastWindows) {
        try {
            $firecastWindow = [System.Windows.Automation.AutomationElement]::FromHandle([IntPtr]$firecastWindowHandle)
            $roomCondition = New-Object System.Windows.Automation.PropertyCondition([System.Windows.Automation.AutomationElement]::NameProperty, "Creator's Game")
            $matches = $firecastWindow.FindAll([System.Windows.Automation.TreeScope]::Descendants, $roomCondition)
            foreach ($match in $matches) {
                $roomMatches += [PSCustomObject]@{Window=$firecastWindowHandle;ControlType=$match.Current.ControlType.ProgrammaticName;Enabled=$match.Current.IsEnabled}
            }
        } catch {
            $roomMatches += [PSCustomObject]@{Window=$firecastWindowHandle;Error=$_.Exception.Message}
        }
    }
    $firecastResults += [PSCustomObject]@{ProcessId=$firecastProcess.Id;Path=$firecastProcess.Path;SessionId=$firecastProcess.SessionId;MainWindowHandle=$firecastProcess.MainWindowHandle.ToInt64();VisibleWindowHandles=$firecastWindows;ExactTestRoomMatches=$roomMatches}
}
$report = [PSCustomObject]@{Id='LOCAL-WINDOW-002';CollectedAtUtc=[DateTime]::UtcNow.ToString('o');AgentSessionId=(Get-Process -Id $PID).SessionId;Processes=$firecastResults;ReadOnly=$true;ScreenshotsTaken=0;ActionsPerformed=0;Limitation='Observação do desktop acessível a este processo; ausência de handle não prova que a aplicação está fechada.'}
$json = $report | ConvertTo-Json -Depth 6
if ($OutputPath) { $json | Set-Content -LiteralPath $OutputPath -Encoding UTF8 }
Write-Output $json
