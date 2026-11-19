<?php
require '/srv/site/app/core.php';
if(cfg()['db_name']!=='thirtyvi_dev')exit('Disposable fixture only');
$held=all("SELECT id,available_at FROM email_queue WHERE status IN ('pending','retry') AND available_at<=NOW()");
try{
 foreach($held as $row)sql('UPDATE email_queue SET available_at=DATE_ADD(NOW(),INTERVAL 1 DAY) WHERE id=?',[$row['id']]);
 microsoft_mail_store_tokens(['access_token'=>'mock-access-99','refresh_token'=>'mock-refresh-99','expires_in'=>3600]);
 $token=microsoft_mail_tokens();$token['expires']=time()-1;mail_put('ms_mail_tokens',seal_secret(json_encode($token)));
 file_put_contents('/tmp/mail-oauth-state.json',json_encode([]));
 $event='email640-worker-'.bin2hex(random_bytes(6));email_enqueue($event,'worker@example.test','Local worker test','Synthetic local message');
 $result=email_process_batch(1);$row=one('SELECT status FROM email_queue WHERE event_key=?',[$event]);
 if($result['sent']!==1||$row['status']!=='sent')throw new RuntimeException('Worker did not mark local SMTP acceptance');
 echo "PASS: CLI worker refreshes Microsoft authorization and marks accepted queued message sent\n";
 if(microsoft_mail_tokens()['refresh']==='mock-refresh-99')throw new RuntimeException('Refresh token was not rotated');
 echo "PASS: CLI worker persists rotated refresh token\n";
}finally{foreach($held as $row)sql('UPDATE email_queue SET available_at=? WHERE id=?',[$row['available_at'],$row['id']]);}
