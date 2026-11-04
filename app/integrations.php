<?php
declare(strict_types=1);
function secret_key():string { $config=cfg(); $base=(string)($config['app_key']??$config['db_pass']??''); return hash('sha256','thirtyvi-credentials-v1:'.$base,true); }
function seal_secret(string $value):string { $iv=random_bytes(12);$tag='';$cipher=openssl_encrypt($value,'aes-256-gcm',secret_key(),OPENSSL_RAW_DATA,$iv,$tag);if($cipher===false)throw new RuntimeException('Encryption failed');return 'enc:'.base64_encode($iv.$tag.$cipher); }
function reveal_secret(string $value):string { if(!str_starts_with($value,'enc:'))return ''; $raw=base64_decode(substr($value,4),true);if($raw===false||strlen($raw)<29)return '';return openssl_decrypt(substr($raw,28),'aes-256-gcm',secret_key(),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16))?:''; }
function integration_secret(string $key):string { $s=setting($key);return $s?reveal_secret($s):(string)(cfg()[$key]??''); }
function upload_media(string $field,array $types=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'],int $max=6000000):?string {
 if(empty($_FILES[$field]['name']))return null;$f=$_FILES[$field];if($f['error']!==UPLOAD_ERR_OK||$f['size']>$max)throw new RuntimeException('Invalid media upload or file too large.');
 $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);if(!isset($types[$mime]))throw new RuntimeException('Only JPG, PNG and WebP images are allowed.');
 if(!getimagesize($f['tmp_name']))throw new RuntimeException('Invalid image file.');$dir=ROOT.'/public/uploads';if(!is_dir($dir))mkdir($dir,0755,true);$name=bin2hex(random_bytes(18)).'.'.$types[$mime];if(!move_uploaded_file($f['tmp_name'],$dir.'/'.$name))throw new RuntimeException('Could not save image.');return '/public/uploads/'.$name;
}
function save_integration(string $key,string $value,bool $secret=false):void { if($secret){if($value==='')return;$value=seal_secret($value);}sql('INSERT INTO settings(`key`,value) VALUES(?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',[$key,$value]); }
