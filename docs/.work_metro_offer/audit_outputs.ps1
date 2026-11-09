$ErrorActionPreference='Stop'
Add-Type -AssemblyName System.IO.Compression.FileSystem
$base='C:\xampp\htdocs\metro\docs'
function Inspect($path){
  $z=[IO.Compression.ZipFile]::OpenRead($path)
  try {
    $entry=$z.GetEntry('word/document.xml');$r=New-Object IO.StreamReader($entry.Open())
    try{[xml]$xml=$r.ReadToEnd()}finally{$r.Dispose()}
    $ns=New-Object Xml.XmlNamespaceManager($xml.NameTable)
    $ns.AddNamespace('w','http://schemas.openxmlformats.org/wordprocessingml/2006/main')
    $text=($xml.SelectNodes('//w:t',$ns)|ForEach-Object{$_.InnerText}) -join ' '
    $media=@($z.Entries|Where-Object{$_.FullName -like 'word/media/*'}|ForEach-Object{
      $st=$_.Open();$sha=[Security.Cryptography.SHA256]::Create()
      try{[BitConverter]::ToString($sha.ComputeHash($st)).Replace('-','')}finally{$sha.Dispose();$st.Dispose()}
    })
    [pscustomobject]@{Text=$text;Media=$media;Revisions=$xml.SelectNodes('//w:ins|//w:del',$ns).Count}
  }finally{$z.Dispose()}
}
$ref=Inspect (Join-Path $base 'KOP CV.ARX.docx')
$files=Get-ChildItem -LiteralPath $base -Filter '0*.docx' -File | Sort-Object Name
if($files.Count -ne 4){throw 'Expected four final documents'}
foreach($f in $files){
  $a=Inspect $f.FullName
  if($a.Text -match 'ASJ|PRAYITNO|UMKM|KDKMP|KKPKK|SIKOP|ARFIANTO'){throw "Stale content in $($f.Name)"}
  $supplier=$f.Name -notlike '02.*'
  if($supplier -and ($a.Text -notmatch 'YULIA ERMA SURYANI' -or $a.Text -notmatch 'CV. FATIH ROYAL X MEDIA')){throw 'Supplier identity missing'}
  if($supplier -and -not @($a.Media|Where-Object{$_ -in $ref.Media}).Count){throw 'Letterhead image mismatch'}
  if($a.Revisions){throw 'Tracked changes found'}
  if($f.Name -like '02.*' -and $a.Text -notmatch 'Rp70.000.000'){throw 'HPS missing'}
  if($f.Name -match '^(01|03)' -and $a.Text -notmatch 'Rp69.450.000'){throw 'Offer missing'}
  [pscustomobject]@{File=$f.Name;Bytes=$f.Length;SupplierIdentity=$supplier;LetterheadMatches=(@($a.Media|Where-Object{$_ -in $ref.Media}).Count -gt 0);Status='PASS'}
}
$hash=(Get-FileHash -LiteralPath (Join-Path $base 'KOP CV.ARX.docx')).Hash
if($hash -ne 'D23F2247881011C3BD38224B929427D1F6D7719055E791155E40CAE163454705'){throw 'Source changed'}
