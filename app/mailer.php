<?php
declare(strict_types=1);
/** Transactional email queue. Do not include secrets in messages or logs. */
function email_enqueue(string $eventKey,string $recipient,string $subject,string $body):void {
    $recipient=trim($recipient);
    if(!filter_var($recipient,FILTER_VALIDATE_EMAIL)||strlen($recipient)>190)return;
    if(strlen($eventKey)>190||!preg_match('/^[a-z0-9:_-]+$/i',$eventKey))return;
    sql('INSERT INTO email_queue(event_key,recipient,subject,body,status,available_at) VALUES(?,?,?,?,\'pending\',NOW()) ON DUPLICATE KEY UPDATE id=id',[$eventKey,$recipient,mb_substr(str_replace(["\r","\n"],' ',$subject),0,190),mb_substr($body,0,10000)]);
}
function email_template_defaults():array{return [
 'order_created'=>['subject'=>'Order received · {reference}','body'=>"Hello {name},\n\nYour order {reference} for {amount} has been received. Payment is not confirmed until verified.\n\nThirthy Vi Degrees"],
 'booking_created'=>['subject'=>'Booking inquiry · {reference}','body'=>"Hello {name},\n\nYour booking inquiry {reference} for {date} was received. This is not a booking confirmation.\n\nThirthy Vi Degrees"],
 'payment_success'=>['subject'=>'Payment verified · {reference}','body'=>"Hello {name},\n\nPayment for {reference} has been verified. Amount: {amount}. Private chef bookings still require confirmation.\n\nThirthy Vi Degrees"],
 'order_status'=>['subject'=>'Order update · {reference}','body'=>"Hello {name},\n\nYour order {reference} is now {status}.\n\nThirthy Vi Degrees"],
 'booking_status'=>['subject'=>'Booking update · {reference}','body'=>"Hello {name},\n\nYour booking {reference} for {date} is now {status}.\n\nThirthy Vi Degrees"]
];}
function email_render(string $key,array $data):array{
 $defaults=email_template_defaults();if(!isset($defaults[$key]))throw new InvalidArgumentException('Unknown email template');
 $vars=[];foreach(['name','reference','amount','date','status'] as $field)$vars['{'.$field.'}']=str_replace(["\r","\n"],' ',(string)($data[$field]??''));
 return [strtr(setting('mail_subject_'.$key,$defaults[$key]['subject']),$vars),strtr(setting('mail_body_'.$key,$defaults[$key]['body']),$vars)];
}
function email_queue_template(string $key,string $eventKey,string $recipient,array $data):void{[$subject,$body]=email_render($key,$data);email_enqueue($eventKey,$recipient,$subject,$body);}
function email_enqueue_event(string $event,array $data):void {
 if($event==='order.created'){
  $o=one('SELECT * FROM orders WHERE id=?',[(int)($data['order_id']??0)]);
  if($o)email_queue_template('order_created','order:created:'.$o['id'],$o['email'],['name'=>$o['customer_name'],'reference'=>$o['reference'],'amount'=>money($o['total'])]);
 }elseif($event==='booking.created'){
  $b=one('SELECT * FROM bookings WHERE id=?',[(int)($data['booking_id']??0)]);
  if($b)email_queue_template('booking_created','booking:created:'.$b['id'],$b['email'],['name'=>$b['name'],'reference'=>$b['reference'],'date'=>$b['event_date']]);
 }elseif($event==='payment.success'){
  $pay=$data['payment']??null;if(!is_array($pay))return;
  $kind=$pay['entity_type']??'';if(!in_array($kind,['order','booking'],true))return;
  $r=one('SELECT * FROM '.($kind==='order'?'orders':'bookings').' WHERE id=?',[(int)$pay['entity_id']]);
  if($r)email_queue_template('payment_success','payment:success:'.$pay['id'],$r['email'],['name'=>$r['customer_name']??$r['name']??'Customer','reference'=>$r['reference'],'amount'=>money($pay['amount'])]);
 }elseif(in_array($event,['order.status','booking.status'],true)){
  $kind=explode('.',$event)[0];$id=(int)($data['id']??0);$status=(string)($data['status']??'');
  $r=one('SELECT * FROM '.($kind==='order'?'orders':'bookings').' WHERE id=?',[$id]);
  if($r)email_queue_template($kind.'_status',$kind.':status:'.$id.':'.$status,$r['email'],['name'=>$r['customer_name']??$r['name']??'Customer','reference'=>$r['reference'],'date'=>$r['event_date']??'','status'=>str_replace('_',' ',$status)]);
 }
}
/** SMTP replies contain numeric codes only in errors; never expose server challenges. */
function smtp_reply($stream,array $allowed):string {
 $response='';$code=null;
 for($i=0;$i<100;$i++){
  $line=fgets($stream,2048);if($line===false||!preg_match('/^([0-9]{3})([ -])/',$line,$parts))throw new RuntimeException('SMTP connection closed or returned an invalid reply.');
  if($code!==null&&$code!==(int)$parts[1])throw new RuntimeException('SMTP reply was inconsistent.');$code=(int)$parts[1];$response.=$line;
  if(strlen($response)>32768)throw new RuntimeException('SMTP reply was too large.');
  if($parts[2]===' '){if(!in_array($code,$allowed,true))throw new RuntimeException('SMTP response '.$code,$code);return $response;}
 }
 throw new RuntimeException('SMTP reply did not finish.');
}
function smtp_write($stream,string $text):void {while($text!==''){$written=fwrite($stream,$text);if($written===false||$written===0)throw new RuntimeException('SMTP write failed.');$text=substr($text,$written);}}
function smtp_cmd($stream,string $command,array $codes):void {smtp_write($stream,$command."\r\n");smtp_reply($stream,$codes);}
function smtp_send(string $to,string $subject,string $body):void {
 $host=mail_setting('smtp_host');$port=(int)mail_setting('smtp_port','587');$username=mail_setting('smtp_user');$mode=mail_setting('smtp_auth','password');
 $from=mail_setting('smtp_from');$sender=mail_setting('smtp_from_name','36°');$security=mail_setting('smtp_security','starttls');
 if(!$host||!preg_match('/^[a-z0-9.-]+$/iD',$host)||!filter_var($from,FILTER_VALIDATE_EMAIL)||!filter_var($to,FILTER_VALIDATE_EMAIL)||$port<1||$port>65535||preg_match('/[\x00-\x1f\x7f]/',$username))throw new RuntimeException('SMTP settings are incomplete or invalid.');
 if(!in_array($security,['starttls','ssl'],true))throw new RuntimeException('TLS is required for SMTP.');
 if(!in_array($mode,['password','microsoft'],true))throw new RuntimeException('Select a supported SMTP authentication method.');
 if($mode==='microsoft'&&(!in_array(strtolower($host),['smtp.office365.com','smtp-mail.outlook.com'],true)||$port!==587||$security!=='starttls'||!filter_var($username,FILTER_VALIDATE_EMAIL)))throw new RuntimeException('Microsoft SMTP requires its official server, port 587, STARTTLS and the full mailbox email.');
 $credential=$mode==='microsoft'?microsoft_mail_access_token():integration_secret('smtp_password');
 if($username!==''&&$credential==='')throw new RuntimeException('SMTP credentials are missing.');
 $tls=STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT|(defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')?STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT:0);
 $context=stream_context_create(['ssl'=>['verify_peer'=>true,'verify_peer_name'=>true,'peer_name'=>$host,'allow_self_signed'=>false,'crypto_method'=>$tls]]);
 $stream=@stream_socket_client(($security==='ssl'?'ssl://':'tcp://').$host.':'.$port,$errno,$errstr,12,STREAM_CLIENT_CONNECT,$context);
 if(!$stream)throw new RuntimeException('SMTP connection failed. Check the host, port and hosting outbound-mail access.');stream_set_timeout($stream,15);
 try {
  smtp_reply($stream,[220]);smtp_cmd($stream,'EHLO thirtyvidegrees.com',[250]);
  if($security==='starttls'){smtp_cmd($stream,'STARTTLS',[220]);if(!@stream_socket_enable_crypto($stream,true,$tls))throw new RuntimeException('SMTP TLS certificate verification or encryption failed.');smtp_cmd($stream,'EHLO thirtyvidegrees.com',[250]);}
  if($username!==''){
   if($mode==='microsoft')smtp_cmd($stream,'AUTH XOAUTH2 '.base64_encode('user='.$username."\x01auth=Bearer ".$credential."\x01\x01"),[235]);
   else{smtp_cmd($stream,'AUTH LOGIN',[334]);smtp_cmd($stream,base64_encode($username),[334]);smtp_cmd($stream,base64_encode($credential),[235]);}
  }
  smtp_cmd($stream,'MAIL FROM:<'.$from.'>',[250]);smtp_cmd($stream,'RCPT TO:<'.$to.'>',[250,251]);smtp_cmd($stream,'DATA',[354]);
  $sender=preg_replace('/[\x00-\x1f\x7f]/','',$sender);
  $headers='From: =?UTF-8?B?'.base64_encode($sender?:'36°').'?= <'.$from.">\r\n".'To: <'.$to.">\r\n".'Subject: =?UTF-8?B?'.base64_encode($subject)."?=\r\n".'MIME-Version: 1.0'."\r\n".'Content-Type: text/plain; charset=UTF-8'."\r\n".'Content-Transfer-Encoding: base64'."\r\n";
  smtp_write($stream,$headers."\r\n".chunk_split(base64_encode($body),76,"\r\n")."\r\n.\r\n");smtp_reply($stream,[250]);
  // Once DATA is accepted, a failed QUIT must not turn delivery into a retry/duplicate.
  @fwrite($stream,"QUIT\r\n");
 } finally {fclose($stream);}
}
function email_process_batch(int $limit=15):array {
    $processed=0;$failed=0;$limit=max(1,min(50,$limit));
    sql("UPDATE email_queue SET status=IF(attempts>=5,'failed','retry'),available_at=NOW(),locked_at=NULL,last_error='Worker interrupted; retry scheduled' WHERE status='sending' AND locked_at<DATE_SUB(NOW(),INTERVAL 15 MINUTE)");
    $rows=all("SELECT id FROM email_queue WHERE status IN ('pending','retry') AND available_at<=NOW() ORDER BY id LIMIT ".$limit);
    foreach($rows as $row){
        $id=(int)$row['id'];
        $claimed=sql("UPDATE email_queue SET status='sending',attempts=attempts+1,locked_at=NOW() WHERE id=? AND status IN ('pending','retry') AND available_at<=NOW()",[$id]);
        if(!$claimed->rowCount())continue;
        $item=one('SELECT * FROM email_queue WHERE id=?',[$id]);
        try {smtp_send($item['recipient'],$item['subject'],$item['body']);sql("UPDATE email_queue SET status='sent',sent_at=NOW(),last_error=NULL WHERE id=?",[$id]);$processed++;}
        catch(Throwable $e){$attempts=(int)$item['attempts'];$terminal=$attempts>=5;$delay=min(3600,60*(2**min(5,$attempts)));sql("UPDATE email_queue SET status=?,last_error=?,available_at=DATE_ADD(NOW(),INTERVAL ? SECOND),locked_at=NULL WHERE id=?",[$terminal?'failed':'retry',substr($e->getMessage(),0,220),$delay,$id]);$failed++;}
    }
    return ['sent'=>$processed,'failed_or_retry'=>$failed];
}
