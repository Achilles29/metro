<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Google_workspace
{
    private $credentials = '/var/lib/metro/google/service-account.json';
    private $oauth_client = '/var/lib/metro/google/oauth-client.json';
    private $oauth_token_file = '/var/lib/metro/google/oauth-token.json';

    public function configured()
    {
        return is_file($this->credentials);
    }

    public function account_email()
    {
        if (!$this->configured()) return NULL;
        $key=json_decode(file_get_contents($this->credentials),TRUE);
        return $key['client_email'] ?? NULL;
    }

    public function oauth_client_configured() { return is_file($this->oauth_client); }
    public function oauth_connected() { return is_file($this->oauth_token_file); }

    private function save_secret($path,$data)
    {
        $directory=dirname($path);
        if (!is_dir($directory) && !mkdir($directory,0770,TRUE)) throw new RuntimeException('Direktori konfigurasi Google gagal dibuat.');
        $temporary=$directory.'/key-'.bin2hex(random_bytes(8)).'.tmp';
        if (file_put_contents($temporary,json_encode($data,JSON_UNESCAPED_SLASHES),LOCK_EX)===FALSE)
            throw new RuntimeException('Kredensial Google gagal disimpan.');
        chmod($temporary,0640);
        if (!rename($temporary,$path)) {unlink($temporary);throw new RuntimeException('Kredensial Google gagal diaktifkan.');}
    }

    public function install_oauth_client($field)
    {
        if (empty($_FILES[$field]) || $_FILES[$field]['error']!==UPLOAD_ERR_OK || $_FILES[$field]['size']>16384
            || !is_uploaded_file($_FILES[$field]['tmp_name'])) throw new RuntimeException('Pilih JSON OAuth web client yang valid.');
        $data=json_decode(file_get_contents($_FILES[$field]['tmp_name']),TRUE);
        $web=$data['web'] ?? NULL;
        if (!$web || empty($web['client_id']) || empty($web['client_secret'])
            || !in_array(site_url('petugas/integrasi/google/callback'),$web['redirect_uris'] ?? array(),TRUE))
            throw new RuntimeException('JSON OAuth harus bertipe web dan memuat URI redirect '.site_url('petugas/integrasi/google/callback'));
        $this->save_secret($this->oauth_client,$web);
        if (is_file($this->oauth_token_file)) unlink($this->oauth_token_file);
    }

    private function oauth_client_data()
    {
        if (!$this->oauth_client_configured()) throw new RuntimeException('Unggah JSON OAuth web client terlebih dahulu.');
        $client=json_decode(file_get_contents($this->oauth_client),TRUE);
        if (empty($client['client_id']) || empty($client['client_secret'])) throw new RuntimeException('JSON OAuth tidak valid.');
        return $client;
    }

    public function authorization_url($state)
    {
        $client=$this->oauth_client_data();
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query(array(
            'client_id'=>$client['client_id'],'redirect_uri'=>site_url('petugas/integrasi/google/callback'),
            'response_type'=>'code','scope'=>'https://www.googleapis.com/auth/spreadsheets https://www.googleapis.com/auth/drive',
            'access_type'=>'offline','prompt'=>'consent','state'=>$state));
    }

    public function finish_oauth($code)
    {
        $client=$this->oauth_client_data();
        $result=$this->request('https://oauth2.googleapis.com/token','POST',http_build_query(array(
            'code'=>$code,'client_id'=>$client['client_id'],'client_secret'=>$client['client_secret'],
            'redirect_uri'=>site_url('petugas/integrasi/google/callback'),'grant_type'=>'authorization_code')),
            array('Content-Type: application/x-www-form-urlencoded'));
        if (empty($result['refresh_token'])) throw new RuntimeException('Google tidak memberikan refresh token. Ulangi sambungan dengan persetujuan akses.');
        $this->save_secret($this->oauth_token_file,array('refresh_token'=>$result['refresh_token'],'connected_at'=>date('c')));
    }

    private function oauth_access_token()
    {
        $client=$this->oauth_client_data();
        if (!$this->oauth_connected()) throw new RuntimeException('Hubungkan akun Google melalui tombol OAuth terlebih dahulu.');
        $stored=json_decode(file_get_contents($this->oauth_token_file),TRUE);
        $result=$this->request('https://oauth2.googleapis.com/token','POST',http_build_query(array(
            'client_id'=>$client['client_id'],'client_secret'=>$client['client_secret'],
            'refresh_token'=>$stored['refresh_token'] ?? '','grant_type'=>'refresh_token')),
            array('Content-Type: application/x-www-form-urlencoded'));
        if (empty($result['access_token'])) throw new RuntimeException('Refresh token Google tidak berlaku. Sambungkan ulang akun Google.');
        return $result['access_token'];
    }

    public function install_credentials($field)
    {
        if (empty($_FILES[$field]) || $_FILES[$field]['error']!==UPLOAD_ERR_OK || $_FILES[$field]['size']>16384
            || !is_uploaded_file($_FILES[$field]['tmp_name'])) throw new RuntimeException('Pilih JSON akun layanan yang valid (maksimal 16 KB).');
        $data=json_decode(file_get_contents($_FILES[$field]['tmp_name']),TRUE);
        if (!is_array($data) || ($data['type'] ?? '')!=='service_account'
            || !filter_var($data['client_email'] ?? '',FILTER_VALIDATE_EMAIL)
            || empty($data['private_key']) || !openssl_pkey_get_private($data['private_key']))
            throw new RuntimeException('JSON akun layanan Google tidak valid.');
        $directory=dirname($this->credentials);
        if (!is_dir($directory) && !mkdir($directory,0770,TRUE)) throw new RuntimeException('Direktori konfigurasi Google gagal dibuat.');
        $temporary=$directory.'/key-'.bin2hex(random_bytes(8)).'.tmp';
        if (file_put_contents($temporary,json_encode($data,JSON_UNESCAPED_SLASHES),LOCK_EX)===FALSE)
            throw new RuntimeException('Kredensial gagal disimpan.');
        chmod($temporary,0640);
        if (!rename($temporary,$this->credentials)) {unlink($temporary);throw new RuntimeException('Kredensial gagal diaktifkan.');}
        return $data['client_email'];
    }

    private function token($scope)
    {
        $ci=&get_instance();$settings=$ci->db->get_where('google_settings',array('id'=>1))->row();
        if ($settings && $settings->drive_auth_mode==='oauth') return $this->oauth_access_token();
        if (!$this->configured()) throw new RuntimeException('Unggah JSON akun layanan Google terlebih dahulu.');
        $key=json_decode(file_get_contents($this->credentials),TRUE);
        $now=time();
        $encode=function($value) {return rtrim(strtr(base64_encode(json_encode($value,JSON_UNESCAPED_SLASHES)),'+/','-_'),'=');};
        $unsigned=$encode(array('alg'=>'RS256','typ'=>'JWT')).'.'.$encode(array(
            'iss'=>$key['client_email'],'scope'=>$scope,
            'aud'=>'https://oauth2.googleapis.com/token','iat'=>$now,'exp'=>$now+3600));
        if (!openssl_sign($unsigned,$signature,$key['private_key'],OPENSSL_ALGO_SHA256))
            throw new RuntimeException('Token Google gagal ditandatangani.');
        $jwt=$unsigned.'.'.rtrim(strtr(base64_encode($signature),'+/','-_'),'=');
        $result=$this->request('https://oauth2.googleapis.com/token','POST',
            http_build_query(array('grant_type'=>'urn:ietf:params:oauth:grant-type:jwt-bearer','assertion'=>$jwt)),
            array('Content-Type: application/x-www-form-urlencoded'));
        if (empty($result['access_token'])) throw new RuntimeException('Autentikasi Google gagal: '.($result['error_description'] ?? $result['error'] ?? 'periksa akun layanan.'));
        return $result['access_token'];
    }

    private function request($url,$method='GET',$body=NULL,$headers=array())
    {
        $curl=curl_init($url);
        curl_setopt_array($curl,array(CURLOPT_RETURNTRANSFER=>TRUE,CURLOPT_CONNECTTIMEOUT=>8,
            CURLOPT_TIMEOUT=>35,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers));
        if ($body!==NULL) curl_setopt($curl,CURLOPT_POSTFIELDS,$body);
        $raw=curl_exec($curl);$status=curl_getinfo($curl,CURLINFO_HTTP_CODE);$error=curl_error($curl);curl_close($curl);
        if ($raw===FALSE) throw new RuntimeException('Koneksi Google gagal: '.$error);
        $data=json_decode($raw,TRUE);
        if ($status<200 || $status>=300) {
            $message=$data['error']['message'] ?? $data['error_description'] ?? $data['error'] ?? ('HTTP '.$status);
            if (is_array($message)) $message=json_encode($message);
            throw new RuntimeException('Google API: '.mb_substr((string)$message,0,300));
        }
        return is_array($data)?$data:array();
    }

    private function append_rows($spreadsheet,$range,$rows)
    {
        if (!$spreadsheet || !$range) throw new RuntimeException('ID spreadsheet dan tab laporan belum diatur.');
        $token=$this->token('https://www.googleapis.com/auth/spreadsheets');
        $url='https://sheets.googleapis.com/v4/spreadsheets/'.rawurlencode($spreadsheet)
            .'/values/'.rawurlencode($range).':append?valueInputOption=USER_ENTERED&insertDataOption=INSERT_ROWS';
        return $this->request($url,'POST',json_encode(array('values'=>$this->safe_rows($rows)),JSON_UNESCAPED_UNICODE),
            array('Authorization: Bearer '.$token,'Content-Type: application/json'));
    }

    private function safe_rows($rows)
    {
        foreach ($rows as &$row) foreach ($row as $column=>&$value)
            if (is_string($value) && !($column===0 && $value==='=ROW()-2')
                && preg_match('/^\\s*[=+\\-@]/u',$value)) $value="'".$value;
        return $rows;
    }

    private function update_rows($spreadsheet,$range,$rows)
    {
        $token=$this->token('https://www.googleapis.com/auth/spreadsheets');
        $url='https://sheets.googleapis.com/v4/spreadsheets/'.rawurlencode($spreadsheet)
            .'/values/'.rawurlencode($range).'?valueInputOption=USER_ENTERED';
        return $this->request($url,'PUT',json_encode(array('values'=>$this->safe_rows($rows)),JSON_UNESCAPED_UNICODE),
            array('Authorization: Bearer '.$token,'Content-Type: application/json'));
    }

    public function append_ctt($request,$ctt,$option)
    {
        $ci=&get_instance();$settings=$ci->db->get_where('google_settings',array('id'=>1))->row();
        if (!$ctt->returned_at) throw new RuntimeException('CTT belum dikembalikan.');
        $values=array('=ROW()-2', $request->organization_name ?: $request->full_name, $option->option_label,
            $request->penera_display ?: '', date('d/m/Y',strtotime($ctt->borrowed_at)),
            date('d/m/Y',strtotime($ctt->returned_at)));
        return $this->append_rows($settings->spreadsheet_id,$settings->sheet_range,array($values));
    }

    public function append_order($request,$items,$certificates,$remote_range=NULL)
    {
        $ci=&get_instance();$settings=$ci->db->get_where('google_settings',array('id'=>1))->row();
        $rows=array();foreach ($items as $index=>$item) {
            $cert=$certificates[(int)$item->id] ?? NULL;
            $rows[]=array($index===0?$request->request_no:'',$index===0?date('d/m/Y',strtotime($request->submitted_at)):'',
                $index===0?($request->organization_name ?: $request->full_name):'',
                $index===0?$request->owner_address:'',$item->uttp_name,1,
                $request->service_mode==='field'?'Luar kantor':'Dalam kantor',
                $cert?$cert->certificate_no:'',$item->tested_at?date('d/m/Y',strtotime($item->tested_at)):'',
                $item->service_address,status_label($item->item_status),$request->penera_display ?: '');
        }
        return $remote_range
            ? $this->update_rows($settings->order_spreadsheet_id,$remote_range,$rows)
            : $this->append_rows($settings->order_spreadsheet_id,$settings->order_sheet_range,$rows);
    }

    public function append_ttu($request,$items,$status,$notes)
    {
        $ci=&get_instance();$settings=$ci->db->get_where('google_settings',array('id'=>1))->row();
        $range=$status==='valid'?$settings->ttu_sah_range:($status==='invalid'?$settings->ttu_batal_range:$settings->ttu_other_range);
        $rows=array();foreach ($items as $item) {
            $date=$status==='valid'||$status==='invalid'?$item->tested_at:$request->submitted_at;
            $rows[]=array('=ROW()-2', $date?date('d/m/Y',strtotime($date)):'',
                $request->organization_name ?: $request->full_name,$request->owner_address,
                $item->uttp_name,1,$request->service_mode==='field'?'Luar kantor':'Dalam kantor',
                $item->service_address,$status==='valid'||$status==='invalid'?($request->penera_display ?: ''):status_label($item->item_status));
            if ($status!=='valid' && $status!=='invalid') $rows[count($rows)-1][]=$notes[(int)$item->id] ?? '';
        }
        return $this->append_rows($settings->ttu_spreadsheet_id,$range,$rows);
    }

    public function test_connection()
    {
        $ci=&get_instance();$settings=$ci->db->get_where('google_settings',array('id'=>1))->row();
        $token=$this->token('https://www.googleapis.com/auth/spreadsheets https://www.googleapis.com/auth/drive.metadata.readonly');
        $result=array();
        foreach (array('spreadsheet_id'=>'ctt','order_spreadsheet_id'=>'order','ttu_spreadsheet_id'=>'ttu') as $field=>$label)
            if ($settings->$field) $result[$label]=$this->request('https://sheets.googleapis.com/v4/spreadsheets/'.rawurlencode($settings->$field).'?fields=spreadsheetId,properties.title',
                'GET',NULL,array('Authorization: Bearer '.$token));
        if ($settings->drive_folder_id) {
            $result['folder']=$this->request('https://www.googleapis.com/drive/v3/files/'.rawurlencode($settings->drive_folder_id).'?fields=id,name,mimeType,driveId&supportsAllDrives=true',
                'GET',NULL,array('Authorization: Bearer '.$token));
            if (($result['folder']['mimeType'] ?? '')!=='application/vnd.google-apps.folder')
                throw new RuntimeException('Tujuan Google Drive bukan folder.');
            if ($settings->drive_auth_mode==='service_account' && empty($result['folder']['driveId']))
                throw new RuntimeException('Folder ini ada di My Drive. Gunakan OAuth admin untuk menyimpan cerapan.');
        }
        return $result;
    }

    public function upload_cerapan($doc,$request,$item)
    {
        $ci=&get_instance();$settings=$ci->db->get_where('google_settings',array('id'=>1))->row();
        if (!$settings || !$settings->drive_folder_id) throw new RuntimeException('Folder Google Drive belum diatur.');
        $ci->load->library('private_files');$path=$ci->private_files->path($doc->storage_key);
        if (!$path) throw new RuntimeException('PDF cerapan tidak ditemukan di penyimpanan.');
        $name=$this->cerapan_name($settings->cerapan_filename_pattern,$request,$item);
        $token=$this->token('https://www.googleapis.com/auth/drive');
        $boundary='setimbang-'.bin2hex(random_bytes(12));
        $body='--'.$boundary."\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n"
            .json_encode(array('name'=>$name,'parents'=>array($settings->drive_folder_id)))."\r\n"
            .'--'.$boundary."\r\nContent-Type: application/pdf\r\n\r\n".file_get_contents($path)."\r\n"
            .'--'.$boundary."--\r\n";
        $result=$this->request('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&supportsAllDrives=true&fields=id,name,webViewLink',
            'POST',$body,array('Authorization: Bearer '.$token,'Content-Type: multipart/related; boundary='.$boundary));
        if (empty($result['id'])) throw new RuntimeException('Google Drive tidak mengembalikan ID berkas.');
        return $result;
    }

    public function cerapan_name($pattern,$request,$item)
    {
        $name=strtr($pattern,array('{request_no}'=>$request->request_no,
            '{unit_sequence}'=>$item->unit_sequence,'{date}'=>date('Ymd'),'{uttp}'=>$item->brand ?: 'UTTP'));
        $name=preg_replace('/[\\x00-\\x1f\\\\\/<>:"|?*]+/u','-', $name);
        if (!preg_match('/\.pdf$/i',$name)) $name.='.pdf';
        return mb_substr($name,0,190);
    }
}
