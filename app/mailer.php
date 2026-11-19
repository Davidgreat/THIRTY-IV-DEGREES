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
 'account_email_changed'=>['subject'=>'Your 36° login email changed','body'=>"Hello {name},\n\nYour account sign-in email was changed. If this was not you, contact our team immediately.\n\n36°"],
 'refund_completed'=>['subject'=>'Refund processed · {reference}','body'=>"Hello {name},\n\nThe payment provider marked a refund of {amount} for {reference} as processed. The time it takes to appear in your account depends on your bank.\n\n36°"],
 'account_created'=>['subject'=>'Welcome to 36°','body'=>"Hello {name},\n\nYour account is ready. You can now order meals, track your orders and plan private dining with us.\n\n36°"],
 'password_reset'=>['subject'=>'Reset your 36° password','body'=>"Use this link within 30 minutes to choose a new password:\n\n{link}\n\nIf you did not request this, ignore this email.\n\n36°"],
 'password_changed'=>['subject'=>'Your 36° password was changed','body'=>"Hello {name},\n\nYour account password was changed successfully. If this was not you, contact our team immediately.\n\n36°"],
 'order_completed'=>['subject'=>'Order completed · {reference}','body'=>"Hello {name},\n\nYour order {reference} is now completed. Thank you for dining with 36°.\n\n36°"],
 'order_cancelled'=>['subject'=>'Order cancelled · {reference}','body'=>"Hello {name},\n\nYour order {reference} has been cancelled. If you paid, contact our team about your refund status. This cancellation notice does not confirm a refund.\n\n36°"],
 'booking_quoted'=>['subject'=>'Your quotation · {reference}','body'=>"Hello {name},\n\nYour booking {reference} for {date} has been quoted at {quote}. The requested deposit is {deposit}. Our team will confirm the arrangements with you.\n\n36°"],
 'order_created'=>['subject'=>'Order received · {reference}','body'=>"Hello {name},\n\nYour order {reference} for {amount} has been received. Payment is not confirmed until verified.\n\nThirthy Vi Degrees"],
 'booking_created'=>['subject'=>'Booking inquiry · {reference}','body'=>"Hello {name},\n\nYour booking inquiry {reference} for {date} was received. This is not a booking confirmation.\n\nThirthy Vi Degrees"],
 'payment_success'=>['subject'=>'Payment verified · {reference}','body'=>"Hello {name},\n\nPayment for {reference} has been verified. Amount: {amount}. Private chef bookings still require confirmation.\n\nThirthy Vi Degrees"],
 'order_status'=>['subject'=>'Order update · {reference}','body'=>"Hello {name},\n\nYour order {reference} is now {status}.\n\nThirthy Vi Degrees"],
 'booking_status'=>['subject'=>'Booking update · {reference}','body'=>"Hello {name},\n\nYour booking {reference} for {date} is now {status}.\n\nThirthy Vi Degrees"]
];}
function email_render(string $key,array $data):array{
 $defaults=email_template_defaults();if(!isset($defaults[$key]))throw new InvalidArgumentException('Unknown email template');
 $vars=[];foreach(['name','reference','amount','date','status','link','quote','deposit'] as $field)$vars['{'.$field.'}']=str_replace(["\r","\n"],' ',(string)($data[$field]??''));
 return [strtr(setting('mail_subject_'.$key,$defaults[$key]['subject']),$vars),strtr(setting('mail_body_'.$key,$defaults[$key]['body']),$vars)];
}
function email_queue_template(string $key,string $eventKey,string $recipient,array $data):void{[$subject,$body]=email_render($key,$data);email_enqueue($eventKey,$recipient,$subject,$body);}
function email_enqueue_event(string $event,array $data):void {
 if($event==='account.created'){
  $account=one('SELECT id,name,email FROM users WHERE id=?',[(int)($data['user_id']??0)]);if($account)email_queue_template('account_created','account:created:'.$account['id'],$account['email'],['name'=>$account['name']]);
 }elseif($event==='order.created'){
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
 }elseif($event==='refund.completed'){
  $refund=one("SELECT r.*,p.entity_type,p.entity_id FROM refund_requests r JOIN payments p ON p.id=r.payment_id WHERE r.id=? AND r.status='completed' AND r.amount>0",[(int)($data['refund_id']??0)]);
  if($refund&&in_array($refund['entity_type'],['order','booking'],true)){$entity=one('SELECT * FROM '.($refund['entity_type']==='order'?'orders':'bookings').' WHERE id=?',[$refund['entity_id']]);if($entity)email_queue_template('refund_completed','refund:completed:'.$refund['id'],$entity['email'],['name'=>$entity['customer_name']??$entity['name'],'reference'=>$entity['reference'],'amount'=>money($refund['amount'])]);}
 }elseif(in_array($event,['order.status','booking.status'],true)){
  $kind=explode('.',$event)[0];$id=(int)($data['id']??0);$status=(string)($data['status']??'');
  $r=one('SELECT * FROM '.($kind==='order'?'orders':'bookings').' WHERE id=?',[$id]);
  if($r){$template=$kind.'_status';if($kind==='order'&&in_array($status,['completed','cancelled'],true))$template='order_'.$status;if($kind==='booking'&&$status==='quoted')$template='booking_quoted';
   $eventKey=$kind.':status:'.$id.':'.(isset($data['event_id'])?'event:'.(int)$data['event_id']:$status);
   email_queue_template($template,$eventKey,$r['email'],['name'=>$r['customer_name']??$r['name']??'Customer','reference'=>$r['reference'],'date'=>$r['event_date']??'','status'=>str_replace('_',' ',$status),'quote'=>money($r['quote']??0),'deposit'=>money($r['deposit']??0)]);
  }
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
    $processed=0;$failed=0;$skipped=0;$limit=max(1,min(50,$limit));
    sql("UPDATE email_queue SET status=IF(attempts>=5,'failed','retry'),available_at=NOW(),locked_at=NULL,last_error='Worker interrupted; retry scheduled' WHERE status='sending' AND locked_at<DATE_SUB(NOW(),INTERVAL 15 MINUTE)");
    $rows=all("SELECT id FROM email_queue WHERE status IN ('pending','retry') AND available_at<=NOW() ORDER BY CASE WHEN event_key LIKE 'password:reset:%' THEN 0 ELSE 1 END,id LIMIT ".$limit);
    foreach($rows as $row){
        $id=(int)$row['id'];
        $claimed=sql("UPDATE email_queue SET status='sending',attempts=attempts+1,locked_at=NOW() WHERE id=? AND status IN ('pending','retry') AND available_at<=NOW()",[$id]);
        if(!$claimed->rowCount())continue;
        $item=one('SELECT * FROM email_queue WHERE id=?',[$id]);
        if(!email_queue_item_current($item)){sql("UPDATE email_queue SET status='failed',locked_at=NULL,last_error='Not sent: password reset expired or was replaced. Request a new link.' WHERE id=?",[$id]);$skipped++;continue;}
        try {smtp_send($item['recipient'],$item['subject'],$item['body']);sql("UPDATE email_queue SET status='sent',sent_at=NOW(),last_error=NULL WHERE id=?",[$id]);$processed++;}
        catch(Throwable $e){$attempts=(int)$item['attempts'];$terminal=$attempts>=5;$delay=min(3600,60*(2**min(5,$attempts)));sql("UPDATE email_queue SET status=?,last_error=?,available_at=DATE_ADD(NOW(),INTERVAL ? SECOND),locked_at=NULL WHERE id=?",[$terminal?'failed':'retry',substr($e->getMessage(),0,220),$delay,$id]);$failed++;}
    }
    return ['sent'=>$processed,'failed_or_retry'=>$failed,'skipped'=>$skipped];
}

/** Never send an expired, replaced or wrong-account reset link from an old backlog. */
function email_queue_item_current(array $item):bool{
 if(!str_starts_with((string)$item['event_key'],'password:reset:'))return true;
 $hash=substr($item['event_key'],15);if(!preg_match('/^[a-f0-9]{64}$/D',$hash))return false;
 return one('SELECT r.user_id FROM account_reset_tokens r JOIN users u ON u.id=r.user_id WHERE r.token_hash=? AND r.expires_at>NOW() AND u.email=?',[$hash,$item['recipient']])!==null;
}
function email_worker_run(int $limit=15,string $origin='cron'):array{
 $name='thirtyvi-email-worker-'.substr(hash('sha256',(string)cfg()['db_name']),0,20);
 if((int)(one('SELECT GET_LOCK(?,0) ok',[$name])['ok']??0)!==1)return ['sent'=>0,'failed_or_retry'=>0,'skipped'=>0,'busy'=>true];
 try{
  if($origin==='cron')mail_put('mail_cron_last_started',(string)time());
  $result=email_process_batch($limit);$record=['time'=>time(),'origin'=>$origin,'result'=>$result];mail_put('mail_worker_last_result',json_encode($record));
  if($origin==='cron')mail_put('mail_cron_last_finished',(string)time());return $result;
 }finally{sql('SELECT RELEASE_LOCK(?)',[$name]);}
}
function email_cron_command():string{
 $binary=PHP_BINDIR.'/php';if(!is_executable($binary))$binary=is_executable('/usr/local/bin/php')?'/usr/local/bin/php':'php';
 return escapeshellarg($binary).' '.escapeshellarg((string)realpath(ROOT.'/app/cron-mail.php'));
}
