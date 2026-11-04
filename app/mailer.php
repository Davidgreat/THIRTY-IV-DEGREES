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
/** Strict SMTP protocol reader, CLI worker only. */
function smtp_reply($stream,array $allowed):string {
    $response='';do{$line=fgets($stream,2048);if($line===false)throw new RuntimeException('SMTP connection closed');$response.=$line;$continuation=isset($line[3])&&$line[3]==='-';}while($continuation);
    $code=(int)substr($response,0,3);if(!in_array($code,$allowed,true))throw new RuntimeException('SMTP response '.$code);
    return $response;
}
function smtp_cmd($stream,string $command,array $codes):void {fwrite($stream,$command."\r\n");smtp_reply($stream,$codes);}
function smtp_send(string $to,string $subject,string $body):void {
    $host=setting('smtp_host');$port=(int)setting('smtp_port','587');$username=setting('smtp_user');$password=integration_secret('smtp_password');
    $from=setting('smtp_from');$sender=setting('smtp_from_name','Thirthy Vi Degrees');$security=setting('smtp_security','starttls');
    if(!$host||!filter_var($from,FILTER_VALIDATE_EMAIL)||!filter_var($to,FILTER_VALIDATE_EMAIL)||$port<1||$port>65535)throw new RuntimeException('SMTP settings incomplete');
    if(!in_array($security,['starttls','ssl'],true))throw new RuntimeException('TLS is required for SMTP');
    $address=($security==='ssl'?'ssl://':'tcp://').$host.':'.$port;
    $stream=@stream_socket_client($address,$errno,$errstr,12,STREAM_CLIENT_CONNECT);
    if(!$stream)throw new RuntimeException('SMTP connection failed');stream_set_timeout($stream,15);
    try {
        smtp_reply($stream,[220]);smtp_cmd($stream,'EHLO thirtyvidegrees.com',[250]);
        if($security==='starttls'){
            smtp_cmd($stream,'STARTTLS',[220]);
            if(!stream_socket_enable_crypto($stream,true,STREAM_CRYPTO_METHOD_TLS_CLIENT))throw new RuntimeException('SMTP TLS failed');
            smtp_cmd($stream,'EHLO thirtyvidegrees.com',[250]);
        }
        if($username!==''){
            if($password==='')throw new RuntimeException('SMTP password missing');
            smtp_cmd($stream,'AUTH LOGIN',[334]);smtp_cmd($stream,base64_encode($username),[334]);smtp_cmd($stream,base64_encode($password),[235]);
        }
        smtp_cmd($stream,'MAIL FROM:<'.$from.'>',[250]);smtp_cmd($stream,'RCPT TO:<'.$to.'>',[250,251]);smtp_cmd($stream,'DATA',[354]);
        $safeSubject='=?UTF-8?B?'.base64_encode($subject).'?=';
        $sender=preg_replace('/[\r\n]/','',$sender);
        $headers='From: '.($sender?:'Thirthy Vi Degrees').' <'.$from.">\r\n".'To: <'.$to.">\r\n".'Subject: '.$safeSubject."\r\n".'MIME-Version: 1.0'."\r\n".'Content-Type: text/plain; charset=UTF-8'."\r\n".'Content-Transfer-Encoding: base64'."\r\n";
        $payload=$headers."\r\n".chunk_split(base64_encode($body),76,"\r\n");
        fwrite($stream,$payload."\r\n.\r\n");smtp_reply($stream,[250]);smtp_cmd($stream,'QUIT',[221]);
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
