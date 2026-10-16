# Captura somente a janela visível pertencente ao Firecast, sem capturar o desktop.
# Saída transitória de inspeção; não entra no conjunto público de evidências.
param([Parameter(Mandatory=$true)][string]$OutputPath,[int]$RegionX=0,[int]$RegionY=0,[int]$RegionWidth=0,[int]$RegionHeight=0)
Add-Type -AssemblyName System.Drawing
Add-Type -TypeDefinition @'
using System; using System.Runtime.InteropServices;
public class FirecastSelectedCapture {
 [DllImport("user32.dll")] public static extern IntPtr SetThreadDpiAwarenessContext(IntPtr context);
 public delegate bool Callback(IntPtr h,IntPtr l);
 [StructLayout(LayoutKind.Sequential)] public struct Rect{public int Left,Top,Right,Bottom;}
 [DllImport("user32.dll")] public static extern bool EnumWindows(Callback cb,IntPtr l);
 [DllImport("user32.dll")] public static extern uint GetWindowThreadProcessId(IntPtr h,out uint pid);
 [DllImport("user32.dll")] public static extern bool IsWindowVisible(IntPtr h);
 [DllImport("user32.dll")] public static extern bool GetWindowRect(IntPtr h,out Rect rect);
 [DllImport("user32.dll")] public static extern bool PrintWindow(IntPtr h,IntPtr dc,uint flags);
 [DllImport("gdi32.dll")] public static extern bool SetViewportOrgEx(IntPtr dc,int x,int y,IntPtr old);
 public static IntPtr Find(int target){IntPtr selected=IntPtr.Zero; EnumWindows((h,l)=>{uint p;GetWindowThreadProcessId(h,out p);if(p==target&&IsWindowVisible(h)){selected=h;return false;}return true;},IntPtr.Zero);return selected;}
}
'@
[void][FirecastSelectedCapture]::SetThreadDpiAwarenessContext([IntPtr](-4))
$targetProcess = @(Get-Process -Name Firecast -ErrorAction Stop | Where-Object {$_.Path -eq 'C:\Users\João\AppData\Local\Firecast\Firecast.exe'})
if($targetProcess.Count -ne 1){throw 'Ambiguous target process'}
$window = [FirecastSelectedCapture]::Find($targetProcess[0].Id)
if($window -eq [IntPtr]::Zero){throw 'No visible Firecast window accessible'}
$rect = New-Object FirecastSelectedCapture+Rect
if(-not [FirecastSelectedCapture]::GetWindowRect($window,[ref]$rect)){throw 'Window bounds unavailable'}
$width = $rect.Right-$rect.Left; $height=$rect.Bottom-$rect.Top
if($width -lt 100 -or $height -lt 100 -or $width -gt 10000 -or $height -gt 10000){throw 'Unexpected window dimensions'}
if($RegionWidth -eq 0){$RegionWidth=$width};if($RegionHeight -eq 0){$RegionHeight=$height}
if($RegionX -lt 0 -or $RegionY -lt 0 -or $RegionX+$RegionWidth -gt $width -or $RegionY+$RegionHeight -gt $height){throw 'Region outside selected window'}
$fullBitmap = New-Object System.Drawing.Bitmap($width,$height)
$graphics = [System.Drawing.Graphics]::FromImage($fullBitmap)
$context = $graphics.GetHdc()
try{$captured=[FirecastSelectedCapture]::PrintWindow($window,$context,2)}finally{$graphics.ReleaseHdc($context)}
try{
 if(-not $captured){throw 'PrintWindow failed; no broad desktop fallback'}
 $bitmap=New-Object System.Drawing.Bitmap($RegionWidth,$RegionHeight)
 $regionGraphics=[System.Drawing.Graphics]::FromImage($bitmap)
 try{$regionGraphics.DrawImageUnscaled($fullBitmap,-$RegionX,-$RegionY);$bitmap.Save($OutputPath,[System.Drawing.Imaging.ImageFormat]::Png)}finally{$regionGraphics.Dispose();$bitmap.Dispose()}
}finally{$graphics.Dispose();$fullBitmap.Dispose()}
[PSCustomObject]@{ProcessId=$targetProcess[0].Id;WindowHandle=$window.ToInt64();WindowWidth=$width;WindowHeight=$height;RegionX=$RegionX;RegionY=$RegionY;Width=$RegionWidth;Height=$RegionHeight;Method='PrintWindow selected Firecast window region; no rescale';File=$OutputPath} | ConvertTo-Json
