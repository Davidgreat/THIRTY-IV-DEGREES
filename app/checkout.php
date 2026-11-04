<?php
declare(strict_types=1);
/** Keep recoverable checkout errors separate from gateway/order creation. */
function checkout_input(array $post):array {
 $limits=['name'=>150,'email'=>190,'phone'=>60,'address'=>1000,'fulfillment'=>20,'recipient_name'=>150,'recipient_phone'=>60,'recipient_address'=>1000,'gift_message'=>500,'scheduled_for'=>16,'notes'=>500];$input=[];
 foreach($limits as $key=>$max)$input[$key]=mb_substr(trim(is_scalar($post[$key]??'')?(string)($post[$key]??''):''),0,$max);
 $input['is_gift']=$input['fulfillment']==='delivery'&&($post['is_gift']??'')==='1'?'1':'0';
 $input['gift_anonymous']=$input['is_gift']==='1'&&($post['gift_anonymous']??'')==='1'?'1':'0';
 return $input;
}
function checkout_errors(array $input,?string &$requestedFor=null):array {
 $errors=[];$requestedFor=null;
 if($input['name']==='')$errors['name']='Enter your full name.';
 if(!filter_var($input['email'],FILTER_VALIDATE_EMAIL))$errors['email']='Enter a valid email address.';
 if($input['phone']==='')$errors['phone']='Enter a contact phone number.';
 if(!in_array($input['fulfillment'],['delivery','pickup'],true))$errors['fulfillment']='Choose delivery or pickup.';
 if($input['is_gift']==='1'){
  if($input['recipient_name']==='')$errors['recipient_name']='Enter the gift recipient’s name.';
  if(!preg_match('/^[+0-9() .-]{8,25}$/D',$input['recipient_phone']))$errors['recipient_phone']='Enter a valid recipient phone number.';
  if($input['recipient_address']==='')$errors['recipient_address']='Enter the recipient’s delivery address.';
 }elseif($input['fulfillment']==='delivery'&&$input['address']==='')$errors['address']='Enter your delivery address, or choose pickup.';
 try{$requestedFor=vi_schedule_validate($input['scheduled_for']);}catch(InvalidArgumentException $e){$errors['scheduled_for']=$e->getMessage();}
 return $errors;
}
function checkout_value(string $key,string $fallback=''):string {
 $draft=$_SESSION['checkout_draft']??[];return isset($draft[$key])?(string)$draft[$key]:$fallback;
}
function checkout_error_summary(array $errors):void {
 ?><div class="checkout-error-summary" id="checkout-errors" role="alert" tabindex="-1" <?=!$errors?'hidden':''?>><strong>Please check your order details</strong><ul><?php foreach($errors as $key=>$message):?><li><?=esc($message)?></li><?php endforeach;?></ul></div><?php
}

function checkout_is_json():bool {
 return defined('THIRTYVI_CHECKOUT_ENDPOINT')&&THIRTYVI_CHECKOUT_ENDPOINT===true&&str_contains(strtolower((string)($_SERVER['HTTP_ACCEPT']??'')),'application/json');
}
function checkout_request_id():string {static $id;return $id??=bin2hex(random_bytes(6));}
/** Log only stages/codes, never submitted fields, cookies, keys or gateway URLs. */
function checkout_trace(string $stage,array $extra=[]):void {
 if(!defined('THIRTYVI_CHECKOUT_ENDPOINT'))return;
 $record=['time'=>gmdate('c'),'request_id'=>checkout_request_id(),'stage'=>$stage,'build'=>'6.1.2'];
 foreach(['order_id','http_status','database_code'] as $key)if(isset($extra[$key]))$record[$key]=(int)$extra[$key];
 if(isset($extra['sqlstate'])&&preg_match('/^[A-Z0-9]{5}$/D',(string)$extra['sqlstate']))$record['sqlstate']=$extra['sqlstate'];
 $line=json_encode($record,JSON_UNESCAPED_SLASHES).PHP_EOL;$file=ROOT.'/storage/.checkout-trace.jsonl';
 if(is_file($file)&&filesize($file)>1048576)@rename($file,$file.'.1');
 if(@file_put_contents($file,$line,FILE_APPEND|LOCK_EX)===false)error_log('Checkout '.$record['request_id'].' stage='.$stage);
}
function checkout_json_reply(int $status,array $payload):never {
 if(defined('THIRTYVI_CHECKOUT_BUFFER'))while(ob_get_level()>THIRTYVI_CHECKOUT_BUFFER)ob_end_clean();
 http_response_code($status);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: private, no-store, max-age=0');header('X-Checkout-Build: 6.1.2');
 echo json_encode(['service'=>'thirtyvi-checkout','build'=>'6.1.2','request_id'=>checkout_request_id()]+$payload,JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);exit;
}
function checkout_endpoint_bootstrap():never {
 header('Allow: GET, POST');
 if($_SERVER['REQUEST_METHOD']==='GET'&&(isset($_GET['probe'])||isset($_GET['bootstrap']))){
  $payload=['ok'=>true,'stage'=>'ready','method'=>'POST'];
  if(isset($_GET['bootstrap'])){
   $last=(int)($_SESSION['checkout_last_order']??0);$saved=null;
   if($last&&isset($_SESSION['order_access'][$last])){$o=one('SELECT reference FROM orders WHERE id=?',[$last]);if($o&&hash_equals($o['reference'],$_SESSION['order_access'][$last]))$saved='/order/'.$last;}
   $payload+=['csrf'=>csrf(),'cart_items'=>count($_SESSION['cart']??[]),'saved_order_url'=>$saved,'mode'=>paystack_mode(),'payments_enabled'=>paystack_enabled()];
  }
  checkout_json_reply(200,$payload);
 }
 checkout_json_reply(405,['ok'=>false,'stage'=>'method','message'=>'Use the checkout form to submit an order.']);
}
function checkout_json_navigation(string $path):never {
 $errors=$_SESSION['checkout_errors']??[];$flash=$_SESSION['flash']??null;
 if(str_starts_with($path,'checkout')&&$errors){checkout_trace('validation_failed');checkout_json_reply(422,['ok'=>false,'stage'=>'validation','message'=>'Please check the highlighted order details.','errors'=>$errors]);}
 $destination=url($path);
 if($destination==='/cart'||$destination==='/menu'){
  checkout_trace('order_not_created');unset($_SESSION['flash']);
  checkout_json_reply(409,['ok'=>false,'stage'=>'order','message'=>$flash['message']??'Your bag is empty or the session expired. Return to your bag and try again.','next'=>'/cart']);
 }
 if(preg_match('~^/order/\d+(?:\?new=1)?$~D',$destination)){
  if(($flash['type']??'')==='error'&&paystack_enabled()){
   checkout_trace('gateway_failed');unset($_SESSION['flash']);
   checkout_json_reply(502,['ok'=>false,'stage'=>'gateway','message'=>$flash['message']??'Payment could not be started. Your order is saved.','order_url'=>$destination]);
  }
  checkout_json_reply(200,['ok'=>true,'stage'=>'order_saved','next'=>$destination]);
 }
 checkout_json_reply(409,['ok'=>false,'stage'=>'navigation','message'=>'Checkout could not continue. Refresh your bag and try again.']);
}
