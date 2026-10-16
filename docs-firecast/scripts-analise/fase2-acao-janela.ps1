# Ação explícita somente na janela Firecast previamente observada.
param([Parameter(Mandatory=$true)][int]$X,[Parameter(Mandatory=$true)][int]$Y,[Parameter(Mandatory=$true)][string]$ActionId,[Parameter(Mandatory=$true)][int]$ExpectedWidth,[Parameter(Mandatory=$true)][int]$ExpectedHeight,[string]$Text,[switch]$Submit)
Add-Type -TypeDefinition @'
using System; using System.Runtime.InteropServices;
public class FirecastSelectedAction {
 [DllImport("user32.dll")] public static extern IntPtr SetThreadDpiAwarenessContext(IntPtr context);
 public delegate bool Callback(IntPtr h,IntPtr l);
 [StructLayout(LayoutKind.Sequential)] public struct Rect{public int Left,Top,Right,Bottom;}
 [StructLayout(LayoutKind.Sequential)] public struct Point{public int X,Y;}
 [DllImport("user32.dll")] public static extern bool EnumWindows(Callback cb,IntPtr l);
 [DllImport("user32.dll")] public static extern uint GetWindowThreadProcessId(IntPtr h,out uint pid);
 [DllImport("user32.dll")] public static extern bool IsWindowVisible(IntPtr h);
 [DllImport("user32.dll")] public static extern bool GetWindowRect(IntPtr h,out Rect rect);
 [DllImport("user32.dll")] public static extern bool ScreenToClient(IntPtr h,ref Point p);
 [DllImport("user32.dll")] public static extern IntPtr SendMessage(IntPtr h,uint m,IntPtr w,IntPtr l);
 public static IntPtr Find(int target){IntPtr selected=IntPtr.Zero;EnumWindows((h,l)=>{uint p;GetWindowThreadProcessId(h,out p);if(p==target&&IsWindowVisible(h)){selected=h;return false;}return true;},IntPtr.Zero);return selected;}
}
'@
if($Text -and -not $Text.StartsWith('[REA TESTE]') -and -not $Text.StartsWith('/r ') -and $Text -ne '/macros'){throw 'Only synthetic test text or /macros navigation permitted'}
[void][FirecastSelectedAction]::SetThreadDpiAwarenessContext([IntPtr](-4))
$targetProcess=@(Get-Process -Name Firecast -ErrorAction Stop | Where-Object {$_.Path -eq 'C:\Users\João\AppData\Local\Firecast\Firecast.exe'})
if($targetProcess.Count -ne 1){throw 'Ambiguous target'}
$window=[FirecastSelectedAction]::Find($targetProcess[0].Id)
if($window -eq [IntPtr]::Zero){throw 'No window'}
$rect=New-Object FirecastSelectedAction+Rect
[void][FirecastSelectedAction]::GetWindowRect($window,[ref]$rect)
if(($rect.Right-$rect.Left) -ne $ExpectedWidth -or ($rect.Bottom-$rect.Top) -ne $ExpectedHeight){throw 'Window geometry changed; observe again'}
if($X -lt 0 -or $X -ge $ExpectedWidth -or $Y -lt 38 -or $Y -ge $ExpectedHeight){throw 'Click outside observed client region'}
$point=New-Object FirecastSelectedAction+Point
$point.X=$rect.Left+$X;$point.Y=$rect.Top+$Y
[void][FirecastSelectedAction]::ScreenToClient($window,[ref]$point)
$position=[IntPtr](($point.Y -shl 16) -bor ($point.X -band 65535))
[void][FirecastSelectedAction]::SendMessage($window,0x201,[IntPtr]1,$position)
[void][FirecastSelectedAction]::SendMessage($window,0x202,[IntPtr]0,$position)
if($Text){
 foreach($character in $Text.ToCharArray()){[void][FirecastSelectedAction]::SendMessage($window,0x102,[IntPtr][int]$character,[IntPtr]0)}
}
if($Submit){[void][FirecastSelectedAction]::SendMessage($window,0x100,[IntPtr]13,[IntPtr]0);[void][FirecastSelectedAction]::SendMessage($window,0x101,[IntPtr]13,[IntPtr]0)}
[PSCustomObject]@{ActionId=$ActionId;AtUtc=[DateTime]::UtcNow.ToString('o');ProcessId=$targetProcess[0].Id;WindowHandle=$window.ToInt64();X=$X;Y=$Y;ClientX=$point.X;ClientY=$point.Y;TextLength=if($Text){$Text.Length}else{0};Method='WM_LBUTTONDOWN/UP (and WM_CHAR only if requested), selected window'} | ConvertTo-Json
