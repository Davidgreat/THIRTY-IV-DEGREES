<?php
/** Local TLS CONNECT fixture for the fixed Microsoft token endpoint; no external access. */
$ctx=stream_context_create(['ssl'=>['local_cert'=>'/tmp/mail640.pem','local_pk'=>'/tmp/mail640.key']]);$server=stream_socket_server('tcp://127.0.0.1:9082',$errno,$errstr,STREAM_SERVER_BIND|STREAM_SERVER_LISTEN,$ctx);if(!$server)throw new RuntimeException('Fixture port unavailable');
while($socket=@stream_socket_accept($server,-1)){
 try{stream_set_timeout($socket,8);$line=fgets($socket);if(!str_starts_with($line,'CONNECT login.microsoftonline.com:443 ')){fclose($socket);continue;}while(($h=fgets($socket))!==false&&trim($h)!==''){}fwrite($socket,"HTTP/1.1 200 Connection Established\r\n\r\n");if(!stream_socket_enable_crypto($socket,true,STREAM_CRYPTO_METHOD_TLS_SERVER)){fclose($socket);continue;}
 $request=trim(fgets($socket));$headers=[];while(($h=fgets($socket))!==false&&trim($h)!==''){$v=explode(':',$h,2);$headers[strtolower($v[0])]=trim($v[1]??'');}$body='';$length=(int)($headers['content-length']??0);while(strlen($body)<$length)$body.=fread($socket,$length-strlen($body));parse_str($body,$fields);
 $state=is_file('/tmp/mail-oauth-state.json')?json_decode(file_get_contents('/tmp/mail-oauth-state.json'),true):[];$status=200;$count=(int)($state['issued']??0)+1;
 if(($fields['client_secret']??'')!=='client-secret-fixture'){$status=400;$response=['error'=>'invalid_client'];}
 elseif(!empty($state['fail_refresh'])||(($fields['grant_type']??'')==='authorization_code'&&($fields['code']??'')!=='good-code')){$status=400;$response=['error'=>'invalid_grant'];}
 else{$response=['token_type'=>'Bearer','access_token'=>'mock-access-'.$count,'refresh_token'=>'mock-refresh-'.$count,'expires_in'=>3600,'scope'=>'https://outlook.office.com/SMTP.Send'];$state['issued']=$count;$state['grant']=$fields['grant_type']??'';$state['pkce_length']=strlen($fields['code_verifier']??'');file_put_contents('/tmp/mail-oauth-state.json',json_encode($state));}
 $json=json_encode($response);fwrite($socket,'HTTP/1.1 '.$status." Result\r\n");fwrite($socket,"Content-Type: application/json\r\nContent-Length: ".strlen($json)."\r\nConnection: close\r\n\r\n".$json);
 }catch(Throwable $e){}fclose($socket);
}
