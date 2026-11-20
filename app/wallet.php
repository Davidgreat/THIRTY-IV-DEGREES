<?php
declare(strict_types=1);
function wallet_minor(string $amount):int{if(!preg_match('/^\d{1,9}(?:\.\d{1,2})?$/D',$amount))throw new InvalidArgumentException('Enter a valid amount with up to two decimal places.');$parts=explode('.',$amount);return (int)$parts[0]*100+(int)str_pad($parts[1]??'',2,'0');}
function wallet_balance(int $userId,string $mode):int{return (int)(one('SELECT balance FROM wallet_accounts WHERE user_id=? AND mode=?',[$userId,$mode])['balance']??0);}
function wallet_change(int $userId,string $mode,int $amount,string $key,string $kind,string $reference):bool{
 if(!db()->inTransaction())throw new LogicException('Wallet changes require a transaction');
 sql('INSERT IGNORE INTO wallet_accounts(user_id,mode,balance) VALUES(?,?,0)',[$userId,$mode]);$account=one('SELECT balance FROM wallet_accounts WHERE user_id=? AND mode=? FOR UPDATE',[$userId,$mode]);
 if(one('SELECT id FROM wallet_ledger WHERE event_key=?',[$key]))return false;
 $balance=(int)$account['balance']+$amount;if($balance<0)throw new RuntimeException('Your wallet balance is too low. Top up and try again.');
 sql('UPDATE wallet_accounts SET balance=? WHERE user_id=? AND mode=?',[$balance,$userId,$mode]);sql('INSERT INTO wallet_ledger(user_id,mode,amount,balance_after,event_key,kind,reference) VALUES(?,?,?,?,?,?,?)',[$userId,$mode,$amount,$balance,$key,$kind,$reference]);return true;
}
function wallet_start_topup(int $userId,int $amount,string $nonce,string $expectedMode):string{
 if(!paystack_enabled())throw new RuntimeException('Top-ups are paused. Please try again later.');$mode=paystack_mode();if($expectedMode!==$mode)throw new RuntimeException('Payment mode changed. Refresh your wallet before continuing.');
 if($amount<50000||$amount>50000000)throw new InvalidArgumentException('Top up between ₦500 and ₦500,000.');
 if(!preg_match('/^[a-f0-9]{48}$/D',$nonce)||!hash_equals((string)($_SESSION['wallet_topup_nonce']??''),$nonce))throw new RuntimeException('This top-up form expired. Refresh your wallet.');
 $lock='thirtyvi:wallet:init:'.$userId;if((int)(one('SELECT GET_LOCK(?,10) ok',[$lock])['ok']??0)!==1)throw new RuntimeException('A top-up is already starting. Please wait.');
 try{
  $topup=one('SELECT * FROM wallet_topups WHERE nonce=?',[$nonce]);
  if(!$topup){$account=one('SELECT email FROM users WHERE id=?',[$userId]);$ref=reference('WAL');sql('INSERT INTO wallet_topups(user_id,reference,nonce,amount,mode,email,secret_cipher) VALUES(?,?,?,?,?,?,?)',[$userId,$ref,$nonce,$amount,$mode,$account['email'],seal_secret(paystack_key($mode))]);$topup=one('SELECT * FROM wallet_topups WHERE nonce=?',[$nonce]);}
  if((int)$topup['user_id']!==$userId||(int)$topup['amount']!==$amount||$topup['mode']!==$mode)throw new RuntimeException('A previous top-up uses this form. Check its status or choose New top-up.');
  if($topup['status']==='success')return '/account/wallet';if($topup['status']==='review')throw new RuntimeException('This top-up needs review. Use Check status or contact the team.');if(paystack_safe_checkout($topup['authorization_url']))return $topup['authorization_url'];
  $key=reveal_secret($topup['secret_cipher']);
  if((int)$topup['attempted']){
   try{$existing=paystack_request('GET','transaction/verify/'.rawurlencode($topup['reference']),$key);if(($existing['status']??'')==='success'){wallet_verify_topup($topup['reference']);return '/account/wallet';}throw new RuntimeException('This top-up is awaiting confirmation. Use Check status before starting another.');}catch(RuntimeException $error){if($error->getCode()!==404)throw $error;}
  }
  sql('UPDATE wallet_topups SET attempted=1 WHERE id=?',[$topup['id']]);$data=paystack_request('POST','transaction/initialize',$key,['reference'=>$topup['reference'],'email'=>$topup['email'],'amount'=>(int)$topup['amount'],'currency'=>'NGN','callback_url'=>rtrim((string)cfg()['app_url'],'/').'/payment/verify','metadata'=>['purpose'=>'wallet_topup','mode'=>$mode]]);$url=(string)($data['authorization_url']??'');if(!paystack_safe_checkout($url))throw new RuntimeException('The payment destination could not be verified.');sql('UPDATE wallet_topups SET authorization_url=? WHERE id=?',[$url,$topup['id']]);return $url;
 }finally{sql('SELECT RELEASE_LOCK(?)',[$lock]);}
}
/** A receipt is retriable notification work; it must never undo verified funds. */
function wallet_topup_receipt(array $topup):void {
 try {
  $recipient=one('SELECT name,email FROM users WHERE id=?',[$topup['user_id']]);
  if($recipient)email_queue_template('wallet_topup','wallet:topup:'.$topup['id'],$recipient['email'],['name'=>$recipient['name'],'reference'=>$topup['reference'],'amount'=>money((int)$topup['amount']/100),'status'=>$topup['mode']==='test'?'Test wallet only - no real funds':'Live wallet']);
 }catch(Throwable $error){wallet_verification_diagnostic($topup['reference'],'receipt',$error);}
}
/** Store only diagnostic codes, never provider responses, SQL text or credentials. */
function wallet_verification_diagnostic(string $reference,string $stage,Throwable $error):void {
 $record=['time'=>time(),'reference'=>$reference,'stage'=>$stage,'type'=>get_class($error)];
 if($error instanceof PDOException){$record['sqlstate']=(string)($error->errorInfo[0]??$error->getCode());$record['driver_code']=(int)($error->errorInfo[1]??0);}
 error_log('Wallet verification: '.json_encode($record));
 try{mail_put('wallet_last_verification_error',json_encode($record));}catch(Throwable $ignored){}
}
function wallet_verify_topup(string $reference):bool {
 if(!preg_match('/^WAL-[A-Z0-9]{10}$/D',$reference))return false;
 $topup=one('SELECT * FROM wallet_topups WHERE reference=?',[$reference]);if(!$topup)return false;
 if($topup['status']==='success'){wallet_topup_receipt($topup);return true;}
 $stage='provider';
 try {
  $data=paystack_request('GET','transaction/verify/'.rawurlencode($reference),reveal_secret($topup['secret_cipher']));
  if(($data['status']??'')!=='success')return false;
  if(($data['reference']??'')!==$reference||($data['currency']??'')!=='NGN'||($data['domain']??'')!==$topup['mode']||!isset($data['amount'])||!preg_match('/^[0-9]+$/D',(string)$data['amount'])||(int)$data['amount']!==(int)$topup['amount']){
   sql("UPDATE wallet_topups SET status='review' WHERE id=? AND status<>'success'",[$topup['id']]);
   throw new RuntimeException('This top-up needs a payment review. Contact our team with reference '.$reference.'.');
  }
  $stage='credit';db()->beginTransaction();
  $locked=one('SELECT * FROM wallet_topups WHERE id=? FOR UPDATE',[$topup['id']]);
  if($locked['status']!=='success'){
   wallet_change((int)$locked['user_id'],$locked['mode'],(int)$locked['amount'],'topup:'.$locked['id'],'topup',$reference);
   sql("UPDATE wallet_topups SET status='success' WHERE id=?",[$locked['id']]);
  }
  db()->commit();
 }catch(Throwable $error){
  if(db()->inTransaction())db()->rollBack();wallet_verification_diagnostic($reference,$stage,$error);
  if($error instanceof RuntimeException && !($error instanceof PDOException))throw $error;
  throw new RuntimeException('Your top-up could not be credited yet. Do not pay again. Contact our team with reference '.$reference.' (WALLET-CREDIT).',0,$error);
 }
 wallet_topup_receipt($topup);return true;
}
/** Bounded round-robin recovery; also works when the browser never returns. */
function wallet_reconcile_batch(int $limit=3):array {
 $lock='thirtyvi-wallet-worker-'.substr(hash('sha256',(string)cfg()['db_name']),0,20);
 if((int)(one('SELECT GET_LOCK(?,0) ok',[$lock])['ok']??0)!==1)return ['checked'=>0,'credited'=>0,'busy'=>true];
 $checked=0;$credited=0;$failed=0;$limit=max(1,min(10,$limit));
 try {
  $cursor=(int)mail_setting('wallet_reconcile_cursor','0');
  $select="SELECT id,reference FROM wallet_topups WHERE attempted=1 AND status='initialized' AND id>? ORDER BY id LIMIT ".$limit;
  $rows=all($select,[$cursor]);if(!$rows&&$cursor)$rows=all($select,[0]);
  foreach($rows as $row){$checked++;try{if(wallet_verify_topup($row['reference']))$credited++;}catch(Throwable $error){$failed++;}mail_put('wallet_reconcile_cursor',(string)$row['id']);}
  // Repair receipts separately, including credits already committed before a queue failure.
  foreach(all("SELECT t.* FROM wallet_topups t WHERE t.status='success' AND NOT EXISTS(SELECT 1 FROM email_queue q WHERE q.event_key=CONCAT('wallet:topup:',t.id)) ORDER BY t.id LIMIT ".$limit) as $topup)wallet_topup_receipt($topup);
  $result=['checked'=>$checked,'credited'=>$credited,'failed'=>$failed];mail_put('wallet_worker_last_result',json_encode(['time'=>time(),'result'=>$result]));return $result;
 }finally{sql('SELECT RELEASE_LOCK(?)',[$lock]);}
}
function wallet_pay(int $userId,string $kind,int $id):bool{
 if(!in_array($kind,['order','booking'],true))throw new InvalidArgumentException('Invalid request');$mode=paystack_mode();$lock='thirtyvi:pay:'.$kind.':'.$id;
 if((int)(one('SELECT GET_LOCK(?,10) ok',[$lock])['ok']??0)!==1)throw new RuntimeException('Payment is being processed. Please wait.');
 $payment=null;
 try{db()->beginTransaction();$record=one('SELECT * FROM '.($kind==='order'?'orders':'bookings').' WHERE id=? AND user_id=? FOR UPDATE',[$id,$userId]);if(!$record||!paystack_can_pay($kind,$record))throw new RuntimeException('This request is not payable from your account.');
  if(one("SELECT id FROM payments WHERE entity_type=? AND entity_id=? AND status='initialized'",[$kind,$id]))throw new RuntimeException('A Paystack checkout already exists for this request. Continue that payment or contact the team before switching methods.');
  if(one("SELECT p.id FROM payments p JOIN payment_gateway_context c ON c.payment_id=p.id WHERE p.entity_type=? AND p.entity_id=? AND p.status='success' AND c.mode<>?",[$kind,$id,$mode]))throw new RuntimeException('Payments for this request use a different environment. Contact our team.');
  $sum=one("SELECT COALESCE(SUM(amount),0) total FROM payments WHERE entity_type=? AND entity_id=? AND status='success'",[$kind,$id]);$received=wallet_minor(number_format((float)$sum['total'],2,'.',''));$total=wallet_minor(number_format((float)($kind==='order'?$record['total']:$record['quote']),2,'.',''));$amount=$total-$received;
  if($kind==='booking'&&$received===0&&(float)$record['deposit']>0)$amount=min($amount,wallet_minor(number_format((float)$record['deposit'],2,'.','')));if($amount<=0)throw new RuntimeException('There is no outstanding balance.');
  $reference=reference('WSP');sql('INSERT INTO payments(reference,entity_type,entity_id,amount) VALUES(?,?,?,?)',[$reference,$kind,$id,number_format($amount/100,2,'.','')]);$pid=(int)db()->lastInsertId();sql('INSERT INTO payment_gateway_context(payment_id,mode,secret_cipher) VALUES(?,?,?)',[$pid,$mode,'']);
  wallet_change($userId,$mode,-$amount,'spend:'.$pid,'payment',$record['reference']);sql('INSERT INTO wallet_spends(payment_id,user_id,entity_type,entity_id,mode,amount) VALUES(?,?,?,?,?,?)',[$pid,$userId,$kind,$id,$mode,$amount]);$payment=one('SELECT * FROM payments WHERE id=?',[$pid]);
  paystack_record_success($payment,['reference'=>$reference,'status'=>'success','amount'=>$amount,'currency'=>'NGN','domain'=>$mode],true);$payment['status']='success';email_enqueue_event('payment.success',['payment'=>$payment]);db()->commit();run_hook('payment.success',['payment'=>$payment]);return true;
 }catch(Throwable $error){if(db()->inTransaction())db()->rollBack();throw $error;}finally{sql('SELECT RELEASE_LOCK(?)',[$lock]);}
}
function wallet_refund(int $id):void{
 $source=one('SELECT w.* FROM refund_requests r JOIN wallet_spends w ON w.payment_id=r.payment_id WHERE r.id=?',[$id]);if(!$source)throw new RuntimeException('Wallet payment not found.');
 db()->beginTransaction();try{one('SELECT id FROM '.($source['entity_type']==='order'?'orders':'bookings').' WHERE id=? FOR UPDATE',[$source['entity_id']]);one('SELECT payment_id FROM wallet_spends WHERE payment_id=? FOR UPDATE',[$source['payment_id']]);$refund=one("SELECT r.*,w.user_id,w.mode,w.amount original_amount FROM refund_requests r JOIN wallet_spends w ON w.payment_id=r.payment_id WHERE r.id=? FOR UPDATE",[$id]);if(!$refund||!in_array($refund['status'],['requested','submitting','completed'],true))throw new RuntimeException('This wallet refund is not eligible.');if($refund['status']==='completed'){db()->commit();return;}
  $amount=wallet_minor(number_format((float)$refund['amount'],2,'.',''));$total=one("SELECT COALESCE(SUM(amount),0) total FROM refund_requests WHERE payment_id=? AND status='completed'",[$refund['payment_id']]);if($amount<=0||$amount+wallet_minor(number_format((float)$total['total'],2,'.',''))>(int)$refund['original_amount'])throw new RuntimeException('Refund exceeds the wallet payment.');
  wallet_change((int)$refund['user_id'],$refund['mode'],$amount,'refund:'.$id,'refund','Refund #'.$id);sql("UPDATE refund_requests SET status='completed',gateway_status='wallet_credited',updated_at=NOW() WHERE id=?",[$id]);refund_recompute_entity((int)$refund['payment_id']);email_enqueue_event('refund.completed',['refund_id'=>$id]);db()->commit();
 }catch(Throwable $error){if(db()->inTransaction())db()->rollBack();throw $error;}
 run_hook('refund.completed',['refund_id'=>$id]);
}
