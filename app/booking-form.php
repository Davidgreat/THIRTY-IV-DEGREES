<?php
$kind=$path==='private-chef'?'chef':'catering';$isChef=$kind==='chef';
layout($isChef?'Private Chef':'Catering & Events',function()use($kind,$isChef){
 $available=!$isChef||setting('chef_available','1')==='1';$account=user();
?>
<section class="section container vi-service-booking">
 <header class="booking-heading"><span class="eyebrow">36° / EXPERIENCES</span><h1><?=$isChef?'Your table. Your own chef.':'An occasion to remember.'?></h1><p><?=$isChef?'A personal dining experience, planned around you.':'Thoughtful catering, from the first guest to the last plate.'?></p><?php if($isChef):vi_chef_badge();endif;?></header>
 <div class="booking-layout">
 <aside class="booking-intro"><img src="/public/assets/banners/<?=$isChef?'private-chef':'gifting'?>.webp" alt="<?=$isChef?'A private dining experience':'Catering for special occasions'?>" loading="eager"><div><span class="eyebrow">THE EXPERIENCE</span><h2><?=$isChef?'Less planning. More presence.':'Good company. Great food.'?></h2><p>Tell us the essentials. We will review your request, confirm availability and send your quotation.</p><ol><li>Share your event details</li><li>Receive a tailored quotation</li><li>Confirm with a secure deposit</li></ol><small>No payment is taken with this request.</small></div></aside>
 <div class="booking-workspace">
 <?php if($isChef):?><div class="notice" role="status" data-chef-pause-notice <?=$available?'hidden':''?>><strong>Private chef inquiries are paused.</strong> Please check back soon, or <a href="/catering">explore catering</a>.</div><?php endif;?>
 <form class="form compact-booking" method="post" action="/booking/create" id="vi-service-booking-form" data-booking-wizard data-available="<?=$available?'1':'0'?>">
 <?=token()?><input type="hidden" name="kind" value="<?=$kind?>">
 <div class="booking-stepper" aria-label="Booking progress" data-booking-controls hidden><button type="button" data-booking-goto="0" aria-current="step"><span>1</span> Your event</button><button type="button" data-booking-goto="1"><span>2</span> Your details</button></div>
 <p class="booking-step-note" data-booking-step-note hidden role="status" aria-live="polite">Step 1 of 2 · Your event</p>
 <fieldset class="booking-step" data-booking-step="0" <?=!$available?'disabled':''?>><legend>Your event</legend><div class="booking-fields">
 <div><label for="vi-book-type">Occasion</label><select id="vi-book-type" name="event_type"><option>Private dining</option><option>Birthday</option><option>Anniversary</option><option>Corporate event</option><option>Other occasion</option></select></div>
 <div><label for="vi-book-guests">Guests</label><input id="vi-book-guests" name="guests" type="number" min="1" max="10000" value="2" required inputmode="numeric"></div>
 <div><label for="vi-book-date">Date</label><input id="vi-book-date" name="event_date" type="date" required min="<?=date('Y-m-d')?>"></div>
 <div><label for="vi-book-time">Time</label><input id="vi-book-time" name="event_time" type="time" required <?=$isChef?'min="'.esc(setting('booking_start','09:00')).'" max="'.esc(setting('booking_end','22:00')).'"':''?>></div>
 <div class="booking-wide"><label for="vi-book-location">Event location</label><textarea id="vi-book-location" name="location" rows="2" maxlength="500" required placeholder="Venue or address, city"></textarea></div>
 </div><p class="booking-availability" id="vi-book-availability" role="status" aria-live="polite">Choose a date and time to check availability.</p>
 <details class="booking-preferences"><summary>Menu preferences or special requests <small>optional</small></summary><label class="sr-only" for="vi-book-details">Menu preferences and special requests</label><textarea id="vi-book-details" name="details" rows="2" maxlength="4000" placeholder="Dietary needs, favourite dishes or anything we should know"></textarea></details>
 </fieldset>
 <fieldset class="booking-step" data-booking-step="1" <?=!$available?'disabled':''?>><legend>Your details</legend><div class="booking-fields">
 <div class="booking-wide"><label for="vi-book-name">Full name</label><input id="vi-book-name" name="name" required maxlength="150" autocomplete="name" placeholder="Your name" value="<?=esc($account['name']??'')?>"></div>
 <div class="booking-wide"><label for="vi-book-email">Email</label><input id="vi-book-email" name="email" type="email" required maxlength="190" autocomplete="email" placeholder="you@example.com" value="<?=esc($account['email']??'')?>"></div>
 <div><label for="vi-book-phone">Phone</label><input id="vi-book-phone" name="phone" type="tel" required maxlength="60" autocomplete="tel" placeholder="Phone number"></div>
 <div><label for="vi-book-budget">Budget <small>optional</small></label><input id="vi-book-budget" name="budget" maxlength="80" placeholder="Estimated budget"></div>
 </div><div class="booking-review" data-booking-review hidden><strong>Your request at a glance</strong><p data-booking-summary></p><button class="text-link" type="button" data-booking-goto="0">Edit event details</button></div>
 <p class="muted small">Your request is an inquiry. Our team will confirm the details before any deposit is requested.</p>
 </fieldset>
 <div class="booking-navigation"><button class="btn" type="button" data-booking-back hidden>← Back</button><button class="btn gold" type="button" data-booking-next hidden <?=$available?'':'disabled'?>>Continue →</button><button type="submit" class="btn gold" data-booking-submit <?=$available?'':'disabled'?>><?=$available?'Send booking request →':'Bookings paused'?></button></div>
 </form>
 </div></div>
</section>
<?php });
