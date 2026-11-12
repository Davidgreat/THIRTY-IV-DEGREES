<?php
declare(strict_types=1);
require __DIR__.'/app/core.php';
require_once ROOT.'/app/checkout.php';
require_once ROOT.'/app/catalogue.php';
require_once ROOT.'/app/owner-security.php';
if(!is_file(ROOT.'/config.php')){header('Location: /install/');exit;}
$path=trim(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH),'/');
if(defined('THIRTYVI_CHECKOUT_ENDPOINT')){
 if(!post())checkout_endpoint_bootstrap();
 $path='checkout';checkout_trace('received');
}

function card(array $p):void{?><article class="card food-tile"><div class="food-photo"><a href="/product/<?=(int)$p['id']?>" aria-label="View <?=esc($p['name'])?>"><img loading="lazy" src="<?=esc($p['image'])?>" alt="<?=esc($p['name'])?>"></a><button class="food-like" type="button" data-food-like="<?=(int)$p['id']?>" aria-label="Save <?=esc($p['name'])?> to favourites" aria-pressed="false" title="Save to favourites">♡</button></div><div class="food-info"><h3><a href="/product/<?=(int)$p['id']?>"><?=esc($p['name'])?></a></h3><?php $rating=one('SELECT COUNT(*) n,ROUND(AVG(stars),1) avg_stars FROM product_reviews WHERE product_id=? AND approved=1',[(int)$p['id']]);if((int)$rating['n']>0):?><div class="food-rating" aria-label="Rated <?=esc($rating['avg_stars'])?> out of five by <?=(int)$rating['n']?> customers"><span aria-hidden="true">★</span> <?=esc($rating['avg_stars'])?> <small>(<?=(int)$rating['n']?>)</small></div><?php endif;?><div class="food-bottom"><strong class="food-price"><?=money($p['price'])?></strong><a class="food-view" href="/product/<?=(int)$p['id']?>" aria-label="View <?=esc($p['name'])?>">View →</a></div></div></article><?php }
function meal_options(int $productId):array{
 $groups=all('SELECT id,name,required,max_select FROM meal_option_groups WHERE product_id=? ORDER BY sort_order,id',[$productId]);
 foreach($groups as &$group)$group['choices']=all('SELECT id,name,price_delta FROM meal_option_choices WHERE group_id=? ORDER BY sort_order,id',[$group['id']]);
 unset($group);return $groups;
}
function meal_selection(int $productId,array $submitted):array{
 $groups=meal_options($productId);$selected=[];$extra=0.0;$labels=[];
 foreach($groups as $group){
  $ids=$submitted[(string)$group['id']]??$submitted[(int)$group['id']]??[];
  if(!is_array($ids))$ids=[$ids];
  $ids=array_values(array_unique(array_map('intval',$ids)));
  if(count($ids)>(int)$group['max_select'])throw new RuntimeException('Too many selections for '.$group['name']);
  if((int)$group['required'] && !count($ids))throw new RuntimeException('Choose an option for '.$group['name']);
  $valid=[];foreach($group['choices'] as $choice)$valid[(int)$choice['id']]=$choice;
  foreach($ids as $id){if(!isset($valid[$id]))throw new RuntimeException('Invalid meal option selected.');$choice=$valid[$id];$extra+=(float)$choice['price_delta'];$labels[]=$group['name'].': '.$choice['name'];$selected[]=$id;}
 }
 if(count($submitted)>count($groups))throw new RuntimeException('Invalid meal options submitted.');
 sort($selected,SORT_NUMERIC);
 return ['ids'=>$selected,'extra'=>round($extra,2),'labels'=>$labels];
}
function meal_cart_price(array $item,array $product):array{
 $submitted=[];foreach(($item['choices']??[]) as $id){$row=one('SELECT group_id FROM meal_option_choices WHERE id=?',[(int)$id]);if(!$row)throw new RuntimeException('A selected extra is no longer available.');$submitted[(string)$row['group_id']][]=(int)$id;}
 $choice=meal_selection((int)$product['id'],$submitted);
 return ['unit'=>round((float)$product['price']+$choice['extra'],2),'labels'=>$choice['labels']];
}

function wa_phone(string $number):string{
 $digits=preg_replace('/\D+/','',$number);
 if(str_starts_with($digits,'0')&&strlen($digits)===11)$digits='234'.substr($digits,1);
 return preg_match('/^[1-9]\d{7,14}$/',$digits)?$digits:'';
}
function wa_template(string $type):string{
 $defaults=[
 'customer_order'=>"Hello 36°! I have paid for order {reference}.\n{details}\nTotal: {total}.\nPlease confirm receipt.",
 'order_accepted'=>"Hello {name}, your order {reference} has been accepted. Thank you for choosing 36°.",
 'order_preparing'=>"Hello {name}, your order {reference} is being prepared.",
 'order_ready'=>"Hello {name}, your order {reference} is ready.",
 'order_dispatched'=>"Hello {name}, your order {reference} is on its way.",
 'order_completed'=>"Hello {name}, your order {reference} is complete. Thank you!",
 'order_cancelled'=>"Hello {name}, your order {reference} has been cancelled. Please contact us if you need help.",
 'booking_inquiry'=>"Hello {name}, we received your {service} inquiry {reference} for {date}. We will contact you shortly.",
 'booking_quoted'=>"Hello {name}, your {service} quotation {reference} is ready. Please view your booking details.",
 'booking_awaiting_deposit'=>"Hello {name}, your {service} booking {reference} is awaiting the required deposit.",
 'booking_confirmed'=>"Hello {name}, your {service} booking {reference} for {date} is confirmed.",
 'booking_cancelled'=>"Hello {name}, your {service} booking {reference} has been cancelled. Please contact us for support.",
 'booking_completed'=>"Hello {name}, thank you for booking 36°! Your event {reference} is complete."
 ];
 return setting('wa_tpl_'.$type,$defaults[$type]??'');
}
function wa_order_details(int $id):string{
 $lines=all('SELECT product_name,quantity,customizations FROM order_items WHERE order_id=?',[$id]);$out=[];
 foreach($lines as $line){$x=$line['product_name'].' x'.$line['quantity'];$opts=json_decode($line['customizations']??'[]',true);if(is_array($opts)&&$opts)$x.=' ('.implode(', ',array_map('strval',$opts)).')';$out[]=$x;}
 $gift=one('SELECT * FROM order_gifts WHERE order_id=?',[$id]);if($gift){$out[]='GIFT FOR: '.$gift['recipient_name'].' | '.$gift['recipient_phone'];if($gift['recipient_address'])$out[]='Recipient address: '.$gift['recipient_address'];if($gift['gift_message'])$out[]='Gift note: '.$gift['gift_message'];if($gift['anonymous_sender'])$out[]='Anonymous sender requested';}return implode("\n",$out);
}
function wa_message(string $type,array $record,string $kind):string{
 $map=['{name}'=>$kind==='order'?($record['customer_name']??'Customer'):($record['name']??'Customer'),'{reference}'=>$record['reference']??'', '{details}'=>$kind==='order'?wa_order_details((int)$record['id']):($record['details']??''),'{total}'=>$kind==='order'?money($record['total']??0):money($record['quote']??0),'{date}'=>$record['event_date']??'', '{service}'=>ucfirst($record['kind']??'private chef')];
 return strtr(wa_template($type),$map);
}
function wa_link(string $phone,string $message):string{
 $phone=wa_phone($phone);return $phone?'https://wa.me/'.$phone.'?text='.rawurlencode($message):'';
}
function head(string $title,string $sub=''):void{?><div class="pagehead"><span class="eyebrow">36°</span><h1><?=esc($title)?></h1><?php if($sub):?><p><?=esc($sub)?></p><?php endif;?></div><?php }
function field(string $label,string $name,string $type='text',bool $required=true,string $value=''):void{echo '<label for="'.esc($name).'">'.esc($label).'</label><input id="'.esc($name).'" name="'.esc($name).'" type="'.esc($type).'" value="'.esc($value).'" '.($required?'required':'').'>';}
/** Authentication-only presentation; passwords stay masked until explicitly revealed. */
function auth_password_field(string $id='password',bool $new=false):void{
 ?><label for="<?=esc($id)?>"><?=$new?'Choose a password':'Password'?></label><input id="<?=esc($id)?>" name="password" type="password" autocomplete="<?=$new?'new-password':'current-password'?>" autocapitalize="off" spellcheck="false" required <?=$new?'minlength="10" maxlength="4096" aria-describedby="'.esc($id).'-hint"':''?>><?php if($new):?><small class="auth-password-hint" id="<?=esc($id)?>-hint">Use at least 10 characters.</small><?php endif;?><label class="auth-password-toggle" hidden><input type="checkbox" data-password-toggle aria-controls="<?=esc($id)?>"><span>Show password</span></label><?php
}

function paystack_start(string $kind,int $id,string $email,float $amount):never{
 try{$destination=paystack_checkout($kind,$id);}catch(Throwable $e){checkout_trace('gateway_failed',['order_id'=>$id,'http_status'=>(int)$e->getCode()]);try{audit('payment.start.failed',$kind.'='.$id.'; http='.(int)$e->getCode());}catch(Throwable $ignored){}flash($e instanceof RuntimeException?$e->getMessage():'Payment could not be started. Please contact support.','error');go(($kind==='order'?'order/':'booking/').$id);}
 if(checkout_is_json()){checkout_trace('gateway_ready',['order_id'=>$id]);checkout_json_reply(200,['ok'=>true,'stage'=>'gateway_ready','next'=>$destination,'order_url'=>($kind==='order'?'/order/':'/booking/').$id]);}
 header('Location: '.$destination);exit;
}
function booking_slot_open(string $kind,string $date,string $time,int $exclude=0):bool{
 if($kind==='chef' && setting('chef_available','1')!=='1')return false;
 if($date<date('Y-m-d')||!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date))return false;
 $dt=DateTimeImmutable::createFromFormat('!Y-m-d',$date);if(!$dt||$dt->format('Y-m-d')!==$date)return false;
 if($kind==='chef'&&(!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D',$time)||$time<setting('booking_start','09:00')||$time>=setting('booking_end','22:00')))return false;
 if(one("SELECT id FROM booking_blackouts WHERE day=? AND (kind=? OR kind='all') LIMIT 1",[$date,$kind]))return false;
 if($kind!=='chef')return true;
 $capacity=max(1,(int)setting('booking_capacity','1'));
 $count=one("SELECT COUNT(*) n FROM bookings WHERE kind='chef' AND event_date=? AND event_time=? AND id<>? AND status IN ('confirmed','awaiting_deposit')",[$date,$time.':00',$exclude]);
 return (int)$count['n']<$capacity;
}
if($path==='payment/webhook'&&post()){
 $raw=file_get_contents('php://input',false,null,0,1048577);if(strlen($raw)>1048576)abort(413,'Payload too large');
 $event=json_decode($raw,true);if(!is_array($event))abort(400,'Invalid JSON');
 $sig=$_SERVER['HTTP_X_PAYSTACK_SIGNATURE']??'';
 if(paystack_webhook_key($raw,$sig,$event)==='')abort(403,'Invalid signature');
 $evt=(string)($event['event']??'');$fingerprint=hash('sha256',$raw);
 sql('INSERT INTO payment_webhook_events(event_hash,event_type,payload_excerpt) VALUES(?,?,?) ON DUPLICATE KEY UPDATE event_hash=event_hash',[$fingerprint,substr($evt,0,80),'Signature verified']);
 if($evt==='charge.success'&&!empty($event['data']['reference'])){
  if(!verify_payment((string)$event['data']['reference'])){http_response_code(503);exit('Verification pending');}
 }
 if(str_starts_with($evt,'refund.')&&isset($event['data']['id'])){
  $r=one('SELECT id FROM refund_requests WHERE gateway_refund_id=?',[(string)$event['data']['id']]);
  if($r){try{refund_reconcile((int)$r['id']);}catch(Throwable $e){http_response_code(503);exit('Reconciliation pending');}}
 }
 http_response_code(200);exit('OK');
}
if($path==='payment/verify'){
 $ref=substr((string)($_GET['reference']??''),0,80);
 try{$verified=verify_payment($ref);}catch(Throwable $e){$verified=false;}
 flash($verified?'Payment verified successfully.':'Payment is awaiting verification. Please check again or contact support.',$verified?'ok':'error');
 $p=one('SELECT * FROM payments WHERE reference=?',[$ref]);go($p?(($p['entity_type']==='order'?'order/':'booking/').$p['entity_id']):'account');
}
if($path==='favourites/products'){
 header('Content-Type: application/json; charset=utf-8');header('Cache-Control: private, no-store');
 $raw=isset($_GET['ids'])?(string)$_GET['ids']:'';
 if(strlen($raw)>1500){http_response_code(400);echo json_encode(['products'=>[]]);exit;}
 $ids=array_values(array_unique(array_filter(array_map('intval',explode(',',$raw)),static fn($id)=>$id>0)));
 $ids=array_slice($ids,0,30);
 if(!$ids){echo json_encode(['products'=>[]]);exit;}
 $holders=implode(',',array_fill(0,count($ids),'?'));
 $products=all('SELECT id,name,image,price FROM products WHERE available=1 AND id IN ('.$holders.')',$ids);
 $map=[];foreach($products as $p)$map[(int)$p['id']]=['id'=>(int)$p['id'],'name'=>$p['name'],'image'=>$p['image'],'price'=>money($p['price']),'url'=>'/product/'.(int)$p['id']];
 echo json_encode(['products'=>array_values(array_filter(array_map(static fn($id)=>$map[$id]??null,$ids)))],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);exit;
}
if($path==='chef/status'){header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');echo json_encode(['available'=>setting('chef_available','1')==='1']);exit;}
if($path==='booking/availability'){
 header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
 $kind=in_array($_GET['kind']??'',['chef','catering'],true)?$_GET['kind']:'chef';$date=substr((string)($_GET['date']??''),0,10);$time=substr((string)($_GET['time']??''),0,5);
 echo json_encode(['available'=>booking_slot_open($kind,$date,$time),'note'=>'Availability is indicative until your booking is confirmed.']);exit;
}
if($path==='logout'){unset($_SESSION['uid'],$_SESSION['checkout_draft'],$_SESSION['checkout_errors'],$_SESSION['checkout_last_order']);session_regenerate_id(true);go('');}
if(post()){
 check_csrf();if(defined('THIRTYVI_CHECKOUT_ENDPOINT'))checkout_trace('csrf_verified');rate_limit('post',60,300);
 if($path==='forgot-password'){
  rate_limit('account_recovery',5,900);
  try{account_reset_request(strtolower(str('email',190)));}catch(Throwable $e){error_log('Account recovery queue failed');}
  flash('If that email belongs to an account, a reset link will be emailed. Check your inbox and spam folder.');go('forgot-password');
 }
 if($path==='reset-password'){
  rate_limit('password_reset',8,900);
  if(account_reset_password(str('reset_token',64),(string)($_POST['password']??''))){flash('Password updated. Sign in with your new password.');go('login');}
  flash('This link is expired or invalid, or the password is shorter than 10 characters. Request another link.','error');go('forgot-password');
 }
 if($path==='register'){$name=str('name',150);$email=strtolower(str('email',190));$password=(string)($_POST['password']??'');if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($password)<10||!$name){flash('Provide valid details and a password of at least 10 characters.','error');go('register');}try{sql('INSERT INTO users(name,email,password) VALUES(?,?,?)',[$name,$email,password_hash($password,PASSWORD_DEFAULT)]);$_SESSION['uid']=(int)db()->lastInsertId();session_regenerate_id(true);go('account');}catch(PDOException $e){flash('This email may already be registered.','error');go('register');}}
 if($path==='track'){
  rate_limit('order_track',8,600);
  $ref=strtoupper(str('reference',40));
  if(!preg_match('/^ORD-[A-F0-9]{10}$/',$ref)){flash('Enter a valid order reference.','error');go('track');}
  $found=one('SELECT reference,status,fulfillment FROM orders WHERE reference=?',[$ref]);
  if(!$found){flash('Order reference not found. Please check and try again.','error');go('track');}
  $_SESSION['track_public']=['reference'=>$found['reference'],'until'=>time()+900];
  session_regenerate_id(true);
  go('track/result');
 }
 if($path==='login'){rate_limit('login',8,600);$u=one('SELECT * FROM users WHERE email=?',[strtolower(str('email',190))]);if(!$u||!password_verify((string)($_POST['password']??''),$u['password'])){flash('Invalid email or password.','error');go('login');}session_regenerate_id(true);$_SESSION['uid']=(int)$u['id'];if($u['role']==='admin')$_SESSION['owner_auth_stamp']=owner_auth_stamp($u);else unset($_SESSION['owner_auth_stamp']);go($u['role']==='customer'?'account':'admin');}
 if($path==='cart/add'){
  $id=(int)($_POST['product_id']??0);$p=one('SELECT * FROM products WHERE id=? AND available=1',[$id]);if(!$p)abort(404,'Meal not available');
  $qty=max(1,min(25,(int)($_POST['qty']??1)));$note=str('notes',400);
  try{$choice=meal_selection($id,is_array($_POST['options']??null)?$_POST['options']:[]);}
  catch(RuntimeException $e){flash($e->getMessage(),'error');go('product/'.$id);}
  $key=hash('sha256',json_encode([$id,$note,$choice['ids']]));
  if(!isset($_SESSION['cart'][$key]))$_SESSION['cart'][$key]=['id'=>$id,'qty'=>0,'notes'=>$note,'choices'=>$choice['ids']];
  $_SESSION['cart'][$key]['qty']=min(25,$_SESSION['cart'][$key]['qty']+$qty);
  flash('Added to your bag.');if(($_POST['return_to']??'')==='shop')go('shop'.(isset($_POST['category'])&&ctype_digit((string)$_POST['category'])?'?category='.(int)$_POST['category']:''));go('cart');
 }
 if($path==='account/reorder'){
  $u=require_login();$orderId=(int)($_POST['order_id']??0);
  $owned=one('SELECT id FROM orders WHERE id=? AND user_id=?',[$orderId,$u['id']]);
  if(!$owned)abort(403,'Order not available');
  $items=all('SELECT product_id,quantity,customizations FROM order_items WHERE order_id=?',[$orderId]);
  $added=0;$skipped=0;
  foreach($items as $item){
   $id=(int)$item['product_id'];$product=one('SELECT id FROM products WHERE id=? AND available=1',[$id]);
   if(!$product || meal_options($id) || !empty(json_decode($item['customizations']??'[]',true))){$skipped++;continue;}
   $key=hash('sha256',json_encode([$id,'',[]]));
   if(!isset($_SESSION['cart'][$key]))$_SESSION['cart'][$key]=['id'=>$id,'qty'=>0,'notes'=>'','choices'=>[]];
   $_SESSION['cart'][$key]['qty']=min(25,$_SESSION['cart'][$key]['qty']+max(1,(int)$item['quantity']));$added++;
  }
  flash($added?'Added '.$added.' item(s) to your bag at current prices.'.($skipped?' '.$skipped.' customizable/unavailable item(s) skipped.':''):'No eligible items to reorder. Please browse the menu.',$added?'ok':'error');
  go($added?'cart':'account');
 }
 if($path==='cart/update'){$key=str('key',80);$qty=max(0,min(25,(int)($_POST['qty']??1)));if(isset($_SESSION['cart'][$key])){if($qty===0)unset($_SESSION['cart'][$key]);else $_SESSION['cart'][$key]['qty']=$qty;}flash('Your bag has been updated.');go('cart');}
 if($path==='cart/remove'){$key=str('key',80);unset($_SESSION['cart'][$key]);go('cart');}
 if($path==='checkout'){
 $cart=$_SESSION['cart']??[];
 if(!$cart){$last=(int)($_SESSION['checkout_last_order']??0);if($last&&isset($_SESSION['order_access'][$last]))go('order/'.$last);go('cart');}
 if(isset($_POST['checkout_mode'])&&(!is_string($_POST['checkout_mode'])||$_POST['checkout_mode']!==paystack_mode())){$_SESSION['checkout_errors']=['payment_mode'=>'The payment environment changed while this page was open. Refresh checkout before continuing.'];go('checkout#checkout-errors');}
 $input=checkout_input($_POST);$_SESSION['checkout_draft']=$input;$requestedFor=null;$errors=checkout_errors($input,$requestedFor);
 if($errors){$_SESSION['checkout_errors']=$errors;go('checkout#checkout-errors');}
 unset($_SESSION['checkout_errors']);checkout_trace('validated');
 $name=$input['name'];$email=$input['email'];$phone=$input['phone'];$address=$input['address'];$fulfillment=$input['fulfillment'];$isGift=$input['is_gift']==='1';
 $recipientName=$input['recipient_name'];$recipientPhone=$input['recipient_phone'];$recipientAddress=$input['recipient_address'];$giftMessage=$input['gift_message'];$giftAnonymous=$input['gift_anonymous']==='1';
 db()->beginTransaction();try{
  $subtotal=0;$items=[];$requestedQty=[];
  foreach($cart as $c){
   $p=one('SELECT * FROM products WHERE id=? AND available=1 FOR UPDATE',[$c['id']]);if(!$p)throw new RuntimeException('A meal is no longer available.');$requestedQty[$p['id']]=($requestedQty[$p['id']]??0)+max(1,min(25,(int)$c['qty']));$inv=one('SELECT * FROM product_inventory WHERE product_id=? FOR UPDATE',[$p['id']]);if($inv&&(int)$inv['track_stock']===1){$held=one('SELECT COALESCE(SUM(quantity),0) AS n FROM stock_reservations WHERE product_id=? AND expires_at>NOW()',[$p['id']]);if((int)$inv['stock_qty']-(int)$held['n']<$requestedQty[$p['id']])throw new RuntimeException('Not enough stock for '.$p['name'].'.');}
   $qty=max(1,min(25,(int)$c['qty']));$price=meal_cart_price($c,$p);
   if($price['unit']<0)throw new RuntimeException('Invalid meal price.');
   $subtotal+=$price['unit']*$qty;$items[]=[$p,$qty,$c['notes']??'',$price];
  }
  $fee=$fulfillment==='delivery'?(float)setting('delivery_fee','0'):0;$ref=reference('ORD');
  sql('INSERT INTO orders(reference,user_id,customer_name,email,phone,address,fulfillment,notes,subtotal,delivery_fee,total) VALUES(?,?,?,?,?,?,?,?,?,?,?)',[$ref,user()['id']??null,$name,$email,$phone,$isGift?$recipientAddress:$address,$fulfillment,str('notes',500),$subtotal,$fee,$subtotal+$fee]);
  $id=(int)db()->lastInsertId();$_SESSION['order_access'][$id]=$ref;
  if($requestedFor)sql('INSERT INTO order_schedules(order_id,requested_for) VALUES(?,?)',[$id,$requestedFor]);
  sql('INSERT INTO order_status_events(order_id,status,actor_id) VALUES(?,?,?)',[$id,'pending',user()['id']??null]);if($isGift)sql('INSERT INTO order_gifts(order_id,recipient_name,recipient_phone,recipient_address,gift_message,anonymous_sender) VALUES(?,?,?,?,?,?)',[$id,$recipientName,$recipientPhone,$recipientAddress,$giftMessage,(int)$giftAnonymous]);
  foreach($items as [$p,$qty,$notes,$price])sql('INSERT INTO order_items(order_id,product_id,product_name,unit_price,quantity,notes,customizations) VALUES(?,?,?,?,?,?,?)',[$id,$p['id'],$p['name'],$price['unit'],$qty,$notes,json_encode($price['labels'],JSON_UNESCAPED_UNICODE)]);
  foreach($items as [$p,$qty,$notes,$price]){
   $inv=one('SELECT track_stock FROM product_inventory WHERE product_id=?',[$p['id']]);
   if($inv&&(int)$inv['track_stock']===1)sql('INSERT INTO stock_reservations(product_id,order_id,quantity,expires_at) VALUES(?,?,?,DATE_ADD(NOW(),INTERVAL 20 MINUTE))',[$p['id'],$id,$qty]);
  }
  db()->commit();
 }catch(Throwable $e){
  if(db()->inTransaction())db()->rollBack();
  if($e instanceof PDOException){checkout_trace('order_database_failed',['database_code'=>(int)($e->errorInfo[1]??0),'sqlstate'=>(string)$e->getCode()]);$message='The order could not be saved because of a database error. Please contact our team with the checkout reference.';}
  else{checkout_trace('order_validation_failed');$message=$e instanceof RuntimeException?$e->getMessage():'The order could not be saved. Please contact our team.';}
  flash($message,'error');go('cart');
 }
 checkout_trace('order_created',['order_id'=>$id]);
 unset($_SESSION['cart'],$_SESSION['checkout_draft'],$_SESSION['checkout_errors']);$_SESSION['checkout_last_order']=$id;
 run_hook('order.created',['order_id'=>$id]);
 if(paystack_enabled())paystack_start('order',$id,$email,$subtotal+$fee);
 flash('Your order is saved. Online payments are currently paused; contact our team to arrange payment.','error');go('order/'.$id.'?new=1');
 }
 if($path==='booking/create'){$kind=str('kind',20);$name=str('name',150);$email=str('email',190);$phone=str('phone',60);$date=str('event_date',20);$time=str('event_time',10);$guests=(int)($_POST['guests']??0);$location=str('location',500);if(!in_array($kind,['chef','catering'],true)||!filter_var($email,FILTER_VALIDATE_EMAIL)||!$name||!$phone||!$location||$guests<1||$guests>10000||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)||$date<date('Y-m-d')){flash('Please enter valid event information.','error');go($kind==='catering'?'catering':'private-chef');}
 $lockName='thirtyvi:booking:'.$kind.':'.$date.':'.$time;
 $got=one('SELECT GET_LOCK(?,5) AS locked',[$lockName]);if((int)($got['locked']??0)!==1){flash('Booking is busy. Please try again.','error');go($kind==='chef'?'private-chef':'catering');}
 try{
  if(!booking_slot_open($kind,$date,$time)){flash('This service or time is unavailable. Please choose another.','error');go($kind==='chef'?'private-chef':'catering');}
  sql('INSERT INTO bookings(reference,user_id,name,email,phone,kind,event_type,event_date,event_time,guests,location,budget,details) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)',[reference('EVT'),user()['id']??null,$name,$email,$phone,$kind,str('event_type',100),$date,$time?:null,$guests,$location,str('budget',80),str('details',4000)]);
  $id=(int)db()->lastInsertId();$bookingRef=one('SELECT reference FROM bookings WHERE id=?',[$id]);$_SESSION['booking_access'][$id]=$bookingRef['reference'];
 }finally{sql('SELECT RELEASE_LOCK(?)',[$lockName]);}
 run_hook('booking.created',['booking_id'=>$id]);flash('Your request has been received. We will review it and provide confirmation or a quotation.');go('booking/'.$id.'?new=1');}
 if($path==='product-review/create'){$u=require_login();$id=max(1,(int)($_POST['product_id']??0));$stars=(int)($_POST['stars']??0);$body=str('body',1200);if(!one('SELECT id FROM products WHERE id=?',[$id]))abort(404,'Meal not found');if($stars<1||$stars>5||mb_strlen($body)<10)abort(400,'Choose a rating and write at least ten characters.');$exists=one('SELECT id FROM product_reviews WHERE product_id=? AND user_id=?',[$id,$u['id']]);if($exists){flash('You have already reviewed this meal.','error');go('product/'.$id);}sql('INSERT INTO product_reviews(product_id,user_id,stars,body) VALUES(?,?,?,?)',[$id,$u['id'],$stars,$body]);flash('Thank you. Your review will appear once approved.');go('product/'.$id);}
 if($path==='review'){ $u=require_login();$stars=(int)($_POST['stars']??0);$body=str('body',2000);if($stars<1||$stars>5||strlen($body)<10)abort(400,'Provide a rating and at least 10 characters.');sql('INSERT INTO reviews(user_id,stars,body) VALUES(?,?,?)',[$u['id'],$stars,$body]);flash('Thank you. Your review is awaiting approval.');go('account');}
 if(str_starts_with($path,'admin/')){admin();$action=substr($path,6);
  if(!staff_can_action($action))abort(403,'Permission denied for this action');
  if(in_array($action,['security/email','security/password'],true))owner_security_save(substr($action,9));
  if($action==='category/save')catalogue_category_save();

  if($action==='package/restore'){
   if(!post())abort(405,'POST required');$owner=admin();if($owner['role']!=='admin')abort(403,'Owner administrator required');
   require_once ROOT.'/app/upgrades.php';
   try{$message=site_restore_to(str('restore_id',100));flash($message);}catch(Throwable $e){flash($e->getMessage(),'error');}
   go('admin/updates');
  }
  if(in_array($action,['package/install','plugin/toggle'],true)){
   require_once ROOT.'/app/upgrades.php';
   try{if($action==='package/install'){$type=str('type',12);$message=install_package($type);$return=$type==='plugin'?'admin/plugins':'admin/updates';}
    else{$slug=str('slug',64);toggle_plugin($slug,($_POST['enabled']??'')==='1');$message='Plugin status updated.';$return='admin/plugins';}
    flash($message);
   }catch(Throwable $e){flash($e->getMessage(),'error');$return=$action==='plugin/toggle'||($_POST['type']??'')==='plugin'?'admin/plugins':'admin/updates';}
   go($return);
  }

  if($action==='inventory/save'){
   $id=(int)($_POST['product_id']??0);$qty=(int)($_POST['stock_qty']??0);$low=(int)($_POST['low_stock_threshold']??5);
   if(!one('SELECT id FROM products WHERE id=?',[$id])||$qty<0||$qty>1000000||$low<0||$low>100000)abort(400,'Invalid stock quantity');
   sql('INSERT INTO product_inventory(product_id,stock_qty,low_stock_threshold,track_stock) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE stock_qty=VALUES(stock_qty),low_stock_threshold=VALUES(low_stock_threshold),track_stock=VALUES(track_stock)',[$id,$qty,$low,isset($_POST['track_stock'])?1:0]);audit('inventory.save',(string)$id);flash('Inventory settings saved.');go('admin/inventory');
  }
  if($action==='booking/blackout'){
   $day=str('day',10);$kind=str('kind',12);$mode=str('mode',12);
   if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$day)||!in_array($kind,['chef','catering','all'],true))abort(400,'Invalid date or service');
   if($mode==='remove')sql('DELETE FROM booking_blackouts WHERE day=? AND kind=?',[$day,$kind]);
   else sql('INSERT IGNORE INTO booking_blackouts(day,kind) VALUES(?,?)',[$day,$kind]);
   audit('booking.blackout',$day.' '.$kind);flash('Booking calendar updated.');go('admin/calendar');
  }
  if($action==='booking/note'){
   $id=(int)($_POST['id']??0);$note=str('note',2000);
   if(!one('SELECT id FROM bookings WHERE id=?',[$id]))abort(404,'Booking not found');
   sql('UPDATE bookings SET internal_note=? WHERE id=?',[$note,$id]);audit('booking.note',(string)$id);flash('Internal note saved.');go('admin/bookings');
  }
  if($action==='meal-option/group-save'){
   $productId=(int)($_POST['product_id']??0);$product=one('SELECT id FROM products WHERE id=?',[$productId]);if(!$product)abort(404,'Meal not found');
   $name=str('name',90);$max=max(1,min(10,(int)($_POST['max_select']??1)));$required=isset($_POST['required'])?1:0;
   if(!$name)abort(400,'Group name is required');
   sql('INSERT INTO meal_option_groups(product_id,name,required,max_select) VALUES(?,?,?,?)',[$productId,$name,$required,$max]);
   flash('Option group added.');go('admin/meal-options?product_id='.$productId);
  }
  if($action==='meal-option/choice-save'){
   $groupId=(int)($_POST['group_id']??0);$group=one('SELECT * FROM meal_option_groups WHERE id=?',[$groupId]);if(!$group)abort(404,'Option group not found');
   $name=str('name',90);$delta=round((float)($_POST['price_delta']??0),2);
   if(!$name||!is_finite($delta)||$delta<0||$delta>10000000)abort(400,'Invalid option price');
   sql('INSERT INTO meal_option_choices(group_id,name,price_delta) VALUES(?,?,?)',[$groupId,$name,$delta]);
   flash('Meal option added.');go('admin/meal-options?product_id='.$group['product_id']);
  }
  if($action==='meal-option/delete'){
   $kind=str('kind',10);$id=(int)($_POST['id']??0);$productId=(int)($_POST['product_id']??0);
   if($kind==='group')sql('DELETE FROM meal_option_groups WHERE id=? AND product_id=?',[$id,$productId]);
   elseif($kind==='choice')sql('DELETE c FROM meal_option_choices c JOIN meal_option_groups g ON c.group_id=g.id WHERE c.id=? AND g.product_id=?',[$id,$productId]);
   else abort(400,'Invalid action');
   flash('Option removed.');go('admin/meal-options?product_id='.$productId);
  }
  if($action==='category/create'){
   $name=str('name',120);$slug=strtolower(trim(preg_replace('/[^a-z0-9]+/','-',strtolower($name)),'-'));
   if(!$name||!$slug)abort(400,'Category name required');
   try{sql('INSERT INTO categories(name,slug) VALUES(?,?)',[$name,$slug]);}catch(PDOException $e){flash('Category already exists or could not be created.','error');go('admin/products');}
   audit('category.create',$slug);flash('Category created.');go('admin/products');
  }
  if($action==='product/save')catalogue_product_save();
  if($action==='refund/request'){
   if(admin()['role']!=='admin')abort(403,'Owner approval required');
   $pid=(int)($_POST['payment_id']??0);$reason=str('reason',500);
   $payment=one("SELECT * FROM payments WHERE id=? AND status='success'",[$pid]);
   if(!$payment)abort(400,'Only successful payments can be reviewed for refund');
   $existing=one("SELECT id FROM refund_requests WHERE payment_id=? AND status IN ('requested','submitting','processing','completed')",[$pid]);
   if($existing){flash('A refund request already exists for this payment.','error');go('admin/refunds');}
   sql("INSERT INTO refund_requests(payment_id,reason,requested_by,status,amount) VALUES(?,?,?,'requested',?)",[$pid,$reason,admin()['id'],$payment['amount']]);
   audit('refund.request',(string)$pid);flash('Refund review created. No money has been returned yet.');go('admin/refunds');
  }
  if($action==='refund/status'){
   $id=(int)($_POST['id']??0);$status=str('status',16);
   if(!in_array($status,['requested','rejected'],true))abort(400,'Gateway controls processing and completed states');
   sql("UPDATE refund_requests SET status=?,updated_at=NOW() WHERE id=? AND status IN ('requested','rejected') AND gateway_refund_id IS NULL",[$status,$id]);
   audit('refund.review',$id.' '.$status);flash('Refund review updated.');go('admin/refunds');
  }
  if($action==='refund/submit'){
   $id=(int)($_POST['id']??0);
   $claim=sql("UPDATE refund_requests SET status='submitting',updated_at=NOW() WHERE id=? AND status='requested' AND gateway_refund_id IS NULL",[$id]);
   if(!$claim->rowCount())abort(409,'This refund is already being handled');
   $r=one('SELECT r.*,p.reference,p.status payment_status,p.amount paid_amount FROM refund_requests r JOIN payments p ON p.id=r.payment_id WHERE r.id=?',[$id]);
   if(!$r||$r['payment_status']!=='success'||(float)$r['amount']<=0||(float)$r['amount']>(float)$r['paid_amount'])abort(400,'Refund payment is not eligible; review manually');
   try{$data=paystack_refund_api('POST','refund',['transaction'=>$r['reference'],'amount'=>(int)round((float)$r['amount']*100)],one('SELECT * FROM payments WHERE id=?',[$r['payment_id']]));
     if(empty($data['id']))throw new RuntimeException('Paystack returned no refund identifier');
     sql("UPDATE refund_requests SET status='processing',gateway_refund_id=?,gateway_status=?,updated_at=NOW() WHERE id=?",[(string)$data['id'],(string)($data['status']??'pending'),$id]);
     audit('refund.submitted',(string)$id);flash('Refund submitted to Paystack. Await gateway confirmation.');
   }catch(Throwable $e){
     // Deliberately DO NOT reset to requested: a timed-out API call may have succeeded.
     audit('refund.submit.uncertain',(string)$id);flash('Gateway submission could not be confirmed. Do not resubmit until reconciled in Paystack dashboard.','error');
   }go('admin/refunds');
  }
  if($action==='refund/reconcile'){
   $id=(int)($_POST['id']??0);
   try{refund_reconcile($id);audit('refund.reconciled',(string)$id);flash('Latest gateway status retrieved.');}
   catch(Throwable $e){flash('Could not retrieve refund status. Please verify in Paystack.','error');}
   go('admin/refunds');
  }
  if($action==='staff/role'){
   if(admin()['role']!=='admin')abort(403,'Owner approval required');
   $id=(int)($_POST['id']??0);$role=str('role',12);
   if(!in_array($role,['customer','manager'],true))abort(400,'Invalid staff role');
   $target=one('SELECT id,role FROM users WHERE id=?',[$id]);
   if(!$target||$target['role']==='admin')abort(403,'Owner accounts cannot be changed here');
   sql('UPDATE users SET role=? WHERE id=?',[$role,$id]);
   audit('staff.role',$id.' '.$role);flash('Staff role updated.');go('admin/staff');
  }
  if($action==='staff/permissions'){
   $id=(int)($_POST['id']??0);$target=one('SELECT id,role FROM users WHERE id=?',[$id]);if(!$target||$target['role']!=='manager')abort(400,'Target must be a manager');
   $selected=$_POST['permissions']??[];if(!is_array($selected))abort(400,'Invalid permissions');
   foreach(['orders_manage','bookings_manage','messages_send','inventory_manage'] as $permission){
    sql('INSERT INTO staff_permissions(user_id,permission,allowed) VALUES(?,?,?) ON DUPLICATE KEY UPDATE allowed=VALUES(allowed)',[$id,$permission,in_array($permission,$selected,true)?1:0]);
   }audit('staff.permissions',(string)$id);flash('Manager permissions saved.');go('admin/staff');
  }
  if($action==='email/templates'){
   $subjects=$_POST['subject']??[];$bodies=$_POST['body']??[];
   if(!is_array($subjects)||!is_array($bodies))abort(400,'Invalid template fields');
   foreach(email_template_defaults() as $key=>$default){
    $subject=trim((string)($subjects[$key]??''));$body=trim((string)($bodies[$key]??''));
    if($subject===''||$body===''||strlen($subject)>190||strlen($body)>10000||strpbrk($subject,"\r\n")!==false)abort(400,'Invalid email template');
    foreach(['mail_subject_'.$key=>$subject,'mail_body_'.$key=>$body] as $k=>$v)sql('INSERT INTO settings (`key`,value) VALUES (?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',[$k,$v]);
   }audit('email.templates');flash('Email templates saved.');go('admin/email-templates');
  }
  if($action==='email/settings'){
   if(admin()['role']!=='admin')abort(403,'Owner access required');
   $host=str('smtp_host',190);$port=(int)($_POST['smtp_port']??587);$security=str('smtp_security',12);
   $from=str('smtp_from',190);$username=str('smtp_user',190);$sender=str('smtp_from_name',100);
   if(!preg_match('/^[a-z0-9.-]+$/i',$host)||$port<1||$port>65535||!in_array($security,['ssl','starttls'],true)||!filter_var($from,FILTER_VALIDATE_EMAIL))abort(400,'Invalid SMTP configuration');
   foreach(['smtp_host'=>$host,'smtp_port'=>(string)$port,'smtp_security'=>$security,'smtp_from'=>$from,'smtp_user'=>$username,'smtp_from_name'=>$sender] as $k=>$v)save_integration($k,$v);
   save_integration('smtp_password',(string)($_POST['smtp_password']??''),true);
   audit('email.smtp.settings');flash('SMTP configuration saved.');go('admin/email');
  }
  if($action==='email/test'){
   if(admin()['role']!=='admin')abort(403,'Owner access required');
   $to=trim((string)($_POST['test_recipient']??''));
   if(strlen($to)>190||!filter_var($to,FILTER_VALIDATE_EMAIL))abort(400,'Enter a valid test recipient email.');
   $last=(int)($_SESSION['smtp_test_last']??0);
   if(time()-$last<60){flash('Please wait one minute before sending another SMTP test.','error');go('admin/email');}
   $_SESSION['smtp_test_last']=time();
   try{
    smtp_send($to,'36° - SMTP connection test',"This is a test message from 36°.\n\nYour saved SMTP configuration successfully submitted this message to the mail server.\n\nTime: ".date('Y-m-d H:i:s T'));
    audit('email.smtp.test.success');
    flash('SMTP server accepted the test email. Check the recipient inbox and spam folder.');
   }catch(Throwable $e){
    audit('email.smtp.test.failed');
    flash('Test failed. Verify SMTP host, port, TLS mode, credentials and hosting outbound SMTP access. Server error details are not displayed for security.','error');
   }
   go('admin/email');
  }
  if($action==='email/retry'){
   if(admin()['role']!=='admin')abort(403,'Owner access required');
   $id=(int)($_POST['id']??0);
   sql("UPDATE email_queue SET status='retry',available_at=NOW(),last_error=NULL WHERE id=? AND status='failed'",[$id]);
   audit('email.retry',(string)$id);flash('Email scheduled for retry.');go('admin/email');
  }
  if($action==='whatsapp/settings'){
   $phone=trim((string)($_POST['chef_whatsapp']??''));
   if($phone!==''&&!wa_phone($phone)){flash('Enter a valid international WhatsApp number.','error');go('admin/whatsapp');}
   sql("INSERT INTO settings (`key`,value) VALUES ('chef_whatsapp',?) ON DUPLICATE KEY UPDATE value=VALUES(value)",[wa_phone($phone)]);
   $templates=$_POST['templates']??[];if(!is_array($templates))abort(400,'Invalid templates');
   foreach(['customer_order','order_accepted','order_preparing','order_ready','order_dispatched','order_completed','order_cancelled','booking_inquiry','booking_quoted','booking_awaiting_deposit','booking_confirmed','booking_cancelled','booking_completed'] as $key){
    if(!isset($templates[$key])||!is_string($templates[$key]))continue;
    $value=trim($templates[$key]);if($value===''||strlen($value)>1200)continue;
    sql("INSERT INTO settings (`key`,value) VALUES (?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)",['wa_tpl_'.$key,$value]);
   }
   audit('whatsapp.settings.updated');flash('WhatsApp number and message templates saved.');go('admin/whatsapp');
  }
  if($action==='whatsapp/draft'){
   $kind=str('kind',12);$id=(int)($_POST['id']??0);$template=str('template',45);
   if(!in_array($kind,['order','booking'],true))abort(400,'Invalid record type');
   $record=one('SELECT * FROM '.($kind==='order'?'orders':'bookings').' WHERE id=?',[$id]);if(!$record)abort(404,'Record not found');
   $allowed=$kind==='order'?['order_accepted','order_preparing','order_ready','order_dispatched','order_completed','order_cancelled']:['booking_inquiry','booking_quoted','booking_awaiting_deposit','booking_confirmed','booking_cancelled','booking_completed'];
   if(!in_array($template,$allowed,true))abort(400,'Invalid message type');
   $url=wa_link($record['phone']??'',wa_message($template,$record,$kind));if(!$url){flash('Customer phone number is not valid for WhatsApp.','error');go('admin/'.($kind==='order'?'orders':'bookings'));}
   audit('whatsapp.draft.opened',$kind.' #'.$id.' · '.$template.' (not proof of sending)');
   header('Location: '.$url, true, 303);exit;
  }
  if($action==='order/status'){$id=(int)($_POST['id']??0);$status=str('status',20);if(!in_array($status,['pending','accepted','preparing','ready','dispatched','completed','cancelled'],true))abort(400,'Invalid status');$previous=one('SELECT status,fulfillment FROM orders WHERE id=?',[$id]);if(!$previous)abort(404,'Order not found');if($previous['fulfillment']==='pickup'&&$status==='dispatched')abort(400,'Pickup orders cannot be marked as dispatched.');if(in_array($previous['status'],['cancelled','completed'],true)&&$previous['status']!==$status)abort(400,'Completed or cancelled orders cannot be changed from this screen.');
  sql('UPDATE orders SET status=? WHERE id=?',[$status,$id]);if($previous['status']!==$status)sql('INSERT INTO order_status_events(order_id,status,actor_id) VALUES(?,?,?)',[$id,$status,admin()['id']]);if($status==='cancelled')sql('DELETE FROM stock_reservations WHERE order_id=?',[$id]);audit('order.status',$id.' '.$status);run_hook('order.status',['id'=>$id,'status'=>$status]);go('admin/orders');}
  if($action==='booking/update'){
   $id=(int)($_POST['id']??0);$status=str('status',30);$quote=str('quote',30);$deposit=str('deposit',30);
   if(!in_array($status,['inquiry','quoted','awaiting_deposit','confirmed','completed','cancelled'],true))abort(400,'Invalid status');
   if(!is_numeric($quote)||!is_numeric($deposit)||(float)$quote<0||(float)$deposit<0||(float)$deposit>(float)$quote)abort(400,'Invalid quotation or deposit');
   $b=one('SELECT * FROM bookings WHERE id=?',[$id]);if(!$b)abort(404,'Booking not found');
   $lockName='thirtyvi:booking:'.$b['kind'].':'.$b['event_date'].':'.$b['event_time'];
   $locked=one('SELECT GET_LOCK(?,5) AS acquired',[$lockName]);if((int)($locked['acquired']??0)!==1)abort(409,'Booking is being updated. Retry.');
   try{if(in_array($status,['confirmed','awaiting_deposit'],true)&&!booking_slot_open($b['kind'],$b['event_date'],substr((string)$b['event_time'],0,5),$id)){
     flash('This date and time has reached capacity. Choose another slot before confirming.','error');go('admin/bookings');
   }sql('UPDATE bookings SET status=?,quote=?,deposit=? WHERE id=?',[$status,$quote,$deposit,$id]);audit('booking.update',(string)$id);run_hook('booking.status',['id'=>$id,'status'=>$status]);
   }finally{sql('SELECT RELEASE_LOCK(?)',[$lockName]);}go('admin/bookings');
  }
  if($action==='paystack/save'){
   try{paystack_save_settings($_POST);flash('Paystack settings saved.');}catch(Throwable $e){flash($e instanceof InvalidArgumentException?$e->getMessage():'Settings could not be saved. Please retry.','error');}
   go('admin/paystack');
  }
  if($action==='paystack/test'){
   $mode=str('mode',8);if(!in_array($mode,['test','live'],true))abort(400,'Invalid payment mode');
   try{paystack_request('GET','balance',paystack_key($mode));audit('paystack.connection',$mode);flash(ucfirst($mode).' secret key accepted by Paystack. Complete a checkout to validate the full payment flow.');}
   catch(Throwable $e){flash('Connection check failed. Check the saved key, PHP cURL, HTTPS and outbound access to api.paystack.co.','error');}
   go('admin/paystack');
  }
  if($action==='integrations/save'){
  if((admin()['role']??'')!=='admin')abort(403,'Owner access required');
  foreach(['smtp_host','smtp_port','smtp_user','smtp_from','smtp_from_name','smtp_security','whatsapp_endpoint','maps_public'] as $key)if(isset($_POST[$key]))save_integration($key,str($key,500));
  foreach(['smtp_password','whatsapp_token','maps_secret'] as $key)if(isset($_POST[$key]))save_integration($key,str($key,2000),true);
  audit('integrations.save');flash('Integration settings saved. Test services before enabling live transactions.');go('admin/integrations');
 }
 if($action==='media/upload'){
  if((admin()['role']??'')!=='admin')abort(403,'Owner access required');
  try{$url=upload_media('image');flash('Uploaded successfully: '.$url);}catch(Throwable $e){flash($e->getMessage(),'error');}go('admin/media');
 }
 if($action==='banner/save'){
  if(admin()['role']!=='admin')abort(403,'Owner access required');
  $id=(int)($_POST['id']??0);$placement=str('placement',20);$title=str('headline',150);$subtitle=str('subtitle',250);$image=str('image_url',1000);$target=str('target_url',500);$button=str('button_text',45);$enabled=isset($_POST['enabled'])?1:0;$sort=max(0,min(999,(int)($_POST['sort_order']??0)));
  if(!in_array($placement,['home_top','home_mid','menu_top','checkout_top'],true)||$title===''||!preg_match('~^/(?!/)[a-zA-Z0-9/_?=&%.-]*$~',$target)||($image!==''&&!preg_match('~^(https://[^\s]+|/public/(?:uploads|assets/banners)/[a-zA-Z0-9_.-]+)$~',$image)))abort(400,'Invalid banner details. Use internal button links and HTTPS/local images.');
  if($id===0){$existing=one('SELECT COUNT(*) n FROM site_banners WHERE placement=? AND enabled=1',[$placement]);if($enabled&&(int)$existing['n']>=3)abort(400,'Maximum three active banners per placement.');}
  if($id>0){sql('UPDATE site_banners SET placement=?,headline=?,subtitle=?,image_url=?,target_url=?,button_text=?,enabled=?,sort_order=? WHERE id=?',[$placement,$title,$subtitle,$image,$target,$button,$enabled,$sort,$id]);}
  else sql('INSERT INTO site_banners(placement,headline,subtitle,image_url,target_url,button_text,enabled,sort_order) VALUES(?,?,?,?,?,?,?,?)',[$placement,$title,$subtitle,$image,$target,$button,$enabled,$sort]);
  audit('banner.save',(string)$id);flash('Banner saved.');go('admin/banners');
 }
 if($action==='banner/delete'){
  if(admin()['role']!=='admin')abort(403,'Owner access required');$id=(int)($_POST['id']??0);sql('DELETE FROM site_banners WHERE id=?',[$id]);audit('banner.delete',(string)$id);go('admin/banners');
 }
 if($action==='sliders/save'){
  if((admin()['role']??'')!=='admin')abort(403,'Owner access required');
  $slides=[];foreach([1,2,3] as $i){$kind=str('slide'.$i.'_type',12);$url=str('slide'.$i.'_url',1000);if($kind==='video' && $url!=='' && !str_starts_with($url,'/public/uploads/')){flash('Videos must use uploaded local media paths.','error');go('admin/sliders');}if($kind==='image' && $url!=='' && !preg_match('~^(https://|/public/uploads/)~',$url)){flash('Use HTTPS or a local uploaded image.','error');go('admin/sliders');}$slides[]=['type'=>$kind==='video'?'video':'image','url'=>$url,'title'=>str('slide'.$i.'_title',180),'subtitle'=>str('slide'.$i.'_subtitle',400)];}
  save_integration('hero_slides',json_encode($slides));flash('Homepage slides updated.');go('admin/sliders');
 }
 if($action==='chef/availability'){
  if((admin()['role']??'')!=='admin')abort(403,'Owner access required');
  $value=($_POST['available']??'')==='1'?'1':'0';
  sql('INSERT INTO settings(`key`,value) VALUES(?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',['chef_available',$value]);
  $saved=one('SELECT value FROM settings WHERE `key`=?',['chef_available']);
  if(($saved['value']??null)!==$value){flash('Availability could not be saved. Please retry.','error');go('admin/settings');}
  audit('chef.availability',$value);
  flash($value==='1'?'Private chef inquiries are now open.':'Private chef inquiries are now paused.');go('admin/settings');
 }
 if($action==='settings/save'){foreach(['brand','tagline','email','phone','delivery_fee','booking_capacity','booking_start','booking_end','hero_title','hero_description','about','theme_default','theme_accent','theme_accent_ink','theme_dark_bg','theme_light_bg'] as $k)if(isset($_POST[$k]) && (!str_starts_with($k,'theme_') || ($k==='theme_default' ? in_array($_POST[$k],['dark','light'],true) : (bool)preg_match('/^#[0-9a-fA-F]{6}$/',(string)$_POST[$k]))))sql('INSERT INTO settings(`key`,value) VALUES(?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',[$k,str($k,3000)]);audit('settings.update');flash('Settings updated.');go('admin/settings');}
  if($action==='page/save'){$slug=str('slug',100);if(!preg_match('/^[a-z0-9-]+$/',$slug))abort(400,'Invalid page slug');sql('INSERT INTO pages(slug,title,body) VALUES(?,?,?) ON DUPLICATE KEY UPDATE title=VALUES(title),body=VALUES(body)',[$slug,str('title',200),str('body',20000)]);audit('page.update',$slug);go('admin/pages');}
  if($action==='product-review/moderate'){$id=(int)($_POST['id']??0);$mode=str('action',16);if($mode==='approve'||$mode==='hide'){sql('UPDATE product_reviews SET approved=? WHERE id=?',[$mode==='approve'?1:0,$id]);}elseif($mode==='delete'){sql('DELETE FROM product_reviews WHERE id=?',[$id]);}else abort(400,'Invalid moderation action');audit('product_review.moderate',$id.' '.$mode);go('admin/product-reviews');}
  if($action==='review/approve'){$id=(int)($_POST['id']??0);sql('UPDATE reviews SET approved=1 WHERE id=?',[$id]);go('admin/reviews');}
  if($action==='payment/start'){$type=str('type',20);$id=(int)($_POST['id']??0);if($type==='order'){$o=one('SELECT * FROM orders WHERE id=?',[$id]);if(!$o)abort(404,'Order missing');paystack_start('order',$id,$o['email'],(float)$o['total']);}abort(400,'Invalid payment type');}
 }
 if($path==='pay'){$type=str('type',20);$id=(int)($_POST['id']??0);if($type==='order'){$o=one('SELECT * FROM orders WHERE id=?',[$id]);if(!$o||!paystack_can_pay('order',$o))abort(400,'Order is not payable');if(!((user()['id']??null)!==null&&(int)$o['user_id']===(int)user()['id'])&&(!isset($_SESSION['order_access'][$id])||!hash_equals($_SESSION['order_access'][$id],$o['reference'])))abort(403,'Order access denied');paystack_start('order',$id,$o['email'],(float)$o['total']);}
 if($type==='booking'){$b=one('SELECT * FROM bookings WHERE id=?',[$id]);if(!$b||!paystack_can_pay('booking',$b))abort(400,'Booking not payable');if(!((user()['id']??null)!==null&&(int)$b['user_id']===(int)user()['id'])&&(!isset($_SESSION['booking_access'][$id])||!hash_equals($_SESSION['booking_access'][$id],$b['reference'])))abort(403,'Booking access denied');$paid=one("SELECT COALESCE(SUM(amount),0) total FROM payments WHERE entity_type='booking' AND entity_id=? AND status='success'",[$id]);$due=(float)$b['quote']-(float)$paid['total'];$amount=(float)$paid['total']>0?$due:((float)$b['deposit']>0?(float)$b['deposit']:$due);if($amount<=0)abort(400,'No outstanding payment');paystack_start('booking',$id,$b['email'],$amount);}abort(400,'Invalid payment');}
}
if($path===''||$path==='home'){require ROOT.'/app/storefront.php';exit;}
if($path==='shop'){layout('The 36° Store',function(){
 $cats=all('SELECT c.id,c.name,c.slug FROM categories c WHERE '.catalogue_store_condition().' ORDER BY c.name');
 $ids=array_map('intval',array_column($cats,'id'));$cat=max(0,(int)($_GET['category']??0));if($cat&&!in_array($cat,$ids,true))$cat=0;
 ?><section class="container vi-shop"><div class="vi-shop-heading"><div><span class="eyebrow">MORE THAN MEALS</span><h1>The 36° Store</h1><p class="muted">Brownies, small chops, special drinks and treats.</p></div><a href="/cart">View bag →</a></div><nav class="vi-shop-tabs" aria-label="Store categories"><a class="<?=$cat===0?'active':''?>" href="/shop">All</a><?php foreach($cats as $c):?><a class="<?=$cat===(int)$c['id']?'active':''?>" href="/shop?category=<?=(int)$c['id']?>"><?=esc($c['name'])?></a><?php endforeach;?></nav><?php
 if(!$cats):?><div class="vi-shop-empty">The store is being stocked. Admin can create categories such as Brownies, Small Chops, Special Drinks and Desserts in Menu & Products.</div><?php else:
 $selected=$cat?[$cat]:$ids;$marks=implode(',',array_fill(0,count($selected),'?'));
 $products=all('SELECT p.*,c.name category FROM products p JOIN categories c ON c.id=p.category_id WHERE p.available=1 AND p.category_id IN ('.$marks.') ORDER BY p.featured DESC,p.id DESC LIMIT 60',$selected);
 if(!$products):?><div class="vi-shop-empty">No items in this category yet.</div><?php else:?><div class="vi-shop-grid"><?php foreach($products as $p):$hasOptions=(bool)meal_options((int)$p['id']);?><article class="vi-shop-product"><div class="vi-shop-photo"><a href="/product/<?=(int)$p['id']?>" aria-label="View <?=esc($p['name'])?>"><img src="<?=esc($p['image'])?>" alt="<?=esc($p['name'])?>" loading="lazy"></a><?php if($hasOptions):?><a class="vi-shop-quick" href="/product/<?=(int)$p['id']?>" aria-label="Customize <?=esc($p['name'])?>">+</a><?php else:?><form class="vi-shop-quick-form" method="post" action="/cart/add"><?=token()?><input type="hidden" name="product_id" value="<?=(int)$p['id']?>"><input type="hidden" name="qty" value="1"><input type="hidden" name="return_to" value="shop"><input type="hidden" name="category" value="<?=$cat?>"><button type="submit" class="vi-shop-quick" aria-label="Add <?=esc($p['name'])?> to bag">+</button></form><?php endif;?></div><strong><?=money($p['price'])?></strong><a class="vi-shop-name" href="/product/<?=(int)$p['id']?>"><?=esc($p['name'])?></a><?php if($hasOptions):?><small class="vi-shop-hint">Choose options</small><?php endif;?></article><?php endforeach;?></div><?php endif;endif;?></section><?php });exit;}
if($path==='menu'){layout('Our Menu',function(){head('Our Menu','Good food, thoughtfully prepared. Find something you will love.');
$cat=max(0,(int)($_GET['category']??0));$search=trim(substr((string)($_GET['q']??''),0,90));$cats=all('SELECT * FROM categories');
$where=['p.available=1'];$params=[];if($cat){$where[]='p.category_id=?';$params[]=$cat;}if($search!==''){$where[]='(p.name LIKE ? OR p.description LIKE ?)';$params[]='%'.$search.'%';$params[]='%'.$search.'%';}
$condition=implode(' AND ',$where);$countRow=one('SELECT COUNT(*) AS total FROM products p WHERE '.$condition,$params);$total=(int)($countRow['total']??0);
$pages=max(1,(int)ceil($total/4));$page=min($pages,max(1,(int)($_GET['page']??1)));$offset=($page-1)*4;
$products=all('SELECT p.*,c.name category FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE '.$condition.' ORDER BY p.featured DESC,p.id DESC LIMIT 4 OFFSET '.(int)$offset,$params);
$queryBase=[];if($cat)$queryBase['category']=$cat;if($search!=='')$queryBase['q']=$search;
$pageUrl=static function(int $n)use($queryBase):string{return '/menu?'.http_build_query(array_merge($queryBase,['page'=>$n]));};
?><?php vi_banners('menu_top');?><section class="section container menu-section"><form class="menu-search" action="/menu" method="get" role="search"><label for="meal-search">What are you craving?</label><div class="menu-search-row"><input id="meal-search" type="search" name="q" value="<?=esc($search)?>" placeholder="Search dishes and flavours…" autocomplete="off"><?php if($cat):?><input type="hidden" name="category" value="<?=$cat?>"><?php endif;?><button class="btn gold" type="submit">Find a dish →</button></div></form>
<div class="filters" aria-label="Food categories"><a class="chip <?=$cat===0?'active':''?>" href="/menu<?= $search!==''?'?q='.rawurlencode($search):'' ?>">All dishes</a><?php foreach($cats as $c):?><a class="chip <?=$cat===(int)$c['id']?'active':''?>" href="/menu?category=<?=(int)$c['id']?><?= $search!==''?'&amp;q='.rawurlencode($search):'' ?>"><?=esc($c['name'])?></a><?php endforeach;?></div>
<div class="menu-results"><p class="muted"><?=number_format($total)?> <?= $total===1?'dish':'dishes' ?> found</p><?php if($total):?><p class="muted">Showing <?=($offset+1)?>–<?=min($offset+4,$total)?> of <?=$total?></p><?php endif;?></div>
<?php if(!$products):?><div class="empty">No dishes match your search right now. Try another name or category.</div><?php else:?><div class="grid menu-grid food-grid"><?php foreach($products as $product)card($product);?></div><?php endif;?>
<?php if($pages>1):?><nav class="menu-pagination" aria-label="Menu pages"><?php if($page>1):?><a class="page-control" href="<?=esc($pageUrl($page-1))?>" rel="prev">← Previous</a><?php endif;?><?php for($n=1;$n<=$pages;$n++):?><a class="page-control <?=$n===$page?'current':''?>" href="<?=esc($pageUrl($n))?>" <?=$n===$page?'aria-current="page"':''?>><?=$n?></a><?php endfor;?><?php if($page<$pages):?><a class="page-control" href="<?=esc($pageUrl($page+1))?>" rel="next">Next →</a><?php endif;?></nav><?php endif;?></section><?php });exit;}
if(preg_match('~^product/(\d+)$~',$path,$m)){$p=one('SELECT * FROM products WHERE id=? AND available=1',[(int)$m[1]]);if(!$p)abort(404,'Meal not found');layout($p['name'],function()use($p){?><section class="section container split product-detail"><?php catalogue_gallery($p);?><div><span class="eyebrow">Freshly prepared</span><h1 style="font-size:3.5rem"><?=esc($p['name'])?></h1><p class="muted"><?=esc($p['description'])?></p><h3 class="price"><?=money($p['price'])?></h3><p class="muted">Made for your table. Have a preference or allergy? Tell us in your order notes, and our team will review it.</p><form class="form" method="post" action="/cart/add"><?=token()?><input type="hidden" name="product_id" value="<?=$p['id']?>"><?php $optionGroups=meal_options((int)$p['id']);foreach($optionGroups as $group):?><fieldset class="meal-options" data-option-group data-max="<?=(int)$group['max_select']?>" data-required="<?=(int)$group['required']?>"><legend><?=esc($group['name'])?> <small><?= (int)$group['required']?'Required':'Optional' ?> · Choose up to <?=(int)$group['max_select']?></small></legend><?php foreach($group['choices'] as $choice):?><label class="meal-choice"><input type="<?=(int)$group['max_select']===1?'radio':'checkbox'?>" name="options[<?=(int)$group['id']?>]<?=(int)$group['max_select']===1?'':'[]'?>" value="<?=(int)$choice['id']?>" data-extra="<?=(float)$choice['price_delta']?>"><span><?=esc($choice['name'])?></span><strong><?= (float)$choice['price_delta']===0.0?'Included':'+'.money($choice['price_delta']) ?></strong></label><?php endforeach;?></fieldset><?php endforeach;?><label for="product-qty">Quantity</label><div class="qty-stepper"><button type="button" data-qty-minus aria-label="Decrease quantity">−</button><input id="product-qty" data-qty type="number" min="1" max="25" name="qty" value="1" required><button type="button" data-qty-plus aria-label="Increase quantity">+</button></div><p class="live-total" data-live-total data-price="<?=esc($p['price'])?>">Total: <?=money($p['price'])?></p><label>Special instructions (optional)</label><textarea name="notes" placeholder="Any preferences or instructions..."></textarea><button class="btn gold full">Add to Bag →</button></form></div></section><section class="section container product-reviews"><div class="section-head"><div><span class="eyebrow">From our guests</span><h2>What people are saying</h2></div></div><?php $reviews=all('SELECT r.stars,r.body,r.created_at,u.name FROM product_reviews r JOIN users u ON u.id=r.user_id WHERE r.product_id=? AND r.approved=1 ORDER BY r.id DESC LIMIT 20',[(int)$p['id']]);$rating=one('SELECT COUNT(*) n,ROUND(AVG(stars),1) avg_stars FROM product_reviews WHERE product_id=? AND approved=1',[(int)$p['id']]);if($reviews):?><p class="muted"><strong><?=esc($rating['avg_stars'])?> / 5</strong> from <?=(int)$rating['n']?> approved reviews</p><div class="review-grid"><?php foreach($reviews as $review):?><article class="review-tile"><div class="review-stars" aria-label="<?=(int)$review['stars']?> of 5 stars"><?=str_repeat('★',(int)$review['stars'])?><span><?=str_repeat('☆',5-(int)$review['stars'])?></span></div><p><?=esc($review['body'])?></p><small><?=esc($review['name'])?> · <?=esc(substr($review['created_at'],0,10))?></small></article><?php endforeach;?></div><?php else:?><p class="muted">No reviews yet. Be the first to share your experience.</p><?php endif;?><?php if(user()):?><form method="post" action="/product-review/create" class="form review-form"><?=token()?><input type="hidden" name="product_id" value="<?=(int)$p['id']?>"><h3>Leave a review</h3><label for="stars">Your rating</label><select id="stars" name="stars" required><option value="">Choose a rating</option><?php for($i=5;$i>=1;$i--):?><option value="<?=$i?>"><?=$i?> <?= $i===1?'star':'stars' ?></option><?php endfor;?></select><label for="review-body">Your experience</label><textarea id="review-body" name="body" minlength="10" maxlength="1200" required placeholder="Tell us what you enjoyed about this meal."></textarea><button class="btn gold">Submit review</button><p class="small muted">Reviews are checked before appearing publicly. One review per customer per meal.</p></form><?php else:?><p class="muted"><a href="/login">Sign in</a> to leave a review.</p><?php endif;?></section><?php });exit;}
if($path==='cart'){$cart=$_SESSION['cart']??[];layout('Your Bag',function()use($cart){head('Your Bag','Good choices. Let’s get them ready.');?><section class="section container cart-page"><div class="cart-layout"><div class="cart-items"><?php $sum=0;$valid=0;foreach($cart as $key=>$c):$p=one('SELECT * FROM products WHERE id=?',[$c['id']]);if(!$p)continue;try{$calc=meal_cart_price($c,$p);}catch(Throwable $e){?><div class="cart-warning">One of your selected extras has changed. <a href="/product/<?=(int)$p['id']?>">Choose your options again →</a><form method="post" action="/cart/remove"><?=token()?><input type="hidden" name="key" value="<?=esc($key)?>"><button class="btn">Remove old selection</button></form></div><?php continue;}$valid++;$sum+=$calc['unit']*$c['qty'];?><article class="cart-line"><a class="cart-thumb" href="/product/<?=(int)$p['id']?>"><img loading="lazy" src="<?=esc($p['image'])?>" alt="<?=esc($p['name'])?>"></a><div class="cart-details"><a class="cart-name" href="/product/<?=(int)$p['id']?>"><?=esc($p['name'])?></a><?php foreach($calc['labels'] as $selection):?><small class="cart-option"><?=esc($selection)?></small><?php endforeach;?><?php if(!empty($c['notes'])):?><small class="cart-option"><?=esc($c['notes'])?></small><?php endif;?><form method="post" action="/cart/update" class="cart-controls"><?=token()?><input type="hidden" name="key" value="<?=esc($key)?>"><label>Qty <input aria-label="Quantity for <?=esc($p['name'])?>" type="number" name="qty" min="0" max="25" value="<?=(int)$c['qty']?>"></label><button class="btn" type="submit">Update</button></form></div><div class="cart-side"><strong><?=money($calc['unit']*$c['qty'])?></strong><form method="post" action="/cart/remove"><?=token()?><input type="hidden" name="key" value="<?=esc($key)?>"><button class="cart-remove" type="submit">Remove</button></form></div></article><?php endforeach;?><?php if(!$valid):?><div class="empty">Your bag is empty. <a href="/menu">Explore our menu →</a></div><?php endif;?></div><aside class="summary cart-summary"><h3>Order Summary</h3><div class="cart-summary-line"><span>Subtotal</span><strong><?=money($sum)?></strong></div><p class="muted small">Delivery fees are calculated at checkout.</p><?php if($valid):?><a class="btn gold full" href="/checkout">Proceed to Checkout →</a><?php endif;?><a class="cart-continue" href="/menu">← Continue browsing</a></aside></div></section><?php });exit;}
if($path==='checkout'){header('Cache-Control: private, no-store, max-age=0');$cart=$_SESSION['cart']??[];if(!$cart)go('cart');$u=user();$previous=$u?one('SELECT phone,address,fulfillment FROM orders WHERE user_id=? ORDER BY id DESC LIMIT 1',[$u['id']]):null;layout('Checkout',function()use($cart,$u,$previous){$sum=0;$items=[];foreach($cart as $c){$p=one('SELECT * FROM products WHERE id=?',[$c['id']]);if(!$p)continue;$calc=meal_cart_price($c,$p);$price=$calc['unit']*$c['qty'];$sum+=$price;$items[]=['product'=>$p,'qty'=>$c['qty'],'price'=>$price,'options'=>$calc['labels']];}?>
<?php vi_banners('checkout_top');?><section class="checkout-flow container" data-checkout-flow>
 <div class="checkout-top"><a href="/cart" class="checkout-back" aria-label="Back to your bag">← <span>Back to bag</span></a><span class="checkout-secure">🔒 Secure checkout</span></div>
 <?php checkout_error_summary($_SESSION['checkout_errors']??[]);?><div class="checkout-heading"><span class="checkout-eyebrow">ALMOST THERE</span><h1>Let's get your food to you.</h1><p>Review your meal, choose delivery or pickup, and confirm your details.</p></div>
 <div class="checkout-steps" aria-label="Checkout steps"><span class="done"><i>✓</i> Your bag</span><span class="current" aria-current="step"><i>2</i> Your details</span><span><i>3</i> Payment</span></div>
 <div class="checkout-grid">
  <div class="checkout-primary">
   <details class="checkout-order-card" open><summary><span><strong>Your order</strong><small><?=count($items)?> selection<?=count($items)===1?'':'s'?></small></span><b><?=money($sum)?></b><span class="checkout-chevron" aria-hidden="true">⌄</span></summary><div class="checkout-order-list"><?php foreach($items as $item):?><div class="checkout-product"><img loading="lazy" src="<?=esc($item['product']['image'])?>" alt=""><div><strong><?=esc($item['product']['name'])?></strong><small>Qty <?=$item['qty']?><?php foreach($item['options'] as $option):?> · <?=esc($option)?><?php endforeach;?></small></div><b><?=money($item['price'])?></b></div><?php endforeach;?></div><a class="checkout-edit" href="/cart">Edit your bag →</a></details>
   <form id="checkout-form" action="/public/checkout.php" method="post" class="checkout-details-form" data-checkout-form data-checkout-build="6.1.2" data-payment-mode="<?=esc(paystack_mode())?>"><?=token()?>
    <details class="checkout-panel" data-checkout-section open><summary class="checkout-panel-title"><span class="checkout-panel-icon">1</span><div><h2>How would you like it?</h2><p>Choose what works for you.</p></div><span class="checkout-disclosure" aria-hidden="true">⌄</span></summary>
     <div class="checkout-choice-row" role="radiogroup" aria-label="Order fulfillment"><label class="checkout-choice"><input type="radio" name="fulfillment" value="delivery" <?=checkout_value('fulfillment','delivery')==='delivery'?'checked':''?>><span>🚴 <strong>Delivery</strong><small>Bring it to me</small></span></label><label class="checkout-choice"><input type="radio" name="fulfillment" value="pickup" <?=checkout_value('fulfillment','delivery')==='pickup'?'checked':''?>> <span>🛍️ <strong>Pickup</strong><small>I'll collect it</small></span></label></div>
     <div class="checkout-gift-toggle" data-gift-option><label class="checkout-gift-check"><span class="checkout-gift-icon" aria-hidden="true">🎁</span><span class="checkout-gift-label">Send as a gift</span><input type="checkbox" name="is_gift" value="1" data-gift-toggle <?=checkout_value('is_gift')==='1'?'checked':''?> aria-label="Send this delivery as a gift"><span class="checkout-gift-indicator" aria-hidden="true">›</span></label></div>
     <div class="checkout-gift-fields" data-gift-fields hidden><div class="checkout-gift-heading"><strong>Recipient details</strong></div><div class="checkout-fields"><div class="gift-field"><label for="gift-recipient-name">Recipient name <span class="checkout-required">*</span></label><input id="gift-recipient-name" name="recipient_name" value="<?=esc(checkout_value('recipient_name'))?>" maxlength="150" autocomplete="off" placeholder="Their full name" data-gift-required></div><div class="gift-field"><label for="gift-recipient-phone">Phone number <span class="checkout-required">*</span></label><input id="gift-recipient-phone" name="recipient_phone" value="<?=esc(checkout_value('recipient_phone'))?>" type="tel" inputmode="tel" maxlength="60" placeholder="0800 000 0000" data-gift-required></div><div class="checkout-wide" data-gift-address><label for="gift-recipient-address">Recipient's delivery address <span class="checkout-required">*</span></label><textarea id="gift-recipient-address" name="recipient_address" rows="2" placeholder="House, street, landmark" data-gift-required><?=esc(checkout_value('recipient_address'))?></textarea></div><div class="checkout-wide"><label for="gift-message">Gift note <small>(optional)</small></label><textarea id="gift-message" name="gift_message" maxlength="500" rows="2" placeholder="A little message to make their day"><?=esc(checkout_value('gift_message'))?></textarea><div class="gift-message-tools"><span class="gift-message-ideas"><button type="button" data-gift-preset="Happy birthday! Wishing you a beautiful day.">Birthday</button><button type="button" data-gift-preset="Thinking of you. Enjoy something delicious!">Thinking of you</button><button type="button" data-gift-preset="Thank you for everything. Enjoy your meal!">Thank you</button></span><small data-gift-count>0/500</small></div></div></div><label class="checkout-anonymous"><input type="checkbox" name="gift_anonymous" value="1" <?=checkout_value('gift_anonymous')==='1'?'checked':''?>><span>Keep my name private from the recipient</span></label><p class="muted small">The recipient's number is used for fulfilment. Your payment and receipt remain under your contact details.</p></div>
     <details class="vi-schedule" data-schedule><summary>🕒 Schedule for later <small>(optional)</small></summary><div class="vi-schedule-body"><label for="vi-scheduled-for">Preferred delivery or pickup time</label><input id="vi-scheduled-for" type="datetime-local" name="scheduled_for" value="<?=esc(checkout_value('scheduled_for'))?>" data-schedule-input step="60" min="<?=esc(vi_local_now()->modify('+2 hours +1 minute')->format('Y-m-d\TH:i'))?>" max="<?=esc(vi_local_now()->modify('+14 days')->format('Y-m-d\TH:i'))?>"><div class="vi-slot-actions"><button type="button" data-vi-slot="tomorrow-lunch">Tomorrow 12 PM</button><button type="button" data-vi-slot="tomorrow-evening">Tomorrow 6 PM</button><button type="button" data-vi-slot="day-after-lunch">Day after 12 PM</button></div><small>At least 2 hours ahead, within 14 days, 9 AM–8 PM (Lagos time). We'll confirm availability after payment.</small><button type="button" class="vi-clear-time" data-clear-schedule>Clear time</button></div></details>
     <div class="checkout-address" data-delivery-address><label for="checkout-address">Delivery address <span class="checkout-required">*</span></label><textarea id="checkout-address" name="address" rows="2" placeholder="Street, house number, landmark" autocomplete="street-address"><?=esc(checkout_value('address',($previous['fulfillment']??'')==='delivery'?(string)$previous['address']:''))?></textarea></div>
    <button type="button" class="btn gold checkout-details-next" data-checkout-details-next hidden>Contact details →</button></details>
    <details class="checkout-panel" data-checkout-section open><summary class="checkout-panel-title"><span class="checkout-panel-icon">2</span><div><h2>Your contact details</h2><p>So we can reach you about your order.</p></div><span class="checkout-disclosure" aria-hidden="true">⌄</span></summary>
     <div class="checkout-fields"><div><label for="checkout-name">Full name <span class="checkout-required">*</span></label><input id="checkout-name" name="name" type="text" autocomplete="name" value="<?=esc(checkout_value('name',$u['name']??''))?>" placeholder="Your full name" maxlength="150" required></div><div><label for="checkout-phone">Phone number <span class="checkout-required">*</span></label><input id="checkout-phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="0800 000 0000" value="<?=esc(checkout_value('phone',$previous['phone']??''))?>" maxlength="60" required></div><div class="checkout-wide"><label for="checkout-email">Email address <span class="checkout-required">*</span></label><input id="checkout-email" name="email" type="email" autocomplete="email" value="<?=esc(checkout_value('email',$u['email']??''))?>" placeholder="you@example.com" maxlength="190" required></div></div>
     <label class="vi-save-contact"><input type="checkbox" id="vi-save-contact" autocomplete="off"><span>Remember my contact details on this device</span></label><details class="checkout-notes"><summary>＋ Add a note for the kitchen <small>(optional)</small></summary><textarea name="notes" rows="2" placeholder="Allergies, spice level or delivery instructions" maxlength="500"><?=esc(checkout_value('notes'))?></textarea></details>
    </details>
    <div class="checkout-desktop-submit"><p>By continuing, you agree to our <a href="/page/terms" target="_blank" rel="noopener">Terms</a> and <a href="/page/privacy" target="_blank" rel="noopener">Privacy Policy</a>.</p><button class="checkout-submit" type="submit"><?=paystack_enabled()?'Continue to Paystack':'Save order request'?> <span>→</span></button><small><?=paystack_enabled()?'Secure payment with Paystack.':'Online payments are paused. Your order request will be saved for our team.'?></small></div>
   </form>
  </div>
  <aside class="checkout-aside"><div class="checkout-total-card"><span>YOUR TOTAL</span><strong><?=money($sum)?></strong><p>Delivery fee: <?=money(setting('delivery_fee','0'))?> (if delivery is selected)</p><div class="checkout-total-line"><span>Payment</span><b>Paystack secured</b></div><p>No payment is taken until you proceed to Paystack.</p></div></aside>
 </div>
 <div class="checkout-mobile-bottom"><div><small>Order subtotal</small><strong><?=money($sum)?></strong></div><button form="checkout-form" class="checkout-submit" type="submit"><?=paystack_enabled()?'Pay securely':'Save request'?> <span>→</span></button></div>
</section><?php });exit;}
if(in_array($path,['private-chef','catering'],true)){require ROOT.'/app/booking-form.php';exit;}
if($path==='about'){layout('Our Story',function(){head('Our Story','The people, passion and purpose behind every plate.');?><section class="section container split"><img src="https://images.unsplash.com/photo-1556911220-bff31c812dba?auto=format&fit=crop&w=1100&q=85" alt="Chef preparing food"><div><span class="eyebrow">Our philosophy</span><h2>We believe in the beauty of a great meal.</h2><p class="muted"><?=nl2br(esc(setting('about')))?></p><a class="btn" href="/private-chef">Discover our experiences</a></div></section><?php });exit;}
if(preg_match('~^page/([a-z0-9-]+)$~',$path,$m)){$p=one('SELECT * FROM pages WHERE slug=?',[$m[1]]);if(!$p)abort(404,'Page not found');layout($p['title'],function()use($p){head($p['title']);?><section class="section container" style="max-width:850px"><p><?=nl2br(esc($p['body']))?></p></section><?php });exit;}
if(in_array($path,['forgot-password','reset-password'],true)){
 header('Referrer-Policy: no-referrer');header('Cache-Control: no-store');
 $reset=$path==='reset-password';layout($reset?'Choose a password':'Reset your password',function()use($reset){head($reset?'A fresh start.':'Forgot your password?');?><section class="section container" style="max-width:540px"><p class="muted"><?=$reset?'Choose at least 10 characters. Your reset link can only be used once.':'Enter your account email and we will send a reset link.'?></p><form class="form" method="post"><?=token()?><?php if($reset):?><input type="hidden" name="reset_token" value="<?=esc(substr((string)($_GET['token']??''),0,64))?>"><?php auth_password_field('new-password',true);?><?php else:field('Account email','email','email');endif;?><button class="btn gold"><?=$reset?'Update password':'Send reset link'?></button></form><p><a href="/login">Back to sign in →</a></p></section><?php });exit;
}
if(in_array($path,['login','register'],true)){$register=$path==='register';layout($register?'Create Account':'Sign In',function()use($register){head($register?'Create an Account':'Welcome Back');?><section class="section container" style="max-width:540px"><form class="form" method="post"><?=token()?><?php if($register):?><label for="name">Your name</label><input id="name" name="name" autocomplete="name" maxlength="150" required><?php endif;?><label for="email">Email address</label><input id="email" name="email" type="email" autocomplete="<?=$register?'email':'username'?>" autocapitalize="off" spellcheck="false" maxlength="190" required><?php auth_password_field('password',$register);?><button class="btn gold full"><?=$register?'Create Account':'Sign In'?></button></form><p class="center"><?=$register?'Already registered? <a href="/login">Sign in</a>':'New here? <a href="/register">Create an account</a> · <a href="/forgot-password">Forgot password?</a>'?></p></section><?php });exit;}
if($path==='track'){layout('Track Your Order',function(){?><section class="container vi-track-page"><div class="vi-track-card"><span class="eyebrow">ORDER TRACKING</span><h1>Track your order</h1><p class="muted">Enter your order reference to check its current stage.</p><form class="vi-track-form" action="/track" method="post"><?=token()?><label for="vi-order-ref">Order reference</label><div class="vi-track-input-row"><input id="vi-order-ref" name="reference" required maxlength="40" autocomplete="off" placeholder="ORD-XXXXXXXXXX"><button class="btn gold" type="submit">Track →</button></div></form><p class="muted small">Status only. For payment or delivery details, sign in or contact support.</p></div></section><?php });exit;}
if($path==='track/result'){
 header('Cache-Control: private, no-store');header('X-Robots-Tag: noindex, nofollow');
 $access=$_SESSION['track_public']??[];
 if(empty($access['reference'])||($access['until']??0)<time())go('track');
 $record=one('SELECT reference,status,fulfillment FROM orders WHERE reference=?',[$access['reference']]);if(!$record)go('track');
 layout('Order Progress',function()use($record){
 $steps=['pending'=>'Received','accepted'=>'Accepted','preparing'=>'Preparing','ready'=>'Ready','dispatched'=>'On the way','completed'=>'Completed'];if($record['fulfillment']==='pickup')unset($steps['dispatched']);
 $current=array_search($record['status'],array_keys($steps),true);
 ?><section class="container vi-track-page"><div class="vi-track-card"><span class="eyebrow">ORDER STATUS</span><h1><?=esc($record['reference'])?></h1><p class="vi-current-stage"><?=esc($record['status']==='cancelled'?'Cancelled':($steps[$record['status']]??ucfirst($record['status'])))?></p><?php if($record['status']!=='cancelled'):?><ol class="vi-public-steps"><?php foreach($steps as $key=>$name):$i=array_search($key,array_keys($steps),true);?><li class="<?=$current!==false&&$i<=$current?'done':''?>"><?=esc($name)?></li><?php endforeach;?></ol><?php else:?><p class="muted">Contact our team if you need assistance.</p><?php endif;?><a href="/track" class="vi-track-again">Track another order →</a></div></section><?php });exit;
}
if($path==='account'){require ROOT.'/app/account-dashboard.php';exit;}
if(preg_match('~^(order|booking)/(\d+)$~',$path,$m)){$type=$m[1];$id=(int)$m[2];$record=one('SELECT * FROM '.($type==='order'?'orders':'bookings').' WHERE id=?',[$id]);if(!$record)abort(404,'Record not found');/* Access is granted at checkout or after verified lookup, never by a query parameter. */$u=user();if(!is_admin()&&!(($u['id']??null)!==null&&(int)$record['user_id']===(int)$u['id'])&&(!isset($_SESSION[$type.'_access'][$id])||!hash_equals($_SESSION[$type.'_access'][$id],$record['reference'])))abort(403,'Access denied');layout(ucfirst($type).' Details',function()use($type,$id,$record){head($type==='order'?'Your Order':'Your Booking',$record['reference']);?><section class="section container" style="max-width:850px"><div class="summary"><span class="eyebrow">Current status</span><h2><?=esc(ucwords(str_replace('_',' ',$record['status'])))?></h2><p>Payment: <strong><?=esc($record['payment_status'])?></strong></p><?php if($type==='order'):?><p>Total: <strong><?=money($record['total'])?></strong></p><?php
 $schedule=one('SELECT requested_for FROM order_schedules WHERE order_id=?',[$id]);$events=all('SELECT status,created_at FROM order_status_events WHERE order_id=? ORDER BY id ASC',[$id]);
 if($schedule):?><p class="vi-scheduled-chip">🕒 Requested for <?=esc(date('D j M Y, g:i A',strtotime($schedule['requested_for'])))?> (Lagos time) · Subject to confirmation</p><?php endif;?><div class="vi-tracking"><h3>Track your order</h3><p class="muted small">Updates appear as the kitchen changes your order status. This page does not provide live GPS tracking.</p><ol class="vi-tracking-list"><?php $steps=['pending'=>'Order received','accepted'=>'Accepted','preparing'=>'Preparing','ready'=>'Ready for '.($record['fulfillment']==='pickup'?'pickup':'dispatch'),'dispatched'=>'On the way','completed'=>'Completed'];if($record['fulfillment']==='pickup')unset($steps['dispatched']);$progress=array_search($record['status'],array_keys($steps),true);foreach($steps as $code=>$label):$i=array_search($code,array_keys($steps),true);$eventTime=null;foreach($events as $event)if($event['status']===$code){$eventTime=$event['created_at'];break;}?><li class="<?=$progress!==false&&$i<=$progress?'vi-step-done':''?>"><span><?=esc($label)?></span><?php if($eventTime):?><small><?=esc(date('j M, g:i A',strtotime($eventTime)))?></small><?php endif;?></li><?php endforeach;?></ol><?php if($record['status']==='cancelled'):?><p class="vi-cancelled">This order was cancelled. Contact support if you need help.</p><?php endif;?></div><?php if($gift=one('SELECT * FROM order_gifts WHERE order_id=?',[$id])):?><div class="gift-order-summary"><strong>🎁 Gift order</strong><p>Recipient: <?=esc($gift['recipient_name'])?> · <?=esc($gift['recipient_phone'])?></p><?php if($gift['recipient_address']):?><p>Deliver to: <?=esc($gift['recipient_address'])?></p><?php endif;?><?php if($gift['gift_message']):?><p>Gift note: <?=esc($gift['gift_message'])?></p><?php endif;?><small><?=!empty($gift['anonymous_sender'])?'Sender identity hidden from recipient':'Sender name may be shared with recipient'?></small></div><?php endif;?><?php foreach(all('SELECT * FROM order_items WHERE order_id=?',[$id]) as $item):?><div class="itemrow"><span><?=esc($item['product_name'])?> × <?=$item['quantity']?><?php foreach(json_decode($item['customizations']??'[]',true)?:[] as $selection):?><small class="checkout-option"><?=esc($selection)?></small><?php endforeach;?></span><strong><?=money($item['unit_price']*$item['quantity'])?></strong></div><?php endforeach;?><?php else:?><p>Event: <?=esc($record['event_type'])?> on <?=esc($record['event_date'])?></p><p>Guests: <?=esc((string)$record['guests'])?></p><?php if($record['quote']!==null):?><p>Quotation: <strong><?=money($record['quote'])?></strong></p><p>Required deposit: <?=money($record['deposit'])?></p><?php endif;?><?php endif;?><?php if($type==='order'&&$record['payment_status']==='paid'&&$record['status']!=='cancelled'&&($chefNumber=wa_phone(setting('chef_whatsapp',setting('phone'))))):?><div class="wa-customer-prompt"><h3>Payment confirmed!</h3><p>Your order is already recorded. Would you like to share its details with our chef on WhatsApp?</p><div class="wa-actions"><a class="btn gold" target="_blank" rel="noopener noreferrer" href="<?=esc(wa_link($chefNumber,wa_message('customer_order',$record,'order')))?>">Yes, open WhatsApp ↗</a><a class="btn" href="/">No thanks, back to home</a></div><p class="muted small">WhatsApp opens with a prepared message. Press Send there to share it.</p></div><?php endif;?><?php if(paystack_can_pay($type,$record)&&paystack_enabled()):?><form method="post" action="/pay"><?=token()?><input type="hidden" name="type" value="<?=esc($type)?>"><input type="hidden" name="id" value="<?=$id?>"><button class="btn gold"><?=paystack_mode()==='test'?'Paystack test checkout →':'Pay securely with Paystack →'?></button></form><?php endif;?><?php if(!paystack_enabled()&&paystack_can_pay($type,$record)):?><p class="notice">Online payments are paused. Your request is saved; contact our team to arrange payment.</p><?php endif;?><?php $testPay=one("SELECT c.payment_id FROM payment_gateway_context c JOIN payments p ON p.id=c.payment_id WHERE p.entity_type=? AND p.entity_id=? AND c.mode='test' AND p.status='success' LIMIT 1",[$type,$id]);if($testPay):?><p class="notice">Test transaction · no real money was collected. Do not fulfil this request as a live payment.</p><?php endif;?><p class="muted small">For support, contact <?=esc(setting('email'))?> with your reference number.</p></div></section><?php });exit;}
if($path==='admin'||str_starts_with($path,'admin/')){header('Cache-Control: private, no-store, max-age=0');admin();require ROOT.'/app/admin.php';exit;}
abort(404,'Page not found');
