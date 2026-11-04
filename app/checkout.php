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
