<?php
require __DIR__.'/../app/core.php';
if(cfg()['db_name']!=='thirtyvi_dev')exit('Tests require isolated thirtyvi_dev database.');
$n=0;function check(bool $value,string $label):void{global $n;if(!$value)throw new RuntimeException('FAIL: '.$label);$n++;echo "PASS: $label\n";}
function mock_success(string $ref):void{$s=json_decode(file_get_contents('/tmp/paystack-mock-state.json'),true);$s[$ref]['status']='success';file_put_contents('/tmp/paystack-mock-state.json',json_encode($s));}
function order_fixture(?int $uid=2):int{
 sql("INSERT INTO orders(reference,user_id,customer_name,email,phone,fulfillment,subtotal,total) VALUES(?,?,?,'customer@example.test','08012345678','pickup',8500,8500)",['ORD-'.strtoupper(bin2hex(random_bytes(5))),$uid,'Test Customer']);$id=(int)db()->lastInsertId();
 sql("INSERT INTO order_items(order_id,product_id,product_name,unit_price,quantity) VALUES(?,2,'Jollof',8500,1)",[$id]);return $id;
}
$_SESSION['uid']=1;
$input=['paystack_mode'=>'test','paystack_enabled'=>'1','paystack_test_public'=>'pk_test_'.str_repeat('a',24),'paystack_test_secret'=>'sk_test_'.str_repeat('a',24),'paystack_live_public'=>'pk_live_'.str_repeat('b',24),'paystack_live_secret'=>'sk_live_'.str_repeat('b',24)];
paystack_save_settings($input);
check(str_starts_with(one('SELECT value FROM settings WHERE `key`=?',['paystack_test_secret'])['value'],'enc:'),'Secret encrypted at rest');
check(!str_contains(one('SELECT value FROM settings WHERE `key`=?',['paystack_test_secret'])['value'],$input['paystack_test_secret']),'Secret not stored as plaintext');
try{paystack_save_settings(['paystack_mode'=>'test','paystack_test_secret'=>'sk_live_'.str_repeat('z',24)]);check(false,'Reject mixed credentials');}catch(InvalidArgumentException $e){check(true,'Reject mixed credentials');}
check(paystack_safe_checkout('https://checkout.paystack.com/example'),'Accept hosted checkout');
foreach(['https://checkout.paystack.com.evil.test/','javascript:alert(1)','http://checkout.paystack.com/','https://evil@checkout.paystack.com/'] as $url)check(!paystack_safe_checkout($url),'Reject unsafe checkout URL');
$p=['reference'=>'PAY-TEST','currency'=>'NGN','amount'=>'8500.00'];$d=['reference'=>'PAY-TEST','currency'=>'NGN','amount'=>850000,'domain'=>'test','status'=>'success'];
check(paystack_valid_transaction($p,$d,'test'),'Matching gateway transaction accepted');
foreach(['reference'=>'wrong','currency'=>'USD','amount'=>850001,'domain'=>'live','status'=>'pending'] as $field=>$value){$bad=$d;$bad[$field]=$value;check(!paystack_valid_transaction($p,$bad,'test'),'Reject mismatched '.$field);}
sql('INSERT INTO product_inventory(product_id,track_stock,stock_qty) VALUES(2,1,100) ON DUPLICATE KEY UPDATE stock_qty=100,track_stock=1');
$id=order_fixture();$url=paystack_checkout('order',$id);$payment=one("SELECT * FROM payments WHERE entity_type='order' AND entity_id=?",[$id]);
check(paystack_safe_checkout($url),'Initialize checkout through TLS mock gateway');
check(paystack_checkout('order',$id)===$url,'Retry reuses existing checkout');
check((int)one('SELECT COUNT(*) n FROM payments WHERE entity_type=? AND entity_id=?',['order',$id])['n']===1,'No duplicate payment record');
mock_success($payment['reference']);check(verify_payment($payment['reference']),'Server verifies Paystack response');
check(one('SELECT payment_status FROM orders WHERE id=?',[$id])['payment_status']==='paid','Order marked paid');
check((int)one('SELECT stock_qty FROM product_inventory WHERE product_id=2')['stock_qty']===100,'Test payment does not consume real stock');
check(verify_payment($payment['reference']),'Repeated verification idempotent');
check((int)one('SELECT COUNT(*) n FROM email_queue WHERE event_key=?',['payment:success:'.$payment['id']])['n']===1,'Exactly one payment notification queued');
// Change mode and keys in storage directly to avoid request-scoped setting cache.
save_integration('paystack_mode','live');
$context=one('SELECT * FROM payment_gateway_context WHERE payment_id=?',[$payment['id']]);check($context['mode']==='test','Transaction retains original environment');
check(paystack_payment_key($payment)===$input['paystack_test_secret'],'Historical payment retains original secret');
$raw=json_encode(['event'=>'charge.success','data'=>['reference'=>$payment['reference']]]);$event=json_decode($raw,true);
check(paystack_webhook_key($raw,hash_hmac('sha512',$raw,$input['paystack_test_secret']),$event)!=='','Signed webhook accepted');
check(paystack_webhook_key($raw,str_repeat('0',128),$event)==='','Invalid webhook rejected');
foreach(['cancelled','completed'] as $status)check(!paystack_can_pay('order',['status'=>$status,'payment_status'=>'unpaid']),'Reject '.$status.' order');
check(!paystack_can_pay('order',['status'=>'pending','payment_status'=>'refunded']),'Reject refunded order');
// Apply the real finalizer twice to a synthetic live response, including concurrent-reference safety.
$id2=order_fixture();sql("INSERT INTO payments(reference,entity_type,entity_id,amount) VALUES(?,'order',?,8500)",['PAY-'.bin2hex(random_bytes(5)),$id2]);$pid=(int)db()->lastInsertId();$p2=one('SELECT * FROM payments WHERE id=?',[$pid]);
sql("INSERT INTO payment_gateway_context(payment_id,mode,secret_cipher) VALUES(?,'live',?)",[$pid,seal_secret($input['paystack_live_secret'])]);
$gateway=['reference'=>$p2['reference'],'currency'=>'NGN','amount'=>850000,'domain'=>'live','status'=>'success'];
paystack_record_success($p2,$gateway);paystack_record_success($p2,$gateway);
check((int)one('SELECT stock_qty FROM product_inventory WHERE product_id=2')['stock_qty']===99,'Live stock deducted exactly once');
// Fully paid confirmed booking must remain confirmed.
sql("INSERT INTO bookings(reference,user_id,name,email,phone,kind,event_date,guests,location,quote,deposit,status) VALUES(?,2,'Test','customer@example.test','08012345678','chef',DATE_ADD(CURDATE(),INTERVAL 2 DAY),2,'Test',10000,5000,'confirmed')",['EVT-'.bin2hex(random_bytes(5))]);$bid=(int)db()->lastInsertId();
sql("INSERT INTO payments(reference,entity_type,entity_id,amount) VALUES(?,'booking',?,10000)",['PAY-'.bin2hex(random_bytes(5)),$bid]);$bp=one('SELECT * FROM payments WHERE id=?',[(int)db()->lastInsertId()]);
paystack_record_success($bp,['status'=>'success']);$b=one('SELECT * FROM bookings WHERE id=?',[$bid]);
check($b['status']==='confirmed'&&$b['payment_status']==='paid','Confirmed booking payment status updated without regression');
// Account recovery: hashed, expiring and single-use tokens.
account_reset_request('customer@example.test');$mail=one("SELECT body FROM email_queue WHERE event_key LIKE 'password:reset:%' ORDER BY id DESC LIMIT 1");preg_match('/token=([a-f0-9]{64})/',$mail['body'],$m);$token=$m[1];
check(one('SELECT token_hash FROM account_reset_tokens WHERE user_id=2')['token_hash']===hash('sha256',$token),'Reset token stored hashed');
check(account_reset_password($token,'New-test-password-600'),'Password reset succeeds');
check(!account_reset_password($token,'Another-password-600'),'Reset token cannot be reused');
sql('UPDATE users SET password=? WHERE id=2',[password_hash('Test-password-600',PASSWORD_DEFAULT)]);
// Restore test mode and paused payments for screenshots and unrelated smoke tests.
save_integration('paystack_mode','test');save_integration('paystack_enabled','0');
echo "TOTAL: $n passed\n";
