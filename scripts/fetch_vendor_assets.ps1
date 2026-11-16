<#
.SYNOPSIS
    Downloads the front-end libraries used by the LMS into assets/vendor/.

.DESCRIPTION
    The LMS used to load every CSS/JS library from cdn.jsdelivr.net,
    cdn.datatables.net, cdnjs.cloudflare.com and cdn.canvasjs.com. They are now
    self-hosted (see assets/vendor/README.md) so that:

      * the Content-Security-Policy no longer has to trust third-party script
        origins,
      * an upstream CDN release (CanvasJS was pinned to "latest", SweetAlert2
        floated across all of v11) cannot silently change the UI,
      * the site keeps working when a CDN is blocked on the local network.

    The URL -> path map lives in scripts/vendor-assets.csv. Re-running this
    script only fills in files that are missing; pass -Force to re-download.

    Source maps are deliberately NOT shipped: the trailing
    "sourceMappingURL" comment is stripped from every downloaded .js/.css, so
    DevTools never requests a *.map file (that was what produced the
    "violates the ... connect-src 'self'" console errors).
#>
param(
    [switch]$Force
)

$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'

$root = Split-Path -Parent $PSScriptRoot
$vendorRoot = Join-Path $root 'assets\vendor'
$manifest = Join-Path $PSScriptRoot 'vendor-assets.csv'

if (-not (Test-Path -LiteralPath $manifest)) {
    throw "Manifest not found: $manifest"
}

$assets = Import-Csv -LiteralPath $manifest
$downloaded = 0
$skipped = 0
$failures = @()

foreach ($asset in $assets) {
    $dest = Join-Path $vendorRoot ($asset.Path -replace '/', '\')
    $dir = Split-Path -Parent $dest

    if (-not (Test-Path -LiteralPath $dir)) {
        New-Item -ItemType Directory -Path $dir -Force | Out-Null
    }

    if ((Test-Path -LiteralPath $dest) -and -not $Force) {
        $skipped++
        continue
    }

    $done = $false
    $lastError = ''
    for ($attempt = 1; $attempt -le 3 -and -not $done; $attempt++) {
        try {
            Invoke-WebRequest -Uri $asset.Url -OutFile $dest -UseBasicParsing -TimeoutSec 90
            $done = $true
        } catch {
            $lastError = $_.Exception.Message
            if ($attempt -lt 3) { Start-Sleep -Seconds 2 }
        }
    }

    if (-not $done) {
        Remove-Item -LiteralPath $dest -ErrorAction SilentlyContinue
        $failures += "$($asset.Url): $lastError"
        Write-Warning "failed: $($asset.Url)"
        continue
    }

    $size = (Get-Item -LiteralPath $dest).Length
    if ($size -lt 200) {
        Remove-Item -LiteralPath $dest -ErrorAction SilentlyContinue
        $failures += "$($asset.Url): suspiciously small file ($size bytes)"
        continue
    }

    Write-Host ("downloaded {0,-58} {1,9} bytes" -f $asset.Path, $size)
    $downloaded++
}

Write-Host "`nDownloaded $downloaded file(s), skipped $skipped existing file(s)."

# Strip DevTools-only source map references (see file header).
$stripped = 0
Get-ChildItem -LiteralPath $vendorRoot -Recurse -File -ErrorAction SilentlyContinue |
    Where-Object { $_.Extension -in '.css', '.js' } |
    ForEach-Object {
        $text = [IO.File]::ReadAllText($_.FullName)
        $clean = [regex]::Replace($text, '(?m)^\s*//#\s*sourceMappingURL=.*$', '')
        $clean = [regex]::Replace($clean, '(?s)/\*#\s*sourceMappingURL=.*?\*/\s*$', '')
        if ($clean -ne $text) {
            [IO.File]::WriteAllText($_.FullName, $clean)
            Write-Host "stripped sourceMappingURL: $($_.Name)"
            $stripped++
        }
    }

Write-Host "Stripped source map references from $stripped file(s)."

if ($failures.Count -gt 0) {
    throw ("Could not fetch {0} asset(s):`n  {1}" -f $failures.Count, ($failures -join "`n  "))
}
