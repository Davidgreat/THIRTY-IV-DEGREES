<?php
/** Local-only TLS fixture. Never contacts an external mail server or stores message bodies. */
$ctx=stream_context_create(['ssl'=>['local_cert'=>'/tmp/mail640.pem','local_pk'=>'/tmp/mail640.key']]);$server=stream_socket_server('tcp://127.0.0.1:587',$errno,$errstr,STREAM_SERVER_BIND|STREAM_SERVER_LISTEN,$ctx);if(!$server)throw new RuntimeException('Fixture port unavailable');
while($socket=@stream_socket_accept($server,-1)){
 try{stream_set_timeout($socket,8);fwrite($socket,"220 Local SMTP fixture\r\n");$auth='';$user='';$tls=false;
 while(($line=fgets($socket))!==false){$line=rtrim($line,"\r\n");
 if(str_starts_with($line,'EHLO '))fwrite($socket,"250-local.test\r\n250-STARTTLS\r\n250 AUTH LOGIN XOAUTH2\r\n");
 elseif($line==='STARTTLS'){fwrite($socket,"220 Begin TLS\r\n");if(!stream_socket_enable_crypto($socket,true,STREAM_CRYPTO_METHOD_TLS_SERVER))break;$tls=true;}
 elseif(str_starts_with($line,'AUTH XOAUTH2 ')){if(!$tls)break;$raw=base64_decode(substr($line,13));$ok=preg_match('/^user=mailer@example\.test\x01auth=Bearer mock-access-[0-9]+\x01\x01$/D',$raw);$auth='XOAUTH2';fwrite($socket,$ok?"235 Authenticated\r\n":"535 Authentication rejected\r\n");}
 elseif($line==='AUTH LOGIN'){if(!$tls)break;$auth='LOGIN';fwrite($socket,"334 VXNlcm5hbWU6\r\n");$user=base64_decode(trim(fgets($socket)));fwrite($socket,"334 UGFzc3dvcmQ6\r\n");$password=base64_decode(trim(fgets($socket)));fwrite($socket,$user==='mailer@example.test'&&$password==='smtp-fixture-password'?"235 Authenticated\r\n":"535 Authentication rejected\r\n");}
 elseif(str_starts_with($line,'MAIL FROM:'))fwrite($socket,"250 Sender accepted\r\n");
 elseif(str_starts_with($line,'RCPT TO:'))fwrite($socket,str_contains($line,'reject@example.test')?"550 Recipient rejected\r\n":"250 Recipient accepted\r\n");
 elseif($line==='DATA'){fwrite($socket,"354 Send message\r\n");while(($data=fgets($socket))!==false&&rtrim($data,"\r\n")!=='.'){}$state=is_file('/tmp/mail-smtp-state.json')?json_decode(file_get_contents('/tmp/mail-smtp-state.json'),true):[];$state['accepted']=($state['accepted']??0)+1;$state['auth']=$auth;$state['tls']=$tls;file_put_contents('/tmp/mail-smtp-state.json',json_encode($state));fwrite($socket,"250 Message accepted\r\n");break;}
 elseif($line==='QUIT')break;else fwrite($socket,"500 Unknown command\r\n");
 }
 }catch(Throwable $e){}fclose($socket);
}
