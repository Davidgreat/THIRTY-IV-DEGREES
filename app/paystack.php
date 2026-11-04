<?php
declare(strict_types=1);
/** All checkout, verification and refund calls use the same mode-aware gateway. */
function paystack_mode():string {
 $mode=setting('paystack_mode');
 if(in_array($mode,['test','live'],true))return $mode;
 return str_starts_with(integration_secret('paystack_secret'),'sk_live_')?'live':'test';
}
function paystack_key(string $mode):string {
 if(!in_array($mode,['test','live'],true))return '';
 $key=integration_secret('paystack_'.$mode.'_secret');
 if($key===''){$legacy=integration_secret('paystack_secret');if(str_starts_with($legacy,'sk_'.$mode.'_'))$key=$legacy;}
 return $key;
}
function paystack_enabled():bool {
 // Preserve already-configured installations until the owner changes this setting.
 return setting('paystack_enabled',integration_secret('paystack_secret')!==''?'1':'0')==='1' && paystack_key(paystack_mode())!=='';
}
function paystack_payment_key(array $payment):string {
 $context=one('SELECT * FROM payment_gateway_context WHERE payment_id=?',[$payment['id']]);
 return $context?reveal_secret($context['secret_cipher']):integration_secret('paystack_secret');
}
function paystack_request(string $method,string $path,string $secret,?array $payload=null):array {
 if($secret==='')throw new RuntimeException('Paystack credentials are not configured.');
 if(!function_exists('curl_init'))throw new RuntimeException('PHP cURL is required for payments.');
 $ch=curl_init('https://api.paystack.co/'.ltrim($path,'/'));
 $opts=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>25,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$secret,'Content-Type: application/json'],CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS];
 if($method==='POST'){$opts[CURLOPT_POST]=true;$opts[CURLOPT_POSTFIELDS]=json_encode($payload,JSON_THROW_ON_ERROR);}
 curl_setopt_array($ch,$opts);$raw=curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
 $data=json_decode((string)$raw,true);
 if($raw===false||$http<200||$http>=300||!is_array($data)||empty($data['status'])||!is_array($data['data']??null))throw new RuntimeException('Paystack could not complete this request. Please retry or contact support.',$http);
 return $data['data'];
}
function paystack_valid_transaction(array $payment,array $data,?string $mode=null):bool {
 return ($data['status']??'')==='success'
  && ($data['reference']??'')===$payment['reference']
  && ($data['currency']??'')===$payment['currency']
  && is_numeric($data['amount']??null)
  && (float)$data['amount']===(float)round((float)$payment['amount']*100)
  && ($mode===null||($data['domain']??'')===$mode);
}
function paystack_safe_checkout(string $url):bool {
 $u=parse_url($url);
 return is_array($u)&&($u['scheme']??'')==='https'&&strtolower($u['host']??'')==='checkout.paystack.com'&&!isset($u['user'])&&!isset($u['pass'])&&(!isset($u['port'])||$u['port']===443);
}
function paystack_can_pay(string $kind,array $record):bool {
 if(in_array($record['payment_status'],['paid','refunded'],true)||in_array($record['status'],['cancelled','completed'],true))return false;
 return $kind==='order'||($kind==='booking'&&in_array($record['status'],['quoted','awaiting_deposit','confirmed'],true)&&(float)$record['quote']>0);
}
function paystack_save_settings(array $input):void {
 $mode=(string)($input['paystack_mode']??'test');$enabled=isset($input['paystack_enabled'])?'1':'0';
 if(!in_array($mode,['test','live'],true))throw new InvalidArgumentException('Choose test or live mode.');
 $values=[];
 foreach(['test','live'] as $m){
  foreach(['public'=>'pk','secret'=>'sk'] as $type=>$prefix){
   $name='paystack_'.$m.'_'.$type;$value=trim((string)($input[$name]??''));
   if($value!==''&&!preg_match('/^'.$prefix.'_'.$m.'_[A-Za-z0-9]{10,200}$/D',$value))throw new InvalidArgumentException(ucfirst($m).' '.$type.' key has an invalid format or belongs to a different mode.');
   $values[$name]=$value;
  }
 }
 $effective=$values['paystack_'.$mode.'_secret']?:paystack_key($mode);
 $public=$values['paystack_'.$mode.'_public']?:setting('paystack_'.$mode.'_public');
 if($public===''){$legacy=setting('paystack_public');if(str_starts_with($legacy,'pk_'.$mode.'_'))$public=$legacy;}
 if($enabled==='1'&&($effective===''||$public===''))throw new InvalidArgumentException('Save both public and secret keys for the selected mode before enabling payments.');
 db()->beginTransaction();
 try {
  foreach($values as $name=>$value)if($value!=='')save_integration($name,$value,str_ends_with($name,'_secret'));
  save_integration('paystack_mode',$mode);save_integration('paystack_enabled',$enabled);
  audit('paystack.settings','mode='.$mode.'; enabled='.$enabled);
  db()->commit();
 }catch(Throwable $e){db()->rollBack();throw $e;}
}
/** A persistent checkout is reused for retries, including after a network timeout. */
function paystack_checkout(string $kind,int $id):string {
 if(!in_array($kind,['order','booking'],true))throw new InvalidArgumentException('Invalid payment type.');
 if(!paystack_enabled())throw new RuntimeException('Online payments are currently unavailable. Your request is saved; please contact our team.');
 $lock='thirtyvi:pay:'.$kind.':'.$id;
 $got=one('SELECT GET_LOCK(?,10) acquired',[$lock]);
 if((int)($got['acquired']??0)!==1)throw new RuntimeException('Payment is being processed. Please wait and try again.');
 try {
  $record=one('SELECT * FROM '.($kind==='order'?'orders':'bookings').' WHERE id=?',[$id]);
  if(!$record||!paystack_can_pay($kind,$record))throw new RuntimeException('This request is not payable.');
  $pending=all("SELECT * FROM payments WHERE entity_type=? AND entity_id=? AND status='initialized' ORDER BY id DESC",[$kind,$id]);
  foreach($pending as $p){
   $key=paystack_payment_key($p);
   if($key==='')throw new RuntimeException('An earlier payment needs review. Please contact support with your reference.');
   // A timeout is an unknown outcome: never create a second charge in that case.
   try{$data=paystack_request('GET','transaction/verify/'.rawurlencode($p['reference']),$key);}
   catch(RuntimeException $e){
    $context=one('SELECT * FROM payment_gateway_context WHERE payment_id=?',[$p['id']]);
    if($e->getCode()!==404||!$context)throw $e;
    $retry=paystack_request('POST','transaction/initialize',$key,['email'=>$record['email'],'amount'=>(int)round((float)$p['amount']*100),'currency'=>$p['currency'],'reference'=>$p['reference'],'callback_url'=>rtrim((string)cfg()['app_url'],'/').'/payment/verify']);
    $url=(string)($retry['authorization_url']??'');if(!paystack_safe_checkout($url))throw new RuntimeException('Payment could not be resumed. Contact support.');
    sql('UPDATE payment_gateway_context SET authorization_url=? WHERE payment_id=?',[$url,$p['id']]);return $url;
   }
   if(($data['status']??'')==='success'){
    verify_payment($p['reference']);
    throw new RuntimeException('Your earlier payment was received. Refresh this page to see the updated balance.');
   }
   if(in_array($data['status']??'',['failed','abandoned','reversed'],true)){
    sql("UPDATE payments SET status='failed' WHERE id=? AND status='initialized'",[$p['id']]);continue;
   }
   $context=one('SELECT * FROM payment_gateway_context WHERE payment_id=?',[$p['id']]);
   if($context&&paystack_safe_checkout($context['authorization_url']))return $context['authorization_url'];
   throw new RuntimeException('An earlier payment is awaiting confirmation. Please contact support before paying again.');
  }
  $sum=one("SELECT COALESCE(SUM(amount),0) total FROM payments WHERE entity_type=? AND entity_id=? AND status='success'",[$kind,$id]);
  $received=(float)$sum['total'];$total=(float)($kind==='order'?$record['total']:$record['quote']);$due=round($total-$received,2);
  $amount=$kind==='booking'&&$received<=0&&(float)$record['deposit']>0?min($due,(float)$record['deposit']):$due;
  if($amount<=0)throw new RuntimeException('There is no outstanding balance.');
  $mode=paystack_mode();$secret=paystack_key($mode);$ref=reference('PAY');
  db()->beginTransaction();
  try{
   sql('INSERT INTO payments(reference,entity_type,entity_id,amount) VALUES(?,?,?,?)',[$ref,$kind,$id,$amount]);$pid=(int)db()->lastInsertId();
   sql('INSERT INTO payment_gateway_context(payment_id,mode,secret_cipher) VALUES(?,?,?)',[$pid,$mode,seal_secret($secret)]);db()->commit();
  }catch(Throwable $e){db()->rollBack();throw $e;}
  $base=rtrim((string)(cfg()['app_url']??''),'/');
  try{$data=paystack_request('POST','transaction/initialize',$secret,['email'=>$record['email'],'amount'=>(int)round($amount*100),'currency'=>'NGN','reference'=>$ref,'callback_url'=>$base.'/payment/verify','metadata'=>['entity_type'=>$kind,'entity_id'=>$id,'mode'=>$mode]]);}
  catch(RuntimeException $e){if($e->getCode()>=400&&$e->getCode()<500&&!in_array($e->getCode(),[408,429],true))sql("UPDATE payments SET status='failed' WHERE id=?",[$pid]);throw $e;}
  $url=(string)($data['authorization_url']??'');
  if(!paystack_safe_checkout($url))throw new RuntimeException('The payment gateway returned an invalid checkout address. Contact support.');
  sql('UPDATE payment_gateway_context SET authorization_url=? WHERE payment_id=?',[$url,$pid]);
  return $url;
 }finally{sql('SELECT RELEASE_LOCK(?)',[$lock]);}
}
function paystack_webhook_key(string $raw,string $signature,array $event):string {
 if(!preg_match('/^[a-f0-9]{128}$/iD',$signature))return '';
 $keys=[];$ref=(string)($event['data']['reference']??'');
 if($ref!==''){$payment=one('SELECT * FROM payments WHERE reference=?',[$ref]);if($payment)$keys[]=paystack_payment_key($payment);}
 foreach(['test','live'] as $mode)$keys[]=paystack_key($mode);
 $keys[]=integration_secret('paystack_secret');
 foreach(array_unique($keys) as $key)if($key!==''&&hash_equals(hash_hmac('sha512',$raw,$key),$signature))return $key;
 return '';
}
