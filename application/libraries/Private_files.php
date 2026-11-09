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
}
