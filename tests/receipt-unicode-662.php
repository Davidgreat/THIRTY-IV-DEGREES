<?php
require '/srv/upgrade662/app/core.php';
if(cfg()['db_name']!=='thirtyvi_upgrade662')exit('Isolated fixture required');
function verify662(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);echo 'PASS: '.$message.PHP_EOL;}
if(($argv[1]??'')==='before'){
 sql("INSERT INTO users(name,email,password) VALUES('Unicode receipt fixture','unicode-receipt@example.test','disabled')");$uid=(int)db()->lastInsertId();
 sql("INSERT INTO wallet_topups(user_id,reference,nonce,amount,mode,email,secret_cipher,status,attempted) VALUES(?,?,?,75000,'live','unicode-receipt@example.test',?,'success',1)",[$uid,reference('WAL'),bin2hex(random_bytes(24)),seal_secret('sk_live_'.str_repeat('b',24))]);$id=(int)db()->lastInsertId();
 db()->beginTransaction();wallet_change($uid,'live',75000,'topup:'.$id,'topup','Synthetic verified top-up');db()->commit();mail_put('unicode_fixture_id',(string)$id);
 wallet_topup_receipt(one('SELECT * FROM wallet_topups WHERE id=?',[$id]));$diagnostic=json_decode(mail_setting('wallet_last_verification_error'),true);
 verify662(($diagnostic['driver_code']??0)===1366,'Reproduced reported receipt PDO error 1366 against a Latin-1 queue');
 verify662(!one('SELECT id FROM email_queue WHERE event_key=?',['wallet:topup:'.$id]),'Legacy queue rejects the naira receipt');verify662(wallet_balance($uid,'live')===75000,'Credited wallet remains intact despite receipt error');
 foreach(['smtp_auth'=>'password','smtp_host'=>'localhost','smtp_port'=>'587','smtp_security'=>'starttls','smtp_user'=>'mailer@example.test','smtp_from'=>'mailer@example.test','smtp_from_name'=>'Unicode fixture'] as $key=>$value)mail_put($key,$value);mail_put('smtp_password',seal_secret('smtp-fixture-password'));
}else{
 $id=(int)mail_setting('unicode_fixture_id');$topup=one('SELECT * FROM wallet_topups WHERE id=?',[$id]);$before=wallet_balance((int)$topup['user_id'],'live');
 verify662(wallet_repair_receipts(20)===1,'After updater migration the missing receipt queues successfully');wallet_repair_receipts(20);
 $queued=one('SELECT * FROM email_queue WHERE event_key=?',['wallet:topup:'.$id]);verify662(str_contains($queued['body'],'₦750.00'),'Naira amount is preserved correctly in the receipt');
 verify662((int)one('SELECT COUNT(*) n FROM email_queue WHERE event_key=?',['wallet:topup:'.$id])['n']===1,'Unicode recovery does not duplicate the receipt');verify662(wallet_balance((int)$topup['user_id'],'live')===$before,'Unicode migration and repair do not change credited funds');
 verify662(one('SELECT body FROM email_queue WHERE event_key=?',['fixture:existing'])['body']==='Existing welcome message','Existing email content survives conversion');
 $result=cron_worker_run();verify662(one('SELECT status FROM email_queue WHERE id=?',[$queued['id']])['status']==='sent','Recovered receipt is accepted by local SMTP through the actual CLI cron worker');
}
