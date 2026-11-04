<?php
/** CLI-only gateway status reconciliation. Never initiates refunds. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/core.php';
$rows=all("SELECT id FROM refund_requests WHERE status='processing' AND gateway_refund_id IS NOT NULL ORDER BY updated_at ASC LIMIT 25");
$done=0;$failed=0;
foreach($rows as $row){try{refund_reconcile((int)$row['id']);$done++;}catch(Throwable $e){$failed++;error_log('Refund reconciliation #'.$row['id'].' deferred');}}
$payments=all("SELECT reference FROM payments WHERE status='initialized' AND created_at<DATE_SUB(NOW(),INTERVAL 2 MINUTE) AND created_at>DATE_SUB(NOW(),INTERVAL 14 DAY) ORDER BY id DESC LIMIT 25");
$verified=0;
foreach($payments as $payment){try{if(verify_payment((string)$payment['reference']))$verified++;}catch(Throwable $e){error_log('Payment verification deferred');}}
echo json_encode(['refunds_checked'=>$done,'refunds_failed'=>$failed,'payments_verified'=>$verified]).PHP_EOL;
