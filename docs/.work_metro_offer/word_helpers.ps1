function Set-PageSystem($doc) {
    foreach ($section in $doc.Sections) {
        $ps = $section.PageSetup
        $ps.PageWidth = 595.3
        $ps.PageHeight = 935.5
        $ps.Orientation = 0
        $ps.LeftMargin = 56.7
        $ps.RightMargin = 56.7
        $ps.TopMargin = 56.7
        $ps.BottomMargin = 56.7
        $ps.HeaderDistance = 22.7
        $ps.FooterDistance = 22.7
    }
}

function Set-Styles($doc) {
    $normal = $doc.Styles.Item('Normal')
    $normal.Font.Name = 'Calibri'; $normal.Font.Size = 10.5; $normal.Font.Color = $Black
    $normal.ParagraphFormat.SpaceAfter = 5
    $normal.ParagraphFormat.LineSpacingRule = 0
    $title = $doc.Styles.Item('Title')
    $title.Font.Name = 'Calibri'; $title.Font.Size = 15; $title.Font.Bold = -1; $title.Font.Color = $Black
    $title.ParagraphFormat.Alignment = 1; $title.ParagraphFormat.SpaceAfter = 5
    $h1 = $doc.Styles.Item('Heading 1')
    $h1.Font.Name = 'Calibri'; $h1.Font.Size = 12; $h1.Font.Bold = -1; $h1.Font.Color = $Black
    $h1.ParagraphFormat.SpaceBefore = 10; $h1.ParagraphFormat.SpaceAfter = 4; $h1.ParagraphFormat.KeepWithNext = -1
    $h2 = $doc.Styles.Item('Heading 2')
    $h2.Font.Name = 'Calibri'; $h2.Font.Size = 11; $h2.Font.Bold = -1; $h2.Font.Color = $Black
    $h2.ParagraphFormat.SpaceBefore = 7; $h2.ParagraphFormat.SpaceAfter = 3; $h2.ParagraphFormat.KeepWithNext = -1
}

function Add-PageField($footer) {
    $footer.Range.Text = 'Halaman '
    $footer.Range.Font.Name = 'Calibri'; $footer.Range.Font.Size = 8.5
    $footer.Range.ParagraphFormat.Alignment = 2
    $r = $footer.Range.Duplicate
    $r.Collapse(0)
    $footer.Range.Fields.Add($r, 33) | Out-Null
}

function Configure-HeaderFooter($doc, [string]$runningHeader, [bool]$letterheadFirst) {
    foreach ($section in $doc.Sections) {
        $section.PageSetup.DifferentFirstPageHeaderFooter = $(if ($letterheadFirst) {-1} else {0})
        $primary = $section.Headers.Item(1)
        $primary.Range.Text = $runningHeader
        $primary.Range.Font.Name = 'Calibri'; $primary.Range.Font.Size = 8.5; $primary.Range.Font.Color = $Black
        $primary.Range.ParagraphFormat.Alignment = 2
        if ($letterheadFirst) { $section.Headers.Item(2).Range.Text = '' }
        Add-PageField $section.Footers.Item(1)
        if ($letterheadFirst) { Add-PageField $section.Footers.Item(2) }
    }
}

function Add-Paragraph($doc, [string]$text, [string]$style='Normal', [int]$align=0, [double]$before=0, [double]$after=5, [bool]$bold=$false, [double]$size=0) {
    $last = $doc.Paragraphs.Item($doc.Paragraphs.Count)
    $lastText = ($last.Range.Text -replace '[\r\a]+$','').Trim()
    if ($lastText.Length -eq 0 -and -not ($doc.Paragraphs.Count -eq 1 -and $doc.Shapes.Count -gt 0)) {
        $p = $last
    } else {
        $insert = $doc.Range($doc.Content.End - 1, $doc.Content.End - 1)
        $insert.InsertParagraphAfter()
        $p = $doc.Paragraphs.Item($doc.Paragraphs.Count)
    }
    $p.Range.Text = $text
    try { $p.Range.Style = $style } catch { $p.Range.Style = 'Normal' }
    $p.Alignment = $align
    $p.Format.SpaceBefore = $before
    $p.Format.SpaceAfter = $after
    if ($bold) { $p.Range.Font.Bold = -1 }
    if ($size -gt 0) { $p.Range.Font.Size = $size }
    return $p
}

function Add-Blank($doc, [double]$after=3) {
    $insert = $doc.Range($doc.Content.End - 1, $doc.Content.End - 1)
    $insert.InsertParagraphAfter()
    $p = $doc.Paragraphs.Item($doc.Paragraphs.Count); $p.Range.Text=''; $p.Format.SpaceAfter=$after; return $p
}

function Add-PageBreak($doc) {
    $r = $doc.Range($doc.Content.End - 1, $doc.Content.End - 1)
    $r.InsertBreak(7)
}

function Set-Cell($cell, [string]$text, [int]$align, [double]$size, [bool]$bold, [int]$fill, [int]$fontColor) {
    $cell.Range.Text = $text
    $cell.Range.Font.Name = 'Calibri'; $cell.Range.Font.Size = $size; $cell.Range.Font.Color = $fontColor
    $cell.Range.Font.Bold = $(if ($bold) {-1} else {0})
    $cell.Range.ParagraphFormat.Alignment = $align
    $cell.Range.ParagraphFormat.SpaceBefore = 0; $cell.Range.ParagraphFormat.SpaceAfter = 0
    $cell.VerticalAlignment = 1
    $cell.Shading.BackgroundPatternColor = $fill
}

function Add-Table($doc, $rows, [double[]]$widths, [int[]]$aligns, [int]$accent, [int]$pale, [double]$fontSize=9.5) {
    $cols = $widths.Count
    $r = $doc.Range($doc.Content.End - 1, $doc.Content.End - 1)
    $table = $doc.Tables.Add($r, $rows.Count, $cols)
    $table.AllowAutoFit = 0
    $table.TopPadding = 4; $table.BottomPadding = 4; $table.LeftPadding = 5; $table.RightPadding = 5
    foreach ($borderId in @(-1,-2,-3,-4,-5,-6)) {
        try {
            $table.Borders.Item($borderId).LineStyle = 1
            $table.Borders.Item($borderId).Color = $Gray
        } catch {}
    }
    for ($c=1; $c -le $cols; $c++) { $table.Columns.Item($c).PreferredWidthType = 3; $table.Columns.Item($c).PreferredWidth = $widths[$c-1] }
    $rowIndex = 0
    foreach ($spec in $rows) {
        $rowIndex++
        $kind = $spec.Kind
        if ($kind -eq 'group') {
            $table.Cell($rowIndex,1).Merge($table.Cell($rowIndex,$cols))
            Set-Cell $table.Cell($rowIndex,1) $spec.Cells[0] 0 $fontSize $true $pale $Black
            $table.Rows.Item($rowIndex).Range.ParagraphFormat.KeepWithNext = -1
            continue
        }
        if ($kind -eq 'total') {
            $mergeTo = $spec.MergeTo
            if ($mergeTo -gt 1) { $table.Cell($rowIndex,1).Merge($table.Cell($rowIndex,$mergeTo)) }
            Set-Cell $table.Cell($rowIndex,1) $spec.Cells[0] 2 $fontSize $true $pale $Black
            $remaining = $cols - $mergeTo
            for ($k=1; $k -le $remaining; $k++) { Set-Cell $table.Cell($rowIndex,1+$k) $spec.Cells[$k] 2 $fontSize $true $pale $Black }
            continue
        }
        for ($c=1; $c -le $cols; $c++) {
            $fill = if ($kind -eq 'header') {$accent} elseif (($rowIndex % 2) -eq 0) {$pale} else {$White}
            $fontColor = if ($kind -eq 'header') {$White} else {$Black}
            Set-Cell $table.Cell($rowIndex,$c) ([string]$spec.Cells[$c-1]) $aligns[$c-1] $fontSize ($kind -eq 'header') $fill $fontColor
        }
        if ($kind -eq 'header') { $table.Rows.Item($rowIndex).HeadingFormat = -1; $table.Rows.Item($rowIndex).Range.ParagraphFormat.KeepWithNext = -1 }
    }
    $table.Range.ParagraphFormat.WidowControl = -1
    $table.Rows.AllowBreakAcrossPages = 0
    $end = $doc.Range($doc.Content.End - 1, $doc.Content.End - 1)
    $end.InsertParagraphAfter()
    return $table
}

function Add-Signature($doc, [string]$organization, [string]$name, [string]$role) {
    $rows = @(
        [pscustomobject]@{Kind='body'; Cells=@('',($SignPlace + ', ' + $DateText))},
        [pscustomobject]@{Kind='body'; Cells=@('',$organization)},
        [pscustomobject]@{Kind='body'; Cells=@('','')},
        [pscustomobject]@{Kind='body'; Cells=@('','')},
        [pscustomobject]@{Kind='body'; Cells=@('',$name)},
        [pscustomobject]@{Kind='body'; Cells=@('',$role)}
    )
    $t = Add-Table $doc $rows @(240,240) @(0,1) $White $White 10.5
    $t.Borders.Enable = 0
    $t.Cell(5,2).Range.Font.Bold = -1
    return $t
}

function New-LetterheadDocument($word, [string]$outputPath) {
    $doc = $word.Documents.Open($TemplatePath, $false, $false)
    $doc.SaveAs2($outputPath, 16)
    Set-PageSystem $doc
    Set-Styles $doc
    return $doc
}

function New-BlankDocument($word, [string]$outputPath) {
    $doc = $word.Documents.Add()
    $doc.SaveAs2($outputPath, 16)
    Set-PageSystem $doc
    Set-Styles $doc
    return $doc
}
