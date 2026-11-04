<?php
/** Local TLS proxy for automated tests. Uses synthetic keys and no external network. */
$ctx=stream_context_create(['ssl'=>['local_cert'=>'/tmp/paystack-mock.pem','local_pk'=>'/tmp/paystack-mock.key','verify_peer'=>false]]);
$server=stream_socket_server('tcp://127.0.0.1:9080',$errno,$errstr,STREAM_SERVER_BIND|STREAM_SERVER_LISTEN,$ctx);
if(!$server)throw new RuntimeException($errstr);
while($c=stream_socket_accept($server,-1)){
 try{
  stream_set_timeout($c,5);$line=fgets($c);if(!str_starts_with($line,'CONNECT api.paystack.co:443 ')){fclose($c);continue;}
  while(($h=fgets($c))!==false&&trim($h)!==''){}
  fwrite($c,"HTTP/1.1 200 Connection Established\r\n\r\n");
  if(!stream_socket_enable_crypto($c,true,STREAM_CRYPTO_METHOD_TLS_SERVER)){fclose($c);continue;}
  $request=trim(fgets($c));$headers=[];while(($h=fgets($c))!==false&&trim($h)!==''){$p=explode(':',$h,2);$headers[strtolower($p[0])]=trim($p[1]??'');}
  $length=(int)($headers['content-length']??0);$body='';while(strlen($body)<$length){$body.=fread($c,$length-strlen($body));}
  [$method,$path]=explode(' ',$request);$mode=str_contains($headers['authorization']??'','sk_live_')?'live':'test';
  $state=is_file('/tmp/paystack-mock-state.json')?json_decode(file_get_contents('/tmp/paystack-mock-state.json'),true):[];$code=200;$data=[];
  if($path==='/balance')$data=[['currency'=>'NGN','balance'=>100000]];
  elseif($path==='/transaction/initialize'){
   $p=json_decode($body,true);$ref=$p['reference'];
   if(isset($state[$ref])){$code=400;$data=[];}
   else{$state[$ref]=['reference'=>$ref,'status'=>'pending','currency'=>$p['currency'],'amount'=>$p['amount'],'domain'=>$mode];$data=['authorization_url'=>'https://checkout.paystack.com/mock-'.$ref,'reference'=>$ref,'access_code'=>'mock'];}
  }elseif(str_starts_with($path,'/transaction/verify/')){
   $ref=rawurldecode(substr($path,strlen('/transaction/verify/')));
   if(!isset($state[$ref]))$code=404;
   elseif($state[$ref]['domain']!==$mode)$code=404;
   else $data=$state[$ref];
  }else $code=404;
  file_put_contents('/tmp/paystack-mock-state.json',json_encode($state));
  $response=json_encode(['status'=>$code===200,'data'=>$data]);
  fwrite($c,"HTTP/1.1 $code Result\r\nContent-Type: application/json\r\nContent-Length: ".strlen($response)."\r\nConnection: close\r\n\r\n".$response);
 }catch(Throwable $e){fwrite(STDERR,'Mock error: '.$e->getMessage()."\n");}
 fclose($c);
}
