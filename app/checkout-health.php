<?php
admin_page('Checkout diagnostics',function(){
 $requirements=[
 'orders'=>['id','reference','user_id','customer_name','email','phone','address','fulfillment','notes','subtotal','delivery_fee','total','status','payment_status'],
 'order_items'=>['order_id','product_id','product_name','unit_price','quantity','notes','customizations'],
 'order_status_events'=>['order_id','status','actor_id'],
 'order_schedules'=>['order_id','requested_for'],
 'order_gifts'=>['order_id','recipient_name','recipient_phone','recipient_address','gift_message','anonymous_sender'],
 'product_inventory'=>['product_id','track_stock','stock_qty'],
 'stock_reservations'=>['product_id','order_id','quantity','expires_at'],
 'payments'=>['id','reference','entity_type','entity_id','amount','currency','status','gateway_response'],
 'payment_gateway_context'=>['payment_id','mode','secret_cipher','authorization_url'],
 'request_rate_limits'=>['bucket','hits','expires_at']];
 $columns=all('SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()');$actual=[];
 foreach($columns as $column)$actual[$column['TABLE_NAME']][]=$column['COLUMN_NAME'];
 ?><p class="muted">This read-only report checks the deployed checkout path and database structure. It does not charge cards or display credentials.</p>
 <section class="admin-panel"><h3>Endpoint and runtime</h3><p>Expected checkout build: <strong>6.1.2</strong></p><p><a href="/public/checkout.php?probe=1" target="_blank" rel="noopener">Open the public endpoint probe →</a> — it should show JSON with build 6.1.2, not redirect to checkout.</p><p>PHP <?=esc(PHP_VERSION)?> · cURL <?=function_exists('curl_init')?'available':'MISSING'?> · OpenSSL <?=function_exists('openssl_encrypt')?'available':'MISSING'?></p><p>Payments: <?=paystack_enabled()?'enabled':'paused'?> · <?=esc(paystack_mode())?> mode</p></section>
 <section class="admin-panel"><h3>Required database structure</h3><div class="scroll"><table><tr><th>Table</th><th>Result</th></tr><?php foreach($requirements as $table=>$needed):$missing=array_diff($needed,$actual[$table]??[]);?><tr><td><?=esc($table)?></td><td><?=$missing?'Missing: '.esc(implode(', ',$missing)):'Available'?></td></tr><?php endforeach;?></table></div><p class="small muted">Missing fields can prevent an order from being created before Paystack is contacted. This report does not alter your database.</p></section>
 <section class="admin-panel"><h3>Recent checkout stages</h3><p class="muted small">Match the reference shown on the failed checkout. Entries contain stages and numeric error codes only.</p><div class="scroll"><table><tr><th>Time (UTC)</th><th>Reference</th><th>Stage</th><th>Codes / order</th></tr><?php
 $file=ROOT.'/storage/.checkout-trace.jsonl';$lines=is_readable($file)?file($file,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES):[];
 foreach(array_reverse(array_slice($lines?:[],-40)) as $line){$row=json_decode($line,true);if(!is_array($row))continue;?><tr><td><?=esc((string)($row['time']??''))?></td><td><?=esc((string)($row['request_id']??''))?></td><td><?=esc((string)($row['stage']??''))?></td><td><?php foreach(['http_status','database_code','sqlstate','order_id'] as $key)if(isset($row[$key]))echo esc($key.': '.$row[$key]).'<br>';?></td></tr><?php }?></table></div><?php if(!$lines):?><p>No endpoint checkout attempts recorded yet. If pressing payment still creates no entries, the request is not reaching this endpoint or the trace file is not writable.</p><?php endif;?></section>
 <?php
});
