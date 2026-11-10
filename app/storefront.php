<?php
layout('Exceptional Dining',function(){
 $featured=all('SELECT p.*,c.name category FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.available=1 AND p.featured=1 ORDER BY p.id DESC LIMIT 4');
 $slides=json_decode(setting('hero_slides','[]'),true)?:[];
 $slides=array_values(array_filter($slides,static fn($s)=>is_array($s)&&!empty($s['url'])));
 if(!$slides)$slides=[['type'=>'image','url'=>'/public/assets/banners/signature.webp','title'=>'Food worth gathering for.','subtitle'=>'Thoughtful meals. Private dining. Memorable occasions.']];
?>
<section class="home-hero container" data-hero-slider aria-label="Featured dining experiences">
 <div class="home-hero-media" aria-hidden="true">
 <?php foreach($slides as $i=>$slide):?><div class="home-slide <?=str_starts_with($slide['url'],'/public/assets/banners/')?'home-slide-artwork':''?> <?=$i===0?'active':''?>" data-slide><?php if(($slide['type']??'')==='video'):?><video muted loop playsinline preload="metadata"><source src="<?=esc($slide['url'])?>" type="video/mp4"></video><?php else:?><img src="<?=esc($slide['url'])?>" alt="" loading="<?=$i===0?'eager':'lazy'?>"><?php endif;?></div><?php endforeach;?>
 </div>
 <script>window.thirtyviSlides=<?=json_encode($slides,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;</script>
 <div class="home-hero-copy"><span class="eyebrow">36° / FOOD & PRIVATE DINING</span><h1 data-hero-title><span>Food worth</span><br><em data-hero-phrase>gathering for.</em></h1><button type="button" class="hero-motion-toggle" data-hero-motion hidden aria-pressed="false">Pause words <span aria-hidden="true">Ⅱ</span></button><p data-hero-subtitle><?=esc(($slides[0]['subtitle']??'')?:setting('hero_description'))?></p><div class="home-hero-actions"><a class="btn gold" href="/menu">Order food <span aria-hidden="true">↗</span></a><a class="btn home-secondary" href="/private-chef">Book a private chef</a></div><a class="home-chef-status" href="/private-chef"><?php vi_chef_badge();?></a></div>
 <?php if(count($slides)>1):?><div class="home-slider-controls"><button type="button" data-prev aria-label="Previous feature">←</button><span data-counter>01 / <?=str_pad((string)count($slides),2,'0',STR_PAD_LEFT)?></span><button type="button" data-next aria-label="Next feature">→</button></div><?php endif;?>
</section>
<div class="container home-utility"><a href="/track">Track an order <span aria-hidden="true">→</span></a><a href="/shop">Visit the 36° Store <span aria-hidden="true">↗</span></a></div>
<?php vi_banners('home_top');?>
<section class="section container home-experiences" aria-labelledby="experience-heading">
 <div class="compact-section-title"><div><span class="eyebrow">MAKE IT YOURS</span><h2 id="experience-heading">What brings you to the table?</h2></div><p>Choose your experience.</p></div>
 <div class="experience-tabs" role="tablist" aria-label="Choose an experience" data-experience-tabs hidden>
 <?php foreach(['meals'=>'Everyday meals','chef'=>'Private chef','events'=>'Catering'] as $key=>$label):?><button type="button" id="tab-<?=$key?>" role="tab" aria-controls="experience-<?=$key?>" aria-selected="<?=$key==='meals'?'true':'false'?>" data-experience-choice="<?=$key?>"><?=$label?></button><?php endforeach;?>
 </div>
 <div class="experience-panels">
 <?php $experiences=[
 'meals'=>['A good day starts with good food.','Freshly prepared favourites, made for your everyday cravings.','/menu','Explore the menu','signature.webp','01 / EVERYDAY'],
 'chef'=>['Your occasion. Your own chef.','A personal menu and thoughtful service, in the comfort of your space.','/private-chef','Plan your private dinner','private-chef.webp','02 / PERSONAL'],
 'events'=>['Bring everyone together.','From intimate gatherings to celebrations, let us take care of the food.','/catering','Discuss your event','gifting.webp','03 / CELEBRATE']];
 foreach($experiences as $key=>$x):?><article class="experience-panel" id="experience-<?=$key?>" data-experience-panel="<?=$key?>"><div class="experience-copy"><span class="eyebrow"><?=$x[5]?></span><h3><?=$x[0]?></h3><p><?=$x[1]?></p><a class="text-link" href="<?=$x[2]?>"><?=$x[3]?> →</a></div><div class="experience-photo"><img src="/public/assets/banners/<?=$x[4]?>" alt="<?=esc($x[0])?>" loading="lazy"></div></article><?php endforeach;?>
 </div>
</section>
<section class="section container home-favourites" aria-labelledby="favourites-heading"><div class="compact-section-title"><div><span class="eyebrow">FROM OUR KITCHEN</span><h2 id="favourites-heading">Worth coming back for.</h2></div><a class="text-link" href="/menu">Full menu →</a></div><div class="grid food-grid home-featured-grid"><?php foreach($featured as $p)card($p);?></div><?php if(!$featured):?><p class="muted">Our menu is being prepared. <a href="/menu">Browse available meals →</a></p><?php endif;?></section>
<?php vi_banners('home_mid');?>
<section class="container hospitality-note" aria-label="A note from our kitchen"><span class="eyebrow">A LITTLE MORE PERSONAL</span><p>Some meals fill a table.<br><em>The best ones bring it to life.</em></p><div><span class="hospitality-seal" aria-hidden="true">36°</span><span>Made with care.<br>Shared with good company.</span></div></section><section class="container home-story"><span class="eyebrow">THE 36° SIGNATURE</span><div><h2>Good food. Considered hospitality.</h2><p>Beyond the plate, we create moments that feel personal.</p></div><a class="text-link" href="/about">Our story →</a></section>
<?php });
