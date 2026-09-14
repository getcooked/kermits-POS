$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Drawing
$layout = Get-Content -LiteralPath (Join-Path $PSScriptRoot 'flowchart-layout.json') -Raw -Encoding UTF8 | ConvertFrom-Json
$previewDir = Join-Path $PSScriptRoot 'flowchart-previews'
New-Item -ItemType Directory -Path $previewDir -Force | Out-Null
$black = [System.Drawing.Brushes]::Black
$white = [System.Drawing.Brushes]::White
$pen = [System.Drawing.Pen]::new([System.Drawing.Color]::Black, 1.5)
$format = [System.Drawing.StringFormat]::new()
$format.Alignment = [System.Drawing.StringAlignment]::Center
$format.LineAlignment = [System.Drawing.StringAlignment]::Center
$format.Trimming = [System.Drawing.StringTrimming]::None
function Draw-Text($graphics, $value, $x, $y, $width, $height, $size, $bold = $false) {
    $style = if ($bold) { [System.Drawing.FontStyle]::Bold } else { [System.Drawing.FontStyle]::Regular }
    $font = [System.Drawing.Font]::new('Arial', [single]$size, $style, [System.Drawing.GraphicsUnit]::Pixel)
    $rect = [System.Drawing.RectangleF]::new($x, $y, $width, $height)
    $graphics.DrawString([string]$value, $font, $black, $rect, $format)
    $font.Dispose()
}
$montage = [System.Drawing.Bitmap]::new(1530, 1980)
$mg = [System.Drawing.Graphics]::FromImage($montage)
$mg.Clear([System.Drawing.Color]::White)
$mg.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
$index = 0
foreach ($page in $layout.pages) {
    $bitmap = [System.Drawing.Bitmap]::new(850, 1100)
    $g = [System.Drawing.Graphics]::FromImage($bitmap)
    $g.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::AntiAlias
    $g.TextRenderingHint = [System.Drawing.Text.TextRenderingHint]::AntiAliasGridFit
    $g.Clear([System.Drawing.Color]::White)
    $g.DrawRectangle($pen, 30, 92, 790, 990)
    Draw-Text $g ('Flowchart ' + ($index + 1) + ' - ' + $page.title) 35 20 780 28 20 $true
    Draw-Text $g $page.subtitle 35 53 780 22 12
    Draw-Text $g $page.foot 35 76 780 14 9
    foreach ($edge in $page.edges) {
        $pts = [System.Drawing.PointF[]]@($edge.points | ForEach-Object { [System.Drawing.PointF]::new([single]$_[0], [single]$_[1]) })
        $g.DrawLines($pen, $pts)
        $tip = $pts[-1]; $prev = $pts[-2]
        $angle = [Math]::Atan2(($tip.Y - $prev.Y), ($tip.X - $prev.X))
        $arrow = [System.Drawing.PointF[]]@($tip,
            [System.Drawing.PointF]::new(($tip.X - 10 * [Math]::Cos($angle) + 4 * [Math]::Sin($angle)), ($tip.Y - 10 * [Math]::Sin($angle) - 4 * [Math]::Cos($angle))),
            [System.Drawing.PointF]::new(($tip.X - 10 * [Math]::Cos($angle) - 4 * [Math]::Sin($angle)), ($tip.Y - 10 * [Math]::Sin($angle) + 4 * [Math]::Cos($angle))))
        $g.FillPolygon($black, $arrow)
        if ($edge.label) {
            $a = $pts[0]; $b = $pts[1]
            $tx = $edge.labelAt[0]; $ty = $edge.labelAt[1]
            $tw = [Math]::Max(45, $edge.label.Length * 8 + 16)
            $g.FillRectangle($white, [single]($tx - $tw / 2), [single]($ty - 10), [single]$tw, 20)
            Draw-Text $g $edge.label ($tx - $tw / 2) ($ty - 10) $tw 20 12
        }
    }
    foreach ($node in $page.nodes) {
        $x = [single]$node.x; $y = [single]$node.y; $w = [single]$node.w; $h = [single]$node.h
        $poly = $null
        switch ($node.kind) {
            'decision' { $poly = @(@(($x + $w / 2), $y), @(($x + $w), ($y + $h / 2)), @(($x + $w / 2), ($y + $h)), @($x, ($y + $h / 2))) }
            'input' { $poly = @(@(($x + 15), $y), @(($x + $w), $y), @(($x + $w - 15), ($y + $h)), @($x, ($y + $h))) }
            'offpage' { $poly = @(@($x, $y), @(($x + $w), $y), @(($x + $w), ($y + $h * .6)), @(($x + $w / 2), ($y + $h)), @($x, ($y + $h * .6))) }
            { $_ -in 'terminator','connector' } { $g.FillEllipse($white,$x,$y,$w,$h); $g.DrawEllipse($pen,$x,$y,$w,$h) }
            'process' { $g.FillRectangle($white,$x,$y,$w,$h); $g.DrawRectangle($pen,$x,$y,$w,$h) }
        }
        if ($poly) {
            $polygon = [System.Drawing.PointF[]]@($poly | ForEach-Object { [System.Drawing.PointF]::new([single]$_[0], [single]$_[1]) })
            $g.FillPolygon($white,$polygon); $g.DrawPolygon($pen,$polygon)
        }
        $size = if ($node.kind -eq 'terminator') {23} elseif ($node.kind -in 'connector','offpage') {16} elseif ($node.kind -eq 'text') {12} else {14}
        if ($node.kind -eq 'decision') { Draw-Text $g $node.text ($x + 30) ($y + 22) ($w - 60) ($h - 44) $size }
        else { Draw-Text $g $node.text ($x + 4) ($y + 3) ($w - 8) ($h - 6) $size }
    }
    $file = Join-Path $previewDir ('page-{0:d2}.png' -f ($index + 1))
    $bitmap.Save($file, [System.Drawing.Imaging.ImageFormat]::Png)
    $mg.DrawImage($bitmap, [int](($index % 3) * 510), [int]([Math]::Floor($index / 3) * 660), 510, 660)
    $g.Dispose(); $bitmap.Dispose(); $index++
}
$montage.Save((Join-Path $previewDir 'overview.png'), [System.Drawing.Imaging.ImageFormat]::Png)
$mg.Dispose(); $montage.Dispose(); $pen.Dispose(); $format.Dispose()
Write-Output "Rendered $index pages and overview."
