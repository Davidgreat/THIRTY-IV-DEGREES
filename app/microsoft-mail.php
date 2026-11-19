<?php
declare(strict_types=1);
/** Read fresh settings: OAuth refreshes must never reuse a request-local token cache. */
function mail_setting(string $key,string $default=''):string{$row=one('SELECT value FROM settings WHERE `key`=?',[$key]);return $row?(string)$row['value']:$default;}
function mail_put(string $key,string $value):void{sql('INSERT INTO settings(`key`,value) VALUES(?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',[$key,$value]);}
function mail_secret(string $key):string{return reveal_secret(mail_setting($key));}
function microsoft_mail_redirect():string{
 $base=rtrim((string)(cfg()['app_url']??''),'/');$url=parse_url($base);
 if(!$url||empty($url['host'])||isset($url['user'])||isset($url['pass'])||isset($url['query'])||isset($url['fragment'])||!(($url['scheme']??'')==='https'||(($url['scheme']??'')==='http'&&in_array($url['host'],['localhost','127.0.0.1'],true))))throw new RuntimeException('Set a valid HTTPS website URL in the hosting configuration before connecting Microsoft.');
 return $base.'/admin/email/microsoft/callback';
}
function microsoft_mail_fingerprint():string{
 $data=[];foreach(['smtp_auth','smtp_host','smtp_port','smtp_security','smtp_user','ms_mail_tenant','ms_mail_client_id','ms_mail_client_secret'] as $key)$data[$key]=mail_setting($key);
 $data['redirect']=microsoft_mail_redirect();return hash('sha256',json_encode($data));
}
function microsoft_mail_configured():bool{return mail_setting('ms_mail_client_id')!==''&&mail_secret('ms_mail_client_secret')!=='';}
function microsoft_mail_tokens():array{$raw=mail_secret('ms_mail_tokens');return $raw!==''?(json_decode($raw,true)?:[]):[];}
function microsoft_mail_endpoint(string $operation):string{
 $tenant=mail_setting('ms_mail_tenant','common');if(!preg_match('/^(common|organizations|consumers|[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12})$/iD',$tenant))throw new RuntimeException('Choose a valid Microsoft tenant.');
 return 'https://login.microsoftonline.com/'.$tenant.'/oauth2/v2.0/'.$operation;
}
function microsoft_mail_http(array $fields):array{
 $curl=curl_init(microsoft_mail_endpoint('token'));
 curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($fields,'','&',PHP_QUERY_RFC3986),CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>25,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS]);
 $body=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);curl_close($curl);
 if($body===false||strlen($body)>131072)throw new RuntimeException('Microsoft could not be reached. Check hosting HTTPS access and try again.');
 $result=json_decode($body,true);
 if($status!==200||!is_array($result)){
  $error=is_array($result)?($result['error']??''):'';
  if($error==='invalid_grant'||$error==='interaction_required')throw new RuntimeException('Microsoft authorization expired or was revoked. Connect Microsoft again.');
  if($error==='invalid_client')throw new RuntimeException('Microsoft rejected the app credentials. Check the application ID, client secret value and tenant.');
  throw new RuntimeException('Microsoft authorization failed. Check the app registration, consent and tenant policies.');
 }
 return $result;
}
function microsoft_mail_lock():string{
 $name='thirtyvi-ms-mail-'.substr(hash('sha256',(string)cfg()['db_name']),0,24);
 if((int)(one('SELECT GET_LOCK(?,10) ok',[$name])['ok']??0)!==1)throw new RuntimeException('Email authorization is busy. Please try again.');return $name;
}
function microsoft_mail_disconnect():void{
 $lock=microsoft_mail_lock();try{mail_put('ms_mail_tokens','');mail_put('smtp_test_result','');unset($_SESSION['ms_mail_pending']);}finally{sql('SELECT RELEASE_LOCK(?)',[$lock]);}
}
function microsoft_mail_store_tokens(array $response,string $refresh='',?string $expectedBinding=null,bool $locked=false):array{
 $ownedLock=$locked?null:microsoft_mail_lock();try{
 $access=$response['access_token']??'';$refresh=$response['refresh_token']??$refresh;$expires=$response['expires_in']??0;
 if(!is_string($access)||$access===''||strlen($access)>32768||!is_string($refresh)||$refresh===''||strlen($refresh)>32768||!is_numeric($expires)||(int)$expires<60)throw new RuntimeException('Microsoft did not return a usable offline SMTP authorization. Reconnect and allow the requested access.');
 if(isset($response['token_type'])&&strcasecmp((string)$response['token_type'],'Bearer')!==0)throw new RuntimeException('Microsoft returned an unsupported token type.');
 if(isset($response['scope'])&&!preg_match('~(?:^|\s)(?:https://outlook\.office(?:365)?\.com/)?SMTP\.Send(?:\s|$)~i',(string)$response['scope']))throw new RuntimeException('Microsoft did not grant SMTP.Send permission. Check delegated permissions and consent.');
 $binding=microsoft_mail_fingerprint();if($expectedBinding!==null&&!hash_equals($expectedBinding,$binding))throw new RuntimeException('Email settings changed during authorization. Connect Microsoft again.');
 $tokens=['access'=>$access,'refresh'=>$refresh,'expires'=>time()+min(86400,(int)$expires),'binding'=>$binding];
 mail_put('ms_mail_tokens',seal_secret(json_encode($tokens,JSON_THROW_ON_ERROR)));return $tokens;
 }finally{if($ownedLock!==null)sql('SELECT RELEASE_LOCK(?)',[$ownedLock]);}
}
function microsoft_mail_access_token():string{
 if(mail_setting('smtp_auth','password')!=='microsoft')throw new RuntimeException('Microsoft authentication is not selected.');
 $lock=microsoft_mail_lock();
 try{
  $tokens=microsoft_mail_tokens();$binding=microsoft_mail_fingerprint();
  if(!$tokens||!hash_equals($binding,(string)($tokens['binding']??'')))throw new RuntimeException('Connect Microsoft before sending email.');
  if((int)($tokens['expires']??0)>time()+120)return (string)$tokens['access'];
  $response=microsoft_mail_http(['client_id'=>mail_setting('ms_mail_client_id'),'client_secret'=>mail_secret('ms_mail_client_secret'),'grant_type'=>'refresh_token','refresh_token'=>$tokens['refresh'],'scope'=>'offline_access https://outlook.office.com/SMTP.Send']);
  $tokens=microsoft_mail_store_tokens($response,(string)$tokens['refresh'],$binding,true);return $tokens['access'];
 }finally{sql('SELECT RELEASE_LOCK(?)',[$lock]);}
}
function microsoft_mail_connect():never{
 if(admin()['role']!=='admin')abort(403,'Owner access required');rate_limit('microsoft_mail_connect',8,900);
 if(mail_setting('smtp_auth')!=='microsoft'||!microsoft_mail_configured()){flash('Save Microsoft authentication settings and app credentials first.','error');go('admin/email');}
 try{
  $state=bin2hex(random_bytes(32));$verifier=rtrim(strtr(base64_encode(random_bytes(48)),'+/','-_'),'=');
  $_SESSION['ms_mail_pending']=['state'=>$state,'verifier'=>$verifier,'time'=>time(),'owner'=>admin()['id'],'binding'=>microsoft_mail_fingerprint()];
  $query=['client_id'=>mail_setting('ms_mail_client_id'),'response_type'=>'code','redirect_uri'=>microsoft_mail_redirect(),'response_mode'=>'query','scope'=>'offline_access https://outlook.office.com/SMTP.Send','state'=>$state,'code_challenge'=>rtrim(strtr(base64_encode(hash('sha256',$verifier,true)),'+/','-_'),'='),'code_challenge_method'=>'S256','prompt'=>'select_account','login_hint'=>mail_setting('smtp_user')];
  header('Cache-Control: no-store');header('Location: '.microsoft_mail_endpoint('authorize').'?'.http_build_query($query,'','&',PHP_QUERY_RFC3986));exit;
 }catch(Throwable $error){unset($_SESSION['ms_mail_pending']);flash(get_class($error)===RuntimeException::class?$error->getMessage():'Microsoft sign-in could not start.','error');go('admin/email');}
}
function microsoft_mail_callback():never{
 header('Cache-Control: private, no-store');header('Referrer-Policy: no-referrer');
 if(admin()['role']!=='admin')abort(403,'Owner access required');
 $pending=$_SESSION['ms_mail_pending']??null;unset($_SESSION['ms_mail_pending']);
 try{
  $state=$_GET['state']??'';$code=$_GET['code']??'';
  if(!is_array($pending)||!is_string($state)||!hash_equals((string)$pending['state'],$state)||time()-(int)$pending['time']>600||(int)$pending['owner']!==(int)admin()['id']||!hash_equals((string)$pending['binding'],microsoft_mail_fingerprint()))throw new RuntimeException('Microsoft sign-in expired or settings changed. Start Connect Microsoft again.');
  if(isset($_GET['error']))throw new RuntimeException('Microsoft sign-in was cancelled or access was not approved. Connect again when ready.');
  if(!is_string($code)||$code===''||strlen($code)>16384)throw new RuntimeException('Microsoft did not return an authorization code. Connect again.');
  $response=microsoft_mail_http(['client_id'=>mail_setting('ms_mail_client_id'),'client_secret'=>mail_secret('ms_mail_client_secret'),'grant_type'=>'authorization_code','code'=>$code,'redirect_uri'=>microsoft_mail_redirect(),'code_verifier'=>$pending['verifier']]);
  microsoft_mail_store_tokens($response,'',(string)$pending['binding']);mail_put('smtp_test_result','');try{audit('email.microsoft.connected');}catch(Throwable $ignored){}flash('Microsoft authorization saved. Send a test email to confirm this mailbox can send.');
 }catch(Throwable $error){flash(get_class($error)===RuntimeException::class?$error->getMessage():'Microsoft sign-in could not be completed.','error');}
 go('admin/email');
}
function email_settings_save():never{
 if(admin()['role']!=='admin')abort(403,'Owner access required');$settingsLock=null;
 try{
  $host=strtolower(str('smtp_host',190));$port=(int)($_POST['smtp_port']??587);$security=str('smtp_security',12);$mode=str('smtp_auth',20)?:mail_setting('smtp_auth','password');
  $from=str('smtp_from',190);$user=str('smtp_user',190);$sender=str('smtp_from_name',100);$secret=$_POST['smtp_password']??'';
  if(!is_string($secret)||strlen($secret)>4096||!preg_match('/^[a-z0-9.-]+$/iD',$host)||$port<1||$port>65535||!in_array($security,['ssl','starttls'],true)||!in_array($mode,['password','microsoft'],true)||!filter_var($from,FILTER_VALIDATE_EMAIL)||strpbrk($user.$from.$sender,"\r\n\0")!==false)throw new InvalidArgumentException('Check the SMTP host, port, encryption, sender and authentication settings.');
  $tenant=str('ms_mail_tenant',80)?:'common';$client=str('ms_mail_client_id',64);$clientSecret=$_POST['ms_mail_client_secret']??'';
  if(!is_string($clientSecret)||strlen($clientSecret)>4096)throw new InvalidArgumentException('Enter a valid Microsoft client secret value.');
  if($mode==='microsoft'){
   if(!in_array($host,['smtp.office365.com','smtp-mail.outlook.com'],true)||$port!==587||$security!=='starttls'||!filter_var($user,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Microsoft SMTP requires smtp.office365.com or smtp-mail.outlook.com, port 587, STARTTLS and the full mailbox email as username.');
   if(!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/iD',$client)||!preg_match('/^(common|organizations|consumers|[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12})$/iD',$tenant)||($clientSecret===''&&mail_secret('ms_mail_client_secret')===''))throw new InvalidArgumentException('Enter the Microsoft application ID, tenant and client secret value.');
   microsoft_mail_redirect();
  }
  $settingsLock=microsoft_mail_lock();db()->beginTransaction();$before=mail_setting('ms_mail_tokens')!==''?microsoft_mail_fingerprint():'';
  foreach(['smtp_host'=>$host,'smtp_port'=>(string)$port,'smtp_security'=>$security,'smtp_auth'=>$mode,'smtp_from'=>$from,'smtp_user'=>$user,'smtp_from_name'=>$sender] as $key=>$value)mail_put($key,$value);
  if($secret!=='')mail_put('smtp_password',seal_secret($secret));
  if($mode==='microsoft'){mail_put('ms_mail_tenant',$tenant);mail_put('ms_mail_client_id',$client);if($clientSecret!=='')mail_put('ms_mail_client_secret',seal_secret($clientSecret));}
  $changed=$before!==''&&!hash_equals($before,microsoft_mail_fingerprint());
  if($changed){mail_put('ms_mail_tokens','');unset($_SESSION['ms_mail_pending']);}
  mail_put('smtp_test_result','');audit('email.smtp.settings');db()->commit();
  flash('Email settings saved and applied. '.($mode==='microsoft'&&($changed||!mail_secret('ms_mail_tokens'))?'Connect Microsoft, then send a test email.':'Send a test email to verify delivery.'));
 }catch(Throwable $error){if(db()->inTransaction())db()->rollBack();flash($error instanceof InvalidArgumentException||get_class($error)===RuntimeException::class?$error->getMessage():'Email settings could not be saved. Please try again.','error');}finally{if($settingsLock!==null)sql('SELECT RELEASE_LOCK(?)',[$settingsLock]);}
 go('admin/email');
}
