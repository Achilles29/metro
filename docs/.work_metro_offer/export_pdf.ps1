param([Parameter(Mandatory=$true)][string]$Number,[string]$Run='v1')
$ErrorActionPreference='Stop'
$base='C:\xampp\htdocs\metro\docs'
$f=Get-ChildItem -LiteralPath $base -Filter "$Number.*.docx" -File
if(@($f).Count -ne 1){throw 'Expected one document'}
$out=Join-Path $base ".qa_metro_offer\$Run"
New-Item -ItemType Directory -Force -Path $out | Out-Null
$pdf=Join-Path $out ($Number+'.pdf')
$renderSource=Join-Path $out ('render_source_'+$Number+'.docx')
Copy-Item -LiteralPath $f.FullName -Destination $renderSource -Force
$w=New-Object -ComObject Word.Application
$w.Visible=$false; $w.DisplayAlerts=0
try {
  $d=$w.Documents.Open([string]$renderSource,$false,$true)
  $w.Options.PrintDrawingObjects=$true
  $null=$d.Paragraphs.Item(1).Range.Information(3)
  $d.SaveAs2([string]$pdf,17)
  $d.Close($false)
  [pscustomobject]@{File=$f.Name;Pdf=$pdf}
} finally {$w.Quit();[Runtime.InteropServices.Marshal]::FinalReleaseComObject($w)|Out-Null}
$env:PYTHONPATH='C:\xampp\htdocs\umkm\docs\.qa_umkm_offer\pydeps'
python 'C:\xampp\htdocs\umkm\docs\.qa_umkm_offer\render_pdf.py' $pdf (Join-Path $out ('render_'+$Number))
