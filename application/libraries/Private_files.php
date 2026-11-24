<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Private_files
{
    private $root = '/var/lib/metro/documents';

    public function save($field)
    {
        if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Pilih berkas PDF yang valid.');
        }
        $file = $_FILES[$field];
        if ($file['size'] < 1 || $file['size'] > 5 * 1024 * 1024) {
            throw new RuntimeException('Ukuran PDF maksimal 5 MB.');
        }
        if (!is_uploaded_file($file['tmp_name'])) throw new RuntimeException('Unggahan tidak valid.');
        $head = file_get_contents($file['tmp_name'], FALSE, NULL, 0, 5);
        $tail = file_get_contents($file['tmp_name'], FALSE, NULL, max(0, $file['size'] - 1024), 1024);
        if ($head !== '%PDF-' || strpos($tail, '%%EOF') === FALSE) {
            throw new RuntimeException('Berkas harus berupa PDF.');
        }
        $key = bin2hex(random_bytes(24)) . '.pdf';
        if (!is_dir($this->root) || !move_uploaded_file($file['tmp_name'], $this->root . '/' . $key)) {
            throw new RuntimeException('Penyimpanan berkas gagal.');
        }
        chmod($this->root . '/' . $key, 0640);
        return array('storage_key' => $key, 'detected_mime' => 'application/pdf',
            'original_name' => substr(basename($file['name']), 0, 200),
            'file_size' => $file['size'], 'sha256' => hash_file('sha256', $this->root . '/' . $key));
    }

    public function path($key)
    {
        if (!preg_match('/^[a-f0-9]{48}\.pdf$/', $key)) return FALSE;
        $path = $this->root . '/' . $key;
        return is_file($path) ? $path : FALSE;
    }

    public function delete($key)
    {
        $path = $this->path($key);
        if ($path) unlink($path);
    }

    public function save_image($field)
    {
        if (!isset($_FILES[$field]) || $_FILES[$field]['error']!==UPLOAD_ERR_OK || !is_uploaded_file($_FILES[$field]['tmp_name']))
            throw new RuntimeException('Pilih foto dari kamera atau galeri.');
        $file=$_FILES[$field];
        if ($file['size']<1 || $file['size']>8*1024*1024) throw new RuntimeException('Foto maksimal 8 MB.');
        $image=@getimagesize($file['tmp_name']);
        $mime=$image['mime'] ?? NULL;
        if (!in_array($mime,array('image/jpeg','image/png'),TRUE) || !$image)
            throw new RuntimeException('Foto harus JPG atau PNG.');
        $key=bin2hex(random_bytes(24)).'.jpg';
        $path=$this->root.'/'.$key;
        if (!is_dir($this->root)) throw new RuntimeException('Penyimpanan foto tidak tersedia.');
        try {
            $size=$this->compress_image($file['tmp_name'],$path,$mime,(int)$image[0],(int)$image[1]);
            chmod($path,0640);
        } catch (RuntimeException $ex) {
            if (is_file($path)) unlink($path);
            throw $ex;
        }
        return array('storage_key'=>$key,'original_name'=>substr(basename($file['name']),0,200),'file_size'=>$size);
    }

    private function compress_image($source,$target,$mime,$width,$height)
    {
        if ($width<1 || $height<1 || $width*$height>20000000)
            throw new RuntimeException('Resolusi foto terlalu besar. Gunakan foto maksimal 20 megapiksel.');
        $original=$mime==='image/png'?@imagecreatefrompng($source):@imagecreatefromjpeg($source);
        if (!$original) throw new RuntimeException('Foto tidak dapat dibaca.');
        if ($mime==='image/jpeg') {
            $orientation=$this->jpeg_orientation($source);
            if (in_array($orientation,array(2,5,7),TRUE)) imageflip($original,IMG_FLIP_HORIZONTAL);
            if ($orientation===4) imageflip($original,IMG_FLIP_VERTICAL);
            $angle=$orientation===3?180:(in_array($orientation,array(5,8),TRUE)?90:(in_array($orientation,array(6,7),TRUE)?-90:0));
            if ($angle) {
                $rotated=imagerotate($original,$angle,0);
                if (!$rotated) { imagedestroy($original); throw new RuntimeException('Orientasi foto gagal diproses.'); }
                imagedestroy($original);
                $original=$rotated;
            }
            $width=imagesx($original);
            $height=imagesy($original);
        }
        $edge=min(2200/max($width,$height),1);
        $out_width=max(1,(int)round($width*$edge));
        $out_height=max(1,(int)round($height*$edge));
        try {
            for ($attempt=0;$attempt<6;$attempt++) {
                $canvas=imagecreatetruecolor($out_width,$out_height);
                if (!$canvas) throw new RuntimeException('Foto gagal dikompresi.');
                $white=imagecolorallocate($canvas,255,255,255);
                imagefill($canvas,0,0,$white);
                imagecopyresampled($canvas,$original,0,0,0,0,$out_width,$out_height,$width,$height);
                foreach (array(84,72,60) as $quality) {
                    if (!imagejpeg($canvas,$target,$quality)) {
                        imagedestroy($canvas);
                        throw new RuntimeException('Foto gagal disimpan.');
                    }
                    clearstatcache(TRUE,$target);
                    $size=filesize($target);
                    if ($size>0 && $size<=1048576) {
                        imagedestroy($canvas);
                        return $size;
                    }
                }
                imagedestroy($canvas);
                $out_width=max(1,(int)round($out_width*0.78));
                $out_height=max(1,(int)round($out_height*0.78));
            }
        } finally { imagedestroy($original); }
        throw new RuntimeException('Foto gagal dikompresi hingga 1 MB.');
    }

    private function jpeg_orientation($path)
    {
        $handle=@fopen($path,'rb');
        if (!$handle) return 1;
        $bytes=fread($handle,65536);
        fclose($handle);
        $position=strpos($bytes,"Exif\0\0");
        if ($position===FALSE) return 1;
        $start=$position+6;
        $endian=substr($bytes,$start,2);
        if ($endian!=='II' && $endian!=='MM') return 1;
        $short=function($offset) use ($bytes,$endian) {
            if ($offset<0 || $offset+2>strlen($bytes)) return 0;
            return unpack($endian==='II'?'v':'n',substr($bytes,$offset,2))[1];
        };
        $long=function($offset) use ($bytes,$endian) {
            if ($offset<0 || $offset+4>strlen($bytes)) return 0;
            return unpack($endian==='II'?'V':'N',substr($bytes,$offset,4))[1];
        };
        if ($short($start+2)!==42) return 1;
        $directory=$start+$long($start+4);
        $count=min(128,$short($directory));
        for ($index=0;$index<$count;$index++) {
            $entry=$directory+2+$index*12;
            if ($short($entry)===274 && $short($entry+2)===3 && $long($entry+4)===1) {
                $value=$short($entry+8);
                return $value>=1 && $value<=8?$value:1;
            }
        }
        return 1;
    }

    public function save_generated_pdf($contents,$name)
    {
        if (substr($contents,0,5)!=='%PDF-' || strlen($contents)>5*1024*1024)
            throw new RuntimeException('PDF hasil tidak valid atau terlalu besar.');
        $key=bin2hex(random_bytes(24)).'.pdf';$path=$this->root.'/'.$key;
        if (file_put_contents($path,$contents,LOCK_EX)===FALSE) throw new RuntimeException('PDF gagal disimpan.');
        chmod($path,0640);
        return array('storage_key'=>$key,'detected_mime'=>'application/pdf',
            'original_name'=>mb_substr($name,0,200),'file_size'=>strlen($contents),
            'sha256'=>hash('sha256',$contents));
    }

    public function image_path($key)
    {
        if (!preg_match('/^[a-f0-9]{48}\.(jpg|png)$/',$key)) return FALSE;
        $path=$this->root.'/'.$key;
        return is_file($path)?$path:FALSE;
    }
}
