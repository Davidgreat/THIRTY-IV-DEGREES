<?php
declare(strict_types=1);
function cron_health_state():array {
 $run=json_decode(mail_setting('cron_last_run','{}'),true)?:[];
 $probe=json_decode(mail_setting('cron_probe_requested','{}'),true)?:[];
 $observed=json_decode(mail_setting('cron_probe_observed','{}'),true)?:[];
 $matched=!empty($probe['id'])&&hash_equals((string)$probe['id'],(string)($observed['id']??''));
 $receipt=$probe?one('SELECT status,attempts,sent_at FROM email_queue WHERE event_key=?',['cron:probe:'.$probe['id']]):null;
 return ['now'=>time(),'recent'=>!empty($run['started_at'])&&$run['started_at']>time()-180,'run'=>$run,'probe'=>$probe?['requested_at'=>$probe['requested_at'],'observed_at'=>$matched?($observed['observed_at']??null):null,'expired'=>!$matched&&time()-(int)$probe['requested_at']>180,'email'=>$receipt]:null];
}
function cron_probe_start():never {
 if(admin()['role']!=='admin')abort(403,'Owner access required');rate_limit('cron_probe',6,3600);
 $recipient=str('recipient',190);if(!filter_var($recipient,FILTER_VALIDATE_EMAIL)){flash('Enter a valid recipient for the queued cron test email.','error');go('admin/cron');}
 $lock='thirtyvi-cron-probe-'.substr(hash('sha256',(string)cfg()['db_name']),0,20);
 if((int)(one('SELECT GET_LOCK(?,5) ok',[$lock])['ok']??0)!==1){flash('A test is already being prepared. Try shortly.','error');go('admin/cron');}
 try{
  $old=json_decode(mail_setting('cron_probe_requested','{}'),true)?:[];$seen=json_decode(mail_setting('cron_probe_observed','{}'),true)?:[];
  if($old&&time()-(int)$old['requested_at']<180&&($seen['id']??'')!==$old['id']){flash('A cron test is already waiting. This page will update when the scheduled worker arrives.');}
  else{
   $id=bin2hex(random_bytes(16));db()->beginTransaction();
   email_enqueue('cron:probe:'.$id,$recipient,'36° Cron delivery test',"Your scheduled delivery queue processed this test email.\n\nA manually drained queue can also send this message; the Admin Cron health page separately confirms whether the scheduled CLI worker was observed.\n\nRequested: ".date('d M Y H:i:s T'));
   mail_put('cron_probe_requested',json_encode(['id'=>$id,'requested_at'=>time()]));db()->commit();
   flash('Cron test armed and email queued. Leave this page open for up to three minutes. This button does not run the worker.');
  }
 }catch(Throwable $error){if(db()->inTransaction())db()->rollBack();flash('The test could not be queued. Check Email delivery and hosting logs.','error');}
 finally{sql('SELECT RELEASE_LOCK(?)',[$lock]);}
 go('admin/cron');
}
/** Only the real CLI entry point calls this. Admin tests never execute shell commands. */
function cron_worker_run():array {
 if(PHP_SAPI!=='cli')throw new LogicException('CLI worker required');
 $lock='thirtyvi-scheduled-worker-'.substr(hash('sha256',(string)cfg()['db_name']),0,20);
 if((int)(one('SELECT GET_LOCK(?,0) ok',[$lock])['ok']??0)!==1)return ['busy'=>true];
 $run=['started_at'=>time(),'state'=>'running','php_version'=>PHP_VERSION,'php_binary'=>PHP_BINARY,'build'=>'6.6.2','extensions'=>['pdo_mysql'=>extension_loaded('pdo_mysql'),'openssl'=>extension_loaded('openssl'),'curl'=>extension_loaded('curl'),'mbstring'=>extension_loaded('mbstring')]];
 try{
  mail_put('cron_last_run',json_encode($run));$probe=json_decode(mail_setting('cron_probe_requested','{}'),true)?:[];
  if(!empty($probe['id']))mail_put('cron_probe_observed',json_encode(['id'=>$probe['id'],'observed_at'=>time()]));
  // Recover missing receipts before the mail batch, so they can leave in this run.
  try{$run['receipt_repair']=wallet_repair_receipts(20);}catch(Throwable $error){$run['receipt_error']=get_class($error);}
  try{$run['mail']=email_worker_run(15,'cron');}catch(Throwable $error){$run['mail_error']=get_class($error);error_log('Mail worker failed: '.get_class($error));}
  try{$run['wallet']=wallet_reconcile_batch(3);}catch(Throwable $error){$run['wallet_error']=get_class($error);wallet_verification_diagnostic('','worker',$error);}
  $run['finished_at']=time();$run['state']=isset($run['mail_error'])||isset($run['wallet_error'])||isset($run['receipt_error'])?'error':'finished';
  mail_put('cron_last_run',json_encode($run));return $run;
 }finally{sql('SELECT RELEASE_LOCK(?)',[$lock]);}
}
function cron_health_page():void {
 if(admin()['role']!=='admin')abort(403,'Owner access required');header('Cache-Control: private, no-store');
 admin_page('Cron health & delivery test',function(){ $state=cron_health_state(); ?>
 <section class="form"><h2>Is the scheduled worker running?</h2><p>Use a real scheduled-run test. Starting a test queues an email and waits for your hosting cron to arrive; it does not run cron from your browser.</p><div class="notice" role="status" aria-live="polite" data-cron-summary></div><div data-cron-probe aria-live="polite"></div><p><strong>Schedule:</strong> every minute — <code>* * * * *</code></p><label for="cron-command">Hosting cron command</label><input readonly id="cron-command" value="<?=esc(email_cron_command())?>"><button class="btn" type="button" data-copy-target="cron-command">Copy command</button><p class="muted small">The observed CLI binary below is stronger evidence than the web PHP binary used to suggest this command. Keep the correct PHP 8.1+ CLI path supplied by your host. Hosting dashboard settings alone do not prove execution.</p>
 <form method="post" action="/admin/cron/test"><?=token()?><label for="cron-recipient">Test email recipient</label><input id="cron-recipient" name="recipient" type="email" maxlength="190" required value="<?=esc(admin()['email'])?>"><button class="btn gold">Start scheduled cron & email test</button></form><p class="small muted">Wait up to three minutes. A fresh CLI heartbeat proves the script reached this database. An accepted test email proves queue-to-SMTP submission, not inbox delivery. Manual queue runs never count as a scheduled-run test.</p><noscript>JavaScript is disabled. Reload this page after one minute to inspect the status below.</noscript>
 <pre data-cron-details style="white-space:pre-wrap;overflow-wrap:anywhere"><?=esc(json_encode($state,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES))?></pre></section>
 <section class="form"><h2>Wallet receipt delivery</h2><p>Paystack sends its own receipt. Your platform queues a separate receipt after verified credit. Check the delivery status here; “sent” means the SMTP server accepted it.</p><form method="post" action="/admin/cron/receipts"><?=token()?><button class="btn">Queue missing verified top-up receipts</button></form><p class="small muted">Repairs up to 20 missing receipts without changing balances or duplicating existing queued/sent messages. Retry failed messages in <a href="/admin/email">Email delivery</a>.</p><div class="scroll"><table><tr><th>Top-up</th><th>Payment mode / status</th><th>Receipt recipient</th><th>Email status</th><th>Attempts</th><th>Accepted at</th></tr><?php foreach(all("SELECT t.reference,t.mode,t.status topup_status,COALESCE(q.recipient,u.email) email,q.status mail_status,q.attempts,q.sent_at FROM wallet_topups t JOIN users u ON u.id=t.user_id LEFT JOIN email_queue q ON q.event_key=CONCAT('wallet:topup:',t.id) ORDER BY t.id DESC LIMIT 30") as $row):?><tr><td><?=esc($row['reference'])?></td><td><?=esc($row['mode'].' / '.$row['topup_status'])?></td><td><?=esc($row['email'])?></td><td><?=esc($row['mail_status']??($row['topup_status']==='success'?'Missing receipt':'Awaiting verified credit'))?></td><td><?=(int)($row['attempts']??0)?></td><td><?=esc($row['sent_at']??'—')?></td></tr><?php endforeach;?></table></div></section>
 <section class="form"><h2>If no scheduled run appears</h2><p>Confirm the cron is saved, all five schedule fields are *, the PHP executable exists, and the script path points to this installation. Check the host’s cron output/error email for permission, missing PHP extension or database connection errors. This page cannot inspect or change your hosting scheduler.</p><p>If the run is recent but email is pending, inspect the queue backlog. Retry/failed messages need SMTP delivery checks. If sent, check Spam, sender bounces and SPF/DKIM/DMARC.</p><a class="btn" href="/admin/email">Open email delivery & queue →</a></section>
 <script type="application/json" id="cron-initial"><?=json_encode($state,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?></script><script src="/public/assets/cron-662.js?v=6.6.2" defer></script>
 <?php });
}
