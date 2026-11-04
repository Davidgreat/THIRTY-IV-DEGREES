<?php
declare(strict_types=1);
function verify_payment(string $ref):bool {
 if(!preg_match('/^[A-Za-z0-9._=-]{1,80}$/D',$ref))return false;
 $payment=one('SELECT * FROM payments WHERE reference=?',[$ref]);
 if(!$payment)return false;if($payment['status']==='success')return true;
 $context=one('SELECT mode FROM payment_gateway_context WHERE payment_id=?',[$payment['id']]);
 $data=paystack_request('GET','transaction/verify/'.rawurlencode($ref),paystack_payment_key($payment));
 if(!paystack_valid_transaction($payment,$data,$context['mode']??null))return false;
 return paystack_record_success($payment,$data);
}
/** Called only after validating the gateway response, never from browser input. */
function paystack_record_success(array $payment,array $data):bool {
 $changed=false;
 db()->beginTransaction();
 try{
  // Lock the parent first so two different successful references cannot deduct stock twice.
  $kind=$payment['entity_type'];$table=$kind==='order'?'orders':'bookings';
  $record=one('SELECT * FROM '.$table.' WHERE id=? FOR UPDATE',[$payment['entity_id']]);
  if(!$record)throw new RuntimeException('Payment requires manual review: request no longer exists.');
  $locked=one('SELECT * FROM payments WHERE id=? FOR UPDATE',[$payment['id']]);
  if($locked['status']!=='success'){
   $changed=true;
   // Retain only reconciliation fields, not card metadata or customer banking details.
   $summary=array_intersect_key($data,array_flip(['id','reference','status','amount','currency','domain','paid_at']));
   sql("UPDATE payments SET status='success',gateway_response=? WHERE id=?",[json_encode($summary),$payment['id']]);
   if($kind==='order'){
    if(!in_array($record['payment_status'],['paid','refunded'],true)){
     $context=one('SELECT mode FROM payment_gateway_context WHERE payment_id=?',[$payment['id']]);
     if($record['status']!=='cancelled'&&($context['mode']??'live')!=='test'){
      foreach(all('SELECT product_id,quantity FROM order_items WHERE order_id=?',[$record['id']]) as $line){
       if(!$line['product_id'])continue;
       $inv=one('SELECT * FROM product_inventory WHERE product_id=? FOR UPDATE',[$line['product_id']]);
       if($inv&&(int)$inv['track_stock']===1){
        if((int)$inv['stock_qty']<(int)$line['quantity'])audit('payment.stock_review',(string)$record['id']);
        else{sql('UPDATE product_inventory SET stock_qty=stock_qty-? WHERE product_id=?',[$line['quantity'],$line['product_id']]);sql('INSERT INTO inventory_movements(product_id,order_id,delta,reason) VALUES(?,?,?,?)',[$line['product_id'],$record['id'],-(int)$line['quantity'],'Paid order']);}
       }
      }
     }elseif($record['status']==='cancelled')audit('payment.cancelled_order',(string)$record['id']);
     sql('DELETE FROM stock_reservations WHERE order_id=?',[$record['id']]);
     sql("UPDATE orders SET payment_status='paid' WHERE id=?",[$record['id']]);
    }else audit('payment.duplicate_review',(string)$record['id']);
   }else{
    $sum=one("SELECT COALESCE(SUM(amount),0) total FROM payments WHERE entity_type='booking' AND entity_id=? AND status='success'",[$record['id']]);
    $paid=(float)$sum['total'];$quote=(float)$record['quote'];
    if($record['payment_status']!=='refunded'){
     sql('UPDATE bookings SET payment_status=? WHERE id=?',[$quote>0&&$paid>=$quote?'paid':'pending',$record['id']]);
     // Payment never overrides an operator's confirmed/completed/cancelled status.
     if(in_array($record['status'],['quoted','awaiting_deposit'],true))sql("UPDATE bookings SET status='awaiting_deposit' WHERE id=?",[$record['id']]);
    }
   }
  }
  db()->commit();
 }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
 if($changed){$payment['status']='success';run_hook('payment.success',['payment'=>$payment]);}
 return true;
}
