<?php
declare(strict_types=1);
function paystack_refund_api(string $method,string $path,?array $payload=null,?array $payment=null):array{
 $key=$payment?paystack_payment_key($payment):paystack_key(paystack_mode());
 return paystack_request($method,$path,$key,$payload);
}
function refund_gateway_status(string $gatewayStatus):string{
 return match(strtolower($gatewayStatus)){'processed'=>'completed','failed','reversed'=>'failed','pending','processing'=>'processing',default=>'processing'};
}
function refund_reconcile(int $id):void{
 $r=one('SELECT * FROM refund_requests WHERE id=?',[$id]);if(!$r||!$r['gateway_refund_id'])throw new RuntimeException('Refund not submitted to Paystack');
 $payment=one('SELECT * FROM payments WHERE id=?',[$r['payment_id']]);
 $data=paystack_refund_api('GET','refund/'.rawurlencode((string)$r['gateway_refund_id']),null,$payment);
 $state=refund_gateway_status((string)($data['status']??'pending'));
 sql("UPDATE refund_requests SET status=?,gateway_status=?,updated_at=NOW() WHERE id=? AND status NOT IN ('completed','rejected')",[$state,(string)($data['status']??'pending'),$id]);
 if($state==='completed')refund_recompute_entity((int)$r['payment_id']);
}
function refund_recompute_entity(int $paymentId):void{
 $pay=one('SELECT * FROM payments WHERE id=?',[$paymentId]);if(!$pay)return;
 $total=one("SELECT COALESCE(SUM(r.amount),0) amount FROM refund_requests r JOIN payments p ON p.id=r.payment_id WHERE p.entity_type=? AND p.entity_id=? AND r.status='completed'",[$pay['entity_type'],$pay['entity_id']]);
 $received=one("SELECT COALESCE(SUM(amount),0) amount FROM payments WHERE entity_type=? AND entity_id=? AND status='success'",[$pay['entity_type'],$pay['entity_id']]);
 if((float)$received['amount']>0&&(float)$total['amount']>=(float)$received['amount'])sql('UPDATE '.($pay['entity_type']==='order'?'orders':'bookings')." SET payment_status='refunded' WHERE id=?",[$pay['entity_id']]);
}
