<?php
$u=require_login();layout('My Account',function()use($u){
 $orders=all('SELECT * FROM orders WHERE user_id=? ORDER BY id DESC',[$u['id']]);
 $bookings=all('SELECT * FROM bookings WHERE user_id=? ORDER BY id DESC',[$u['id']]);
 $active=0;$spent=0;foreach($orders as $o){if(!in_array($o['status'],['completed','cancelled'],true))$active++;if($o['payment_status']==='paid')$spent+=(float)$o['total'];}
?>
<section class="section container vi-customer-dash">
 <header class="customer-head"><div><span class="eyebrow">YOUR 36° SPACE</span><h1>Welcome back, <?=esc($u['name'])?>.</h1><p>Orders, occasions and favourite dishes. All together.</p></div><a class="btn gold" href="/menu">Order food ↗</a></header>
 <div class="customer-metrics"><div><small>Orders</small><strong><?=count($orders)?></strong></div><div><small>In progress</small><strong><?=$active?></strong></div><div><small>Bookings</small><strong><?=count($bookings)?></strong></div><div><small>Paid order value</small><strong><?=money($spent)?></strong></div></div>
 <nav class="customer-shortcuts" aria-label="Quick actions"><a href="/track">Track an order →</a><a href="/private-chef">Plan a private dinner →</a><a href="/catering">Arrange catering →</a></nav>
 <div class="customer-workspace">
 <div class="customer-tabs" role="tablist" aria-label="Account activity" data-account-tabs hidden><?php foreach(['orders'=>'Orders','bookings'=>'Bookings','favourites'=>'Favourites'] as $key=>$label):?><button id="account-tab-<?=$key?>" type="button" role="tab" aria-controls="account-panel-<?=$key?>" aria-selected="<?=$key==='orders'?'true':'false'?>" data-account-tab="<?=$key?>"><?=$label?><?php if($key!=='favourites'):?> <small><?=count($key==='orders'?$orders:$bookings)?></small><?php endif;?></button><?php endforeach;?></div>
 <section id="account-panel-orders" data-account-panel="orders" aria-labelledby="account-orders-heading"><div class="compact-section-title"><h2 id="account-orders-heading">Your orders</h2><a href="/menu">Browse menu →</a></div>
 <?php if(!$orders):?><div class="compact-empty"><strong>Your next favourite is waiting.</strong><p>Choose a meal and your orders will appear here.</p><a class="text-link" href="/menu">Explore the menu →</a></div><?php endif;?>
 <?php foreach(array_slice($orders,0,4) as $o):?><article class="customer-order"><a href="/order/<?=(int)$o['id']?>"><span><strong><?=esc($o['reference'])?></strong><small><?=esc(date('j M Y',strtotime($o['created_at'])))?> · <?=esc(ucfirst($o['fulfillment']))?></small></span><span><b><?=money($o['total'])?></b><small><?=esc(ucfirst($o['status']))?> · <?=esc(ucfirst($o['payment_status']))?></small></span></a><form method="post" action="/account/reorder"><?=token()?><input type="hidden" name="order_id" value="<?=(int)$o['id']?>"><button class="text-link" type="submit">Reorder available items ↻</button></form></article><?php endforeach;?>
 <?php if(count($orders)>4):?><details class="customer-history"><summary>Earlier orders (<?=count($orders)-4?>)</summary><?php foreach(array_slice($orders,4) as $o):?><a href="/order/<?=(int)$o['id']?>"><?=esc($o['reference'])?> · <?=money($o['total'])?> →</a><?php endforeach;?></details><?php endif;?>
 </section>
 <section id="account-panel-bookings" data-account-panel="bookings" aria-labelledby="account-bookings-heading"><div class="compact-section-title"><h2 id="account-bookings-heading">Your occasions</h2><a href="/private-chef">New booking →</a></div>
 <?php if(!$bookings):?><div class="compact-empty"><strong>Make a little occasion of it.</strong><p>Private chef and catering inquiries will appear here.</p><a class="text-link" href="/private-chef">Plan your experience →</a></div><?php endif;?>
 <?php foreach($bookings as $b):?><a class="customer-booking" href="/booking/<?=(int)$b['id']?>"><span><strong><?=esc($b['reference'])?></strong><small><?=esc($b['kind']==='chef'?'Private chef':'Catering')?> · <?=esc($b['event_date'])?></small></span><span class="status-pill"><?=esc(ucwords(str_replace('_',' ',$b['status'])))?></span></a><?php endforeach;?>
 </section>
 <section id="account-panel-favourites" data-account-panel="favourites" aria-labelledby="account-favourites-heading"><div class="compact-section-title"><h2 id="account-favourites-heading">Saved favourites</h2><a href="/menu">Find more →</a></div><p class="muted small">Meals saved on this device, ready whenever you are.</p><div class="vi-dash-favourites" data-favourites-panel><p class="muted">Loading saved meals…</p></div></section>
 </div>
 <details class="customer-review"><summary>How was your experience? Leave a review.</summary><form method="post" action="/review" class="form"><?=token()?><label for="customer-rating">Rating</label><select id="customer-rating" name="stars"><option value="5">★★★★★</option><option value="4">★★★★</option><option value="3">★★★</option><option value="2">★★</option><option value="1">★</option></select><label for="customer-review-text">Your experience</label><textarea id="customer-review-text" name="body" required minlength="10" rows="3"></textarea><button class="btn gold">Submit review</button></form></details>
</section>
<?php });
