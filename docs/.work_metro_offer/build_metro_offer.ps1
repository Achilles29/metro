param([string]$Only='all')
Set-StrictMode -Version Latest
$ErrorActionPreference='Stop'
. "$PSScriptRoot\word_helpers.ps1"
$BaseDir='C:\xampp\htdocs\metro\docs'
$TemplatePath=Join-Path $BaseDir 'KOP CV.ARX.docx'
$DateText='9 November 2026'
$Company='CV. FATIH ROYAL X MEDIA'
$Director='YULIA ERMA SURYANI'
$SignPlace='Sleman'
$PackageName='Pengadaan Sistem Informasi Pelayanan Metrologi Legal Kabupaten Rembang'
$ProductName='Metro Rembang'
function Rgb([int]$r,[int]$g,[int]$b){return $r+256*$g+65536*$b}
$Black=Rgb 0 0 0; $White=Rgb 255 255 255; $Gray=Rgb 217 217 217
$Accent=Rgb 36 65 86; $Pale=Rgb 239 244 248
function Rupiah([long]$v){return 'Rp'+$v.ToString('N0',[Globalization.CultureInfo]::GetCultureInfo('id-ID'))}
function Row($cells){return [pscustomobject]@{Kind='body';Cells=$cells}}
function Head($cells){return [pscustomobject]@{Kind='header';Cells=$cells}}
function Total($label,$value,$merge){return [pscustomobject]@{Kind='total';Cells=@($label,(Rupiah $value));MergeTo=$merge}}
function P($d,$t){Add-Paragraph $d $t 'Normal' 0 0 6 | Out-Null}
function HeadingMain($d,$t){Add-Paragraph $d $t 'Heading 1' 0 9 5 | Out-Null}
function H2($d,$t){Add-Paragraph $d $t 'Heading 2' 0 7 3 | Out-Null}
function T($d,$rows,$widths,$aligns,$size=10){Add-Table $d $rows $widths $aligns $Accent $Pale $size | Out-Null}
function Finish($d,$path){$d.Fields.Update()|Out-Null; $d.Save(); $d.Close($false); Write-Output $path}
function Title($d,$title,[bool]$kop=$true){
    Add-Paragraph $d $title 'Title' 1 $(if($kop){75}else{0}) 5 | Out-Null
    Add-Paragraph $d $PackageName 'Normal' 1 0 12 $true 11.5 | Out-Null
}
function Tune($d){
    $d.Styles.Item('Normal').Font.Size=11
    $d.Styles.Item('Title').Font.Size=16
    $d.Styles.Item('Heading 1').Font.Size=13
    $d.Styles.Item('Heading 2').Font.Size=11.5
}

$Modules=@(
    [pscustomobject]@{No=1;Name='Beranda dan akses layanan';Hps=2800000;Spec='Halaman awal responsif, pengenalan layanan tera dan tera ulang, serta akses pendaftaran dan ruang pemohon.';Detail='Beranda memperkenalkan Metro Rembang dan mengarahkan pengguna ke layanan. Halaman menyesuaikan layar ponsel dan desktop. Materi dasar layanan disepakati dengan unit pengelola sebelum digunakan.'},
    [pscustomobject]@{No=2;Name='Akun dan profil pemohon';Hps=3200000;Spec='Registrasi, login, profil orang atau organisasi, alamat, kontak, dan perubahan kata sandi.';Detail='Pemohon mempunyai akun dan profil yang dipakai saat mengirim permohonan. Login memakai kata sandi yang disimpan dalam bentuk hash, pembatasan percobaan, dan sesi pengguna. Pendaftaran tidak mencakup OTP atau verifikasi email otomatis.'},
    [pscustomobject]@{No=3;Name='Pengajuan tera dan tera ulang';Hps=5800000;Spec='Pilihan layanan kantor/lapangan, surat permohonan PDF, data pemilik, jenis alat, merek, model, kapasitas, dan jumlah.';Detail='Formulir menerima beberapa baris jenis alat ukur, takar, timbang, dan perlengkapannya (UTTP). Jumlah dipecah menjadi unit fisik yang dapat diproses terpisah. Batas dasar adalah 20 baris jenis dan total 100 unit per permohonan.'},
    [pscustomobject]@{No=4;Name='Pelacakan unit dan riwayat proses';Hps=4800000;Spec='Identitas unit turunan, status per unit, catatan perubahan, serta ringkasan status permohonan induk.';Detail='Setiap unit menyimpan urutan, status, waktu perubahan, pelaksana, dan catatan proses. Permohonan induk menampilkan ringkasan unit-unitnya. Perubahan status mengikuti transisi dan kewenangan yang dibatasi pada server.'},
    [pscustomobject]@{No=5;Name='Nomor dan bukti permohonan';Hps=3000000;Spec='Urutan nomor tahunan dalam transaksi basis data serta bukti permohonan PDF yang dapat diunduh pemilik.';Detail='Nomor dibuat dengan penguncian urutan di dalam transaksi basis data untuk mencegah nomor ganda. Bukti PDF menampilkan identitas pengajuan dan rincian alat. Bukti permohonan tidak memuat fungsi tagihan atau pembayaran.'},
    [pscustomobject]@{No=6;Name='Verifikasi dan perbaikan surat';Hps=4400000;Spec='Antrean masuk, pemeriksaan kelengkapan, catatan kekurangan, unggah ulang surat, dan pengembalian ke verifikasi.';Detail='Petugas memeriksa permohonan melalui halaman detail. Jika surat perlu diperbaiki, pemohon melihat catatan dan mengunggah pembaruan. Unit yang memerlukan perbaikan kembali ke verifikasi setelah pengiriman ulang berhasil.'},
    [pscustomobject]@{No=7;Name='Antrean kerja dan penugasan teknis';Hps=4200000;Spec='Pencarian dan filter antrean, penugasan per unit, penggantian petugas, serta daftar kerja teknisi sesuai tugas aktif.';Detail='Koordinator atau administrator memilih petugas teknis aktif untuk unit yang siap dikerjakan. Pergantian tugas dicatat. Teknisi melihat pekerjaan yang ditugaskan kepadanya. Pilihan layanan lapangan merekam alamat, tanpa kalender kunjungan atau optimasi rute.'},
    [pscustomobject]@{No=8;Name='Cerapan dan dokumen SKHP';Hps=5200000;Spec='Unggah cerapan internal, pencatatan nomor SKHP, berkas per unit, penggantian dokumen, dan unduhan setelah selesai.';Detail='Cerapan adalah dokumen internal pengujian. Surat Keterangan Hasil Pengujian (SKHP) diunggah oleh peran penerbit atau administrator dan dihubungkan ke unit. Penyelesaian mensyaratkan catatan SKHP yang valid; pengesahan substansi tetap dilakukan pejabat berwenang.'},
    [pscustomobject]@{No=9;Name='Akun petugas dan perlindungan dokumen';Hps=4800000;Spec='Peran internal, penonaktifan akun, kontrol akses per pemohon/penugasan, validasi PDF, dan penyimpanan privat.';Detail='Administrator mengelola akun verifikator, teknisi, koordinator, dan penerbit. Unduhan diperiksa menurut pemilik, jenis dokumen, peran, dan penugasan. Berkas PDF dibatasi maksimal 5 MB dan ditempatkan di luar direktori web publik.'},
    [pscustomobject]@{No=10;Name='Dashboard operasional petugas';Hps=4800000;Spec='Ringkasan verifikasi, pengujian, pemeriksaan hasil, perbaikan, volume bulanan, dan navigasi responsif.';Detail='Angka dashboard mengacu pada status unit dan waktu pencatatan. Statistik teknisi mengikuti tugas aktifnya. Waktu pembaruan ditampilkan. Metrik tenggat dan jadwal harian tidak dicantumkan sebelum dasar SLA dan kalender ditetapkan.'},
    [pscustomobject]@{No=11;Name='Laporan periode dan ekspor';Hps=4400000;Spec='Rekap permohonan bulanan, jenis layanan, jumlah unit, status terkini, serta ekspor CSV untuk peran berwenang.';Detail='Laporan menghitung permohonan menurut tanggal pengiriman pada bulan terpilih dan status terkini unit dalam permohonan tersebut. Administrator dan koordinator dapat mengekspor CSV. Ekspor tidak memuat identitas pemohon dan tindakannya dicatat.'},
    [pscustomobject]@{No=12;Name='Pemberitahuan umpan balik dan audit';Hps=4600000;Spec='Notifikasi dalam aplikasi, pemberitahuan perubahan status, penilaian layanan 1 sampai 5, dan audit tindakan penting.';Detail='Pemohon menerima pemberitahuan di aplikasi saat proses berubah. Umpan balik dapat dikirim setelah seluruh unit selesai. Catatan audit membantu menelusuri tindakan penting. Pengiriman WhatsApp/email dan pengelolaan tiket pengaduan merupakan pengembangan terpisah.'}
)
$Support=@(
    [pscustomobject]@{No=13;Name='Pemasangan aplikasi dan basis data';Unit='Paket';Vol=1;Price=3000000;Spec='Instalasi rilis, skema database, dependensi PDF, HTTPS, sesi, dan penyimpanan dokumen privat pada server yang disediakan.'},
    [pscustomobject]@{No=14;Name='Penyesuaian alur dan data referensi';Unit='Paket';Vol=1;Price=3000000;Spec='Penyelarasan istilah status, jenis UTTP, profil layanan, akun awal, serta format bukti berdasarkan bahan yang disetujui UPT.'},
    [pscustomobject]@{No=15;Name='Pendampingan penggunaan';Unit='Bulan';Vol=12;Price=300000;Spec='Konsultasi, penerimaan laporan kendala, pencatatan permintaan, dan arahan penggunaan bagi pengelola selama 12 bulan.'},
    [pscustomobject]@{No=16;Name='Pemeliharaan fungsi aplikasi';Unit='Bulan';Vol=12;Price=300000;Spec='Perbaikan kesalahan pada fungsi yang diserahkan, pemeriksaan dampak perbaikan, dan pencatatan pembaruan selama 12 bulan.'},
    [pscustomobject]@{No=17;Name='Pelatihan operasional';Unit='Sesi';Vol=2;Price=600000;Spec='Satu sesi alur permohonan dan verifikasi serta satu sesi penugasan, dokumen, akun, dan pelaporan.'},
    [pscustomobject]@{No=18;Name='Pengujian dan penerimaan bersama';Unit='Paket';Vol=1;Price=1500000;Spec='Uji alur ujung ke ujung, nomor permohonan, status per unit, hak akses, unggah/unduh, dan perbaikan temuan dalam ruang lingkup.'},
    [pscustomobject]@{No=19;Name='Panduan dan dokumentasi teknis';Unit='Paket';Vol=1;Price=1200000;Spec='Panduan pemohon/petugas, kamus status, struktur data, konfigurasi lingkungan, serta prosedur cadangan dan pemulihan.'},
    [pscustomobject]@{No=20;Name='Penyerahan rilis dan administrasi';Unit='Paket';Vol=1;Price=900000;Spec='Paket rilis, daftar dependensi, daftar konfigurasi, hasil uji, daftar dokumen, dan bahan berita acara serah terima.'}
)
$Groups=@(
    [pscustomobject]@{Name='Aplikasi pelayanan dan kendali proses';Hps=52000000;Offer=51600000},
    [pscustomobject]@{Name='Penerapan dan penyesuaian awal';Hps=6000000;Offer=5950000},
    [pscustomobject]@{Name='Pendampingan pemeliharaan dan pelatihan';Hps=8400000;Offer=8350000},
    [pscustomobject]@{Name='Pengujian dokumentasi dan penyerahan';Hps=3600000;Offer=3550000}
)
function PriceTable($d,[bool]$hps=$false){
    $rows=@((Head @('Komponen pekerjaan','Jumlah')))
    foreach($g in $Groups){$v=if($hps){$g.Hps}else{$g.Offer};$rows+=Row @($g.Name,(Rupiah $v))}
    $rows+=Total $(if($hps){'TOTAL HPS'}else{'TOTAL PENAWARAN'}) $(if($hps){70000000}else{69450000}) 1
    T $d $rows @(345,135) @(0,2) 10.5
}
function Build-Offer($w){
    $path=Join-Path $BaseDir '01. Penawaran Sistem Informasi Metrologi CV ARX.docx'
    $d=New-LetterheadDocument $w $path; Tune $d
    Configure-HeaderFooter $d 'Metro Rembang | Penawaran CV ARX' $true
    Title $d 'PENAWARAN APLIKASI METRO REMBANG'
    Add-Paragraph $d ('Sleman, '+$DateText) 'Normal' 2 0 8 | Out-Null
    P $d 'Perihal: Penawaran sistem pelayanan tera dan tera ulang'
    P $d 'Lampiran: Penawaran Teknis dan Spesifikasi Teknis'
    P $d 'Yth. Kepala Dinas Perdagangan, Koperasi dan UKM Kabupaten Rembang'
    P $d 'di Rembang'
    P $d 'Dengan hormat,'
    P $d 'Kami mengajukan penawaran Metro Rembang untuk membantu unit pelayanan metrologi legal mengelola permohonan tera dan tera ulang. Aplikasi menghubungkan pengajuan pemohon dengan antrean petugas, pencatatan proses tiap unit UTTP, serta penyediaan dokumen hasil yang boleh diunduh.'
    P $d 'Nilai penawaran kami sebesar Rp69.450.000 (enam puluh sembilan juta empat ratus lima puluh ribu rupiah), termasuk komponen pekerjaan dan pajak yang berlaku. Nilai ini berada dalam pagu dan HPS Rp70.000.000.'
    P $d 'Cakupan pekerjaan meliputi 12 kelompok fungsi aplikasi, pemasangan dan penyesuaian awal, dua sesi pelatihan, dukungan serta pemeliharaan selama 12 bulan, pengujian bersama, dokumentasi, dan penyerahan rilis. Rincian keluaran dan batas pekerjaan dijelaskan dalam lampiran.'
    P $d 'Melalui pencatatan per unit, petugas dapat melanjutkan pekerjaan dan menunjukkan hasil masing-masing alat tanpa kehilangan hubungan dengan permohonan induknya. Pemeriksaan teknis dan pengesahan SKHP tetap mengikuti keputusan petugas atau pejabat yang berwenang.'
    P $d 'Kami siap membahas kesesuaian alur dengan SOP UPT dan menyiapkan pelaksanaan berdasarkan ruang lingkup yang disepakati. Atas perhatian dan kesempatan yang diberikan, kami mengucapkan terima kasih.'
    Add-Signature $d $Company $Director 'Direktur' | Out-Null
    Add-PageBreak $d
    HeadingMain $d 'Ruang lingkup pelayanan yang ditawarkan'
    P $d 'Metro Rembang menempatkan permohonan sebagai berkas induk dan setiap UTTP sebagai unit kerja. Struktur ini memungkinkan perbaikan berkas, penugasan, pemeriksaan hasil, dan unduhan SKHP ditelusuri pada alat yang benar.'
    $rows=@((Head @('Tahap layanan','Keluaran aplikasi')),
      (Row @('Pemohon mengajukan','Profil pemohon, pilihan tera/tera ulang, alamat layanan, surat PDF, dan daftar alat.')),
      (Row @('Permohonan diterima','Nomor tahunan, bukti PDF, serta identitas terpisah untuk setiap unit fisik.')),
      (Row @('Petugas memeriksa','Antrean terfilter, catatan kekurangan, dan pengiriman ulang surat oleh pemohon.')),
      (Row @('Pekerjaan dilaksanakan','Penugasan teknisi, perubahan status, riwayat proses, dan cerapan internal.')),
      (Row @('Hasil disampaikan','SKHP per unit, akses unduhan setelah selesai, dan notifikasi dalam aplikasi.')),
      (Row @('Kegiatan dievaluasi','Dashboard, laporan periode, ekspor terbatas, umpan balik, dan catatan audit.')))
    T $d $rows @(135,345) @(0,0) 11
    HeadingMain $d 'Susunan harga penawaran'
    PriceTable $d
    P $d 'Terbilang: Enam puluh sembilan juta empat ratus lima puluh ribu rupiah.'
    P $d 'Harga sudah memperhitungkan pajak yang berlaku. Pemisahan pajak dalam dokumen penagihan mengikuti status perpajakan penyedia, dengan total tetap sesuai penawaran.'
    P $d 'Formulir Penawaran Teknis merinci 20 komponen. Spesifikasi Teknis menjelaskan perilaku aplikasi, keluaran penerapan, dan cara memeriksa penyelesaiannya.'
    Add-PageBreak $d
    HeadingMain $d 'Rencana penerapan dan tanggung jawab'
    $rows=@((Head @('Urutan pekerjaan','Hasil yang disepakati')),
      (Row @('1 Konfirmasi proses','Daftar jenis UTTP, istilah status, kewenangan petugas, format dokumen, dan kebutuhan data awal.')),
      (Row @('2 Penyiapan lingkungan','Aplikasi, database, dependensi PDF, HTTPS, akun awal, sesi, dan berkas privat.')),
      (Row @('3 Penyesuaian dan uji','Konfigurasi layanan, pemeriksaan alur beberapa unit, akses antarperan, dan perbaikan temuan.')),
      (Row @('4 Pelatihan dan serah terima','Dua sesi pelatihan, panduan, hasil uji, paket rilis, dan dokumen penyerahan.')),
      (Row @('5 Dukungan operasional','Pendampingan dan pemeliharaan selama 12 bulan sejak tanggal mulai yang ditetapkan dalam kontrak.')))
    T $d $rows @(145,335) @(0,0) 11
    P $d 'Jadwal kalender, penanggung jawab, jam dukungan, sasaran waktu respons, tata cara pembayaran, dan mekanisme penyerahan kode sumber ditetapkan bersama dalam dokumen kontrak.'
    H2 $d 'Bahan dan fasilitas dari instansi'
    P $d 'Instansi menyediakan server atau hosting, domain, akses yang diperlukan, data referensi yang telah diperiksa, SOP, format dokumen, serta petugas untuk uji penerimaan. Materi dan konfigurasi layanan mendapat persetujuan unit pengelola sebelum digunakan.'
    H2 $d 'Batas pekerjaan'
    P $d 'Paket mencakup aplikasi dan dukungan fungsi yang dijelaskan dalam dokumen ini. Pengujian fisik UTTP, pengadaan alat ukur, kunjungan lapangan, pengesahan hasil, digitalisasi arsip massal, dan penetapan tarif layanan dilaksanakan oleh instansi sesuai kewenangannya.'
    P $d 'GIS, kalender kunjungan, pengingat tera ulang otomatis, integrasi WhatsApp/email, SSO/OTP, tanda tangan elektronik, penerbitan SKHP otomatis, pembayaran daring, serta integrasi API baru memerlukan ruang lingkup dan pembiayaan tersendiri.'
    P $d 'Biaya berlangganan infrastruktur, domain, sertifikat berbayar, internet, gateway pesan, dan lisensi tambahan pihak ketiga belum termasuk. Perubahan SOP yang menambah modul atau mengubah alur utama dibahas sebagai pekerjaan tambahan.'
    Finish $d $path
}
function Build-Hps($w){
    $path=Join-Path $BaseDir '02. HPS Sistem Informasi Metrologi.docx'
    $d=New-BlankDocument $w $path; Tune $d
    Configure-HeaderFooter $d 'Metro Rembang | Rincian HPS' $false
    Title $d 'HARGA PERKIRAAN SENDIRI' $false
    P $d 'Dinas Perdagangan, Koperasi dan UKM Kabupaten Rembang'
    P $d ('Tahun Anggaran 2026 | Tanggal penyusunan '+$DateText)
    Add-Paragraph $d 'Pagu anggaran Rp70.000.000 | Total HPS Rp70.000.000' 'Normal' 0 0 9 $true | Out-Null
    P $d 'Rincian berikut mengalokasikan biaya aplikasi pelayanan tera dan tera ulang beserta penerapan, dukungan pengguna, dan penyerahan hasil. Volume dihitung berdasarkan kelompok fungsi dan keluaran pekerjaan.'
    HeadingMain $d 'Aplikasi pelayanan dan kendali proses'
    $rows=@((Head @('No','Uraian komponen','Vol','Satuan','Harga satuan','Jumlah')))
    foreach($m in $Modules){$rows+=Row @($m.No,$m.Name,1,'Modul',(Rupiah $m.Hps),(Rupiah $m.Hps))}
    $rows+=Total 'Subtotal aplikasi' 52000000 5
    T $d $rows @(25,203,25,43,92,92) @(1,0,1,1,2,2) 10
    HeadingMain $d 'Rekapitulasi anggaran'
    PriceTable $d $true
    P $d 'Terbilang: Tujuh puluh juta rupiah.'
    Add-PageBreak $d
    HeadingMain $d 'Penerapan dukungan dan penyerahan'
    $rows=@((Head @('No','Uraian komponen','Vol','Satuan','Harga satuan','Jumlah')))
    foreach($s in $Support){$rows+=Row @($s.No,$s.Name,$s.Vol,$s.Unit,(Rupiah $s.Price),(Rupiah ($s.Price*$s.Vol)))}
    $rows+=Total 'Subtotal layanan pendukung' 18000000 5
    T $d $rows @(25,203,25,43,92,92) @(1,0,1,1,2,2) 10
    HeadingMain $d 'Dasar alokasi dan ketentuan perhitungan'
    P $d 'Alokasi aplikasi mempertimbangkan pengajuan multi-UTTP, penomoran transaksi, pembatasan perubahan status, penugasan, pemisahan dokumen internal, SKHP per unit, dan laporan berbasis periode. Dukungan dihitung 12 bulan, pelatihan dua sesi, dan keluaran pengujian serta dokumentasi masing-masing satu paket.'
    P $d 'Harga satuan merupakan estimasi per komponen. Pejabat yang berwenang melengkapi bukti pembanding dan menilai kewajaran biaya sebelum HPS ditetapkan. Dokumen ini tidak menggantikan penetapan resmi tersebut.'
    P $d 'Seluruh nilai telah memperhitungkan pajak yang berlaku. Dasar pengenaan dan rincian pajak mengikuti status perpajakan penyedia serta dokumen penagihan, tanpa melampaui total HPS Rp70.000.000.'
    P $d 'Server/hosting, domain, perangkat keras, internet, biaya kanal pesan, pengujian fisik alat, kunjungan lapangan, digitalisasi arsip massal, dan integrasi baru tidak termasuk dalam rincian ini. Nilai kontrak mengikuti hasil proses pengadaan.'
    $savedPlace=$SignPlace; $script:SignPlace='Rembang'
    Add-Signature $d 'Pejabat Pembuat Komitmen' '(........................................)' 'NIP. ................................' | Out-Null
    $script:SignPlace=$savedPlace
    Finish $d $path
}
function Build-Form($w){
    $path=Join-Path $BaseDir '03. Formulir Penawaran Teknis Metrologi CV ARX.docx'
    $d=New-LetterheadDocument $w $path; Tune $d
    Configure-HeaderFooter $d 'Metro Rembang | Penawaran teknis CV ARX' $true
    Title $d 'FORMULIR PENAWARAN TEKNIS'
    P $d 'Penyedia: CV. FATIH ROYAL X MEDIA | Aplikasi: Metro Rembang'
    P $d 'Kami menawarkan komponen berikut sebagai satu paket pekerjaan. Spesifikasi Teknis menjadi acuan rincian perilaku dan batas setiap komponen.'
    HeadingMain $d 'Layanan pemohon dan penerimaan berkas'
    $rows=@((Head @('No','Komponen','Kemampuan yang ditawarkan','Volume')))
    foreach($m in $Modules[0..5]){$rows+=Row @($m.No,$m.Name,$m.Spec,'1 modul')}
    T $d $rows @(25,135,260,60) @(1,0,0,1) 11
    Add-PageBreak $d
    HeadingMain $d 'Pekerjaan petugas dan pengendalian layanan'
    $rows=@((Head @('No','Komponen','Kemampuan yang ditawarkan','Volume')))
    foreach($m in $Modules[6..11]){$rows+=Row @($m.No,$m.Name,$m.Spec,'1 modul')}
    T $d $rows @(25,135,260,60) @(1,0,0,1) 11
    HeadingMain $d 'Nilai penawaran paket'
    PriceTable $d
    P $d 'Total Rp69.450.000 sudah mencakup seluruh komponen formulir, layanan pendukung pada halaman berikutnya, dan pajak yang berlaku.'
    Add-PageBreak $d
    HeadingMain $d 'Penerapan dan layanan pendukung'
    $rows=@((Head @('No','Komponen','Keluaran','Volume')))
    foreach($s in $Support){$rows+=Row @($s.No,$s.Name,$s.Spec,([string]$s.Vol+' '+$s.Unit.ToLower()))}
    T $d $rows @(25,135,260,60) @(1,0,0,1) 10.5
    P $d 'Pelaksanaan dan penerimaan mengikuti spesifikasi yang disepakati. Tambahan fungsi di luar daftar ini memerlukan penetapan ruang lingkup tersendiri.'
    Add-Signature $d $Company $Director 'Direktur' | Out-Null
    Finish $d $path
}
function Build-Spec($w){
    $path=Join-Path $BaseDir '04. Spesifikasi Teknis Sistem Informasi Metrologi CV ARX.docx'
    $d=New-LetterheadDocument $w $path; Tune $d
    Configure-HeaderFooter $d 'Metro Rembang | Spesifikasi teknis CV ARX' $true
    Title $d 'SPESIFIKASI TEKNIS APLIKASI METRO'
    P $d 'Dokumen ini menjelaskan fungsi aplikasi pelayanan tera dan tera ulang, keluaran penerapan, persyaratan lingkungan, dan uji penerimaan. Lingkup terdiri dari 12 kelompok fungsi aplikasi dan 8 komponen layanan pendukung.'
    HeadingMain $d 'A Layanan pemohon dan penerimaan berkas'
    foreach($m in $Modules[0..5]){H2 $d ($m.No.ToString()+' '+$m.Name);P $d $m.Detail}
    Add-PageBreak $d
    HeadingMain $d 'B Pekerjaan petugas dan dokumen hasil'
    foreach($m in $Modules[6..11]){H2 $d ($m.No.ToString()+' '+$m.Name);P $d $m.Detail}
    H2 $d 'Pemisahan wewenang'
    P $d 'Peran pemohon, verifikator, teknisi, koordinator, penerbit, dan administrator mempunyai akses berbeda. Penyedia menyiapkan fungsi pencatatan dan pembatasan akses. Keputusan teknis, format resmi, serta pengesahan hasil ditentukan oleh instansi.'
    Add-PageBreak $d
    HeadingMain $d 'C Penerapan dan keluaran pendukung'
    foreach($s in $Support){H2 $d ($s.No.ToString()+' '+$s.Name);P $d $s.Spec}
    HeadingMain $d 'D Lingkungan aplikasi'
    $rows=@((Head @('Komponen','Ketentuan teknis')),
      (Row @('Aplikasi','Web responsif berbasis PHP dan CodeIgniter 3; versi runtime dan dependensi diperiksa pada server penempatan.')),
      (Row @('Basis data dan PDF','MySQL/MariaDB dengan transaksi dan skema migrasi; Dompdf untuk bukti permohonan.')),
      (Row @('Web dan penyimpanan','HTTPS, rewrite URL, konfigurasi rahasia di luar web root, serta direktori dokumen dan sesi privat.')),
      (Row @('Keamanan akses','Hash kata sandi, CSRF, validasi server, pembatasan login, otorisasi per objek, dan audit tindakan penting.')),
      (Row @('Operasi','Catatan versi, log, cadangan basis data/berkas, dan prosedur pemulihan sesuai fasilitas instansi.')))
    T $d $rows @(110,370) @(0,0) 10.5
    Add-PageBreak $d
    HeadingMain $d 'E Pemeriksaan penerimaan'
    P $d 'Uji dilakukan bersama perwakilan instansi menggunakan data uji yang disepakati. Hasil pemeriksaan dicatat agar setiap temuan, perbaikan, dan keputusan penerimaan dapat ditelusuri.'
    $rows=@((Head @('Skenario','Hasil yang harus dapat diperiksa')),
      (Row @('Pengajuan beberapa unit','Jumlah unit sesuai isian; nomor unik; bukti PDF tersedia; pemohon lain tidak dapat membuka permohonan.')),
      (Row @('Perbaikan dan transisi','Catatan kekurangan terlihat; surat dapat diunggah ulang; perubahan status tanpa peran yang tepat ditolak.')),
      (Row @('Penugasan teknisi','Unit muncul pada petugas yang ditugaskan; pergantian tercatat; akses di luar tugas aktif dibatasi.')),
      (Row @('Cerapan dan SKHP','Cerapan tetap internal; SKHP terkait unit yang benar; pemohon mengunduh setelah status unit selesai.')),
      (Row @('Rekap dan ekspor','Definisi periode/status sesuai laporan; ekspor CSV terbatas pada administrator/koordinator dan tercatat.')),
      (Row @('Dukungan operasi','Akun, konfigurasi, panduan, paket rilis, serta prosedur cadangan dan pemulihan dapat diperiksa.')))
    T $d $rows @(130,350) @(0,0) 10.5
    H2 $d 'Kriteria penyelesaian'
    P $d 'Pekerjaan diterima setelah fungsi dalam ruang lingkup lulus uji yang disepakati, temuan kritis diselesaikan, dua sesi pelatihan terlaksana, dan paket penyerahan diterima. Data uji dibersihkan atau ditandai setelah pemeriksaan. Dukungan 12 bulan mengikuti tanggal mulai dalam kontrak.'
    HeadingMain $d 'F Batas layanan dan ketergantungan'
    P $d 'Instansi menyiapkan SOP, jenis UTTP, istilah status, format dokumen, penanggung jawab, data awal, dan fasilitas server. Pengujian fisik alat serta pengesahan dokumen tetap dilakukan petugas berwenang. Aplikasi tidak menerbitkan SKHP sah secara otomatis.'
    P $d 'Paket tidak mencakup GIS, kalender kunjungan, pengingat tera ulang otomatis, WhatsApp/email gateway, SSO/OTP, tanda tangan elektronik, pembayaran, API baru, registri UTTP lintas permohonan, atau digitalisasi arsip massal. Infrastruktur dan biaya layanan pihak ketiga disediakan instansi atau disepakati terpisah.'
    P $d 'Jam bantuan, sasaran respons, retensi data, hak penggunaan, penyerahan kode sumber, dan penanganan perubahan ditetapkan dalam kontrak. Pemeliharaan berlaku bagi fungsi yang diserahkan dan tidak mencakup penambahan modul baru.'
    Add-Signature $d $Company $Director 'Direktur' | Out-Null
    Finish $d $path
}
$initialHash=(Get-FileHash -LiteralPath $TemplatePath -Algorithm SHA256).Hash
if($initialHash -ne 'D23F2247881011C3BD38224B929427D1F6D7719055E791155E40CAE163454705'){throw 'Reference letterhead changed.'}
$sum=($Modules|Measure-Object Hps -Sum).Sum+(($Support|ForEach-Object{$_.Vol*$_.Price})|Measure-Object -Sum).Sum
if($sum -ne 70000000){throw "HPS does not reconcile: $sum"}
if(($Groups|Measure-Object Offer -Sum).Sum -ne 69450000){throw 'Offer does not reconcile.'}
$w=New-Object -ComObject Word.Application; $w.Visible=$false; $w.DisplayAlerts=0
try{
    if($Only -in @('all','01')){Build-Offer $w}
    if($Only -in @('all','02')){Build-Hps $w}
    if($Only -in @('all','03')){Build-Form $w}
    if($Only -in @('all','04')){Build-Spec $w}
}finally{$w.Quit();[Runtime.InteropServices.Marshal]::FinalReleaseComObject($w)|Out-Null;[gc]::Collect();[gc]::WaitForPendingFinalizers()}
if((Get-FileHash -LiteralPath $TemplatePath -Algorithm SHA256).Hash -ne $initialHash){throw 'Source letterhead altered.'}
