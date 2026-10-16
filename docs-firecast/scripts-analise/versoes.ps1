$targetRoot = 'C:\Users\João\AppData\Local\Firecast'
$items = Get-ChildItem -LiteralPath $targetRoot -Recurse -File | Where-Object { $_.Extension -in '.exe','.dll' }
$items | ForEach-Object {
    $version = $_.VersionInfo
    [PSCustomObject]@{
        File = $_.FullName.Substring($targetRoot.Length + 1)
        FileVersion = $version.FileVersion
        ProductVersion = $version.ProductVersion
        ProductName = $version.ProductName
        CompanyName = $version.CompanyName
        Description = $version.FileDescription
    }
} | ConvertTo-Json -Depth 4 | Set-Content -LiteralPath 'docs-firecast/inventarios/versoes-windows.json' -Encoding UTF8
