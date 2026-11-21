<?php
declare(strict_types=1);
function construction_enabled():bool{return mail_setting('construction_enabled','1')==='1';}
function construction_preview_valid():bool{
 $grant=$_SESSION['construction_preview']??[];$hash=mail_setting('construction_preview_hash');
 return $hash!==''&&is_array($grant)&&(int)($grant['expires']??0)>time()&&hash_equals(hash('sha256',$hash.mail_setting('construction_revision')),(string)($grant['stamp']??''));
}
function construction_access():bool{return is_admin()||construction_preview_valid();}
function construction_gate(string $path):void{
 if($path==='preview/leave'&&post()){check_csrf();unset($_SESSION['construction_preview']);session_regenerate_id(true);go('');}
 if($path==='preview'&&post()){
  check_csrf();rate_limit('site_preview',8,900);$code=$_POST['preview_code']??'';$hash=mail_setting('construction_preview_hash');
  if(!is_string($code)||strlen($code)>72||$hash===''||str_contains($code,"\0")||!password_verify($code,$hash)){
   $_SESSION['construction_notice']='That preview code is incorrect. Please try again.';go('preview');
  }
  session_regenerate_id(true);$_SESSION['construction_preview']=['expires'=>time()+8*3600,'stamp'=>hash('sha256',$hash.mail_setting('construction_revision'))];go('');
 }
 if(!construction_enabled()){if($path==='preview')go('');return;}
 // Keep the card public. Existing payment confirmations must still reconcile funds.
 if(in_array($path,['digital-card','digital-card/contact.vcf'],true)&&!post())return;
 header('Cache-Control: private, no-store, max-age=0');header('X-Robots-Tag: noindex, nofollow');
 if(in_array($path,['login','logout','forgot-password','reset-password'],true)||$path==='admin'||str_starts_with($path,'admin/')||($path==='payment/webhook'&&post())||($path==='payment/verify'&&!post()))return;
 if($path==='preview'&&!post()){construction_page(true);}
 if(construction_access())return;
 construction_page(false);
}
function construction_page(bool $preview):never{
 http_response_code($preview?200:503);if(!$preview)header('Retry-After: 3600');header('Cache-Control: private, no-store, max-age=0');header('X-Robots-Tag: noindex, nofollow');
 $notice=$_SESSION['construction_notice']??'';unset($_SESSION['construction_notice']);$card=digital_card_data();$title=mail_setting('construction_title','Something delicious is on the way.');$message=mail_setting('construction_message','We’re preparing a new culinary experience. In the meantime, meet Chef Tosin and explore our services.');
 ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#131b15"><title><?=esc($card['brand'])?> · Under construction</title><link rel="stylesheet" href="/public/assets/construction-671.css?v=6.7.1"></head><body><main class="build-card"><a class="build-logo" href="/digital-card"><img src="<?=esc($card['logo'])?>" alt="<?=esc($card['brand'])?>"></a><span class="build-eyebrow">A LITTLE PREPARATION. SOMETHING SPECIAL.</span><p class="build-status"><i aria-hidden="true"></i> Website under construction</p><h1><?=esc($title)?></h1><p class="build-message"><?=nl2br(esc($message))?></p><?php if($card['enabled']):?><a class="build-primary" href="/digital-card">Meet the chef · Digital card <span aria-hidden="true">→</span></a><?php endif;?><details class="build-preview" <?=$preview?'open':''?>><summary>Have a private preview code?</summary><?php if($notice):?><p role="alert" class="build-error"><?=esc($notice)?></p><?php endif;?><form method="post" action="/preview"><?=token()?><label for="preview-code">Preview access code</label><input id="preview-code" name="preview_code" type="password" required maxlength="72" autocomplete="off" spellcheck="false" autocapitalize="off"><button type="submit">Enter private preview →</button></form><p>Your code opens the site in this browser for eight hours. It does not sign you in or grant admin access.</p></details><?php if(construction_access()):?><a class="build-secondary" href="/">Continue to website →</a><?php if(construction_preview_valid()):?><form class="build-leave" method="post" action="/preview/leave"><?=token()?><button>End this browser’s preview</button></form><?php endif;endif;?><footer><span><?=esc($card['brand'])?></span><a href="/login">Admin sign in</a></footer></main></body></html><?php exit;
}
function construction_save(string $action):never{
 if(admin()['role']!=='admin')abort(403,'Owner access required');
 if($action==='code'){
  $code=strtoupper(bin2hex(random_bytes(8)));db()->beginTransaction();try{mail_put('construction_preview_hash',password_hash($code,PASSWORD_DEFAULT));mail_put('construction_revision',bin2hex(random_bytes(16)));db()->commit();}catch(Throwable $error){if(db()->inTransaction())db()->rollBack();flash('Preview code could not be generated. Please try again.','error');go('admin/construction');}
  $_SESSION['construction_generated_code']=$code;audit('construction.code.rotated');flash('New preview code generated. Earlier preview sessions have been revoked.');go('admin/construction');
 }
 $title=str('title',150);$message=str('message',800);$code=$_POST['new_preview_code']??'';
 if($title===''||!is_string($code)||($code!==''&&(strlen($code)<8||strlen($code)>72||str_contains($code,"\0")))){flash('Enter a title and, if changing it, a preview code of 8–72 bytes.','error');go('admin/construction');}
 db()->beginTransaction();try{
  mail_put('construction_enabled',isset($_POST['enabled'])?'1':'0');mail_put('construction_title',$title);mail_put('construction_message',$message);mail_put('construction_revision',bin2hex(random_bytes(16)));
  if($code!=='')mail_put('construction_preview_hash',password_hash($code,PASSWORD_DEFAULT));db()->commit();
 }catch(Throwable $error){if(db()->inTransaction())db()->rollBack();flash('Construction settings could not be saved. Please try again.','error');go('admin/construction');}
 audit('construction.settings');flash(isset($_POST['enabled'])?'Under-construction mode enabled. The digital card and admin access remain available.':'Under-construction mode disabled. The website is now live.');go('admin/construction');
}
function construction_admin():void{
 if(admin()['role']!=='admin')abort(403,'Owner access required');header('Cache-Control: private, no-store');
 admin_page('Under construction & preview',function(){ $enabled=construction_enabled();$code=$_SESSION['construction_generated_code']??'';unset($_SESSION['construction_generated_code']);?>
 <section class="form"><h2><?=$enabled?'The main website is under construction.':'The website is live.'?></h2><p>The digital card stays public. Admin login, dashboards and password recovery remain available. Existing Paystack callbacks and signed webhooks can still verify payments. Admin and manager sessions can browse the website normally; other visitors need the preview code.</p><a class="btn" href="/preview" target="_blank" rel="noopener">View holding page ↗</a> <a class="btn" href="/digital-card" target="_blank" rel="noopener">Open digital card ↗</a></section>
 <form class="form" method="post" action="/admin/construction/save"><?=token()?><label><input type="checkbox" name="enabled" <?=$enabled?'checked':''?>> Keep the main website under construction</label><p class="muted">Uncheck and save to make the site live. This update starts with construction mode enabled.</p><label for="construction-title">Page headline</label><input id="construction-title" name="title" maxlength="150" required value="<?=esc(mail_setting('construction_title','Something delicious is on the way.'))?>"><label for="construction-message">Page message</label><textarea id="construction-message" name="message" maxlength="800" rows="4"><?=esc(mail_setting('construction_message','We’re preparing a new culinary experience. In the meantime, meet Chef Tosin and explore our services.'))?></textarea><h3>Private preview access</h3><p><?=mail_setting('construction_preview_hash')!==''?'A preview code is configured. It is stored as a password hash and cannot be displayed.':'No preview code yet. Set one below or generate a random code.'?></p><label for="new-preview-code">Set a new preview code (optional, 8–72 bytes)</label><input id="new-preview-code" name="new_preview_code" type="password" autocomplete="new-password" maxlength="72"><p class="muted">Leave blank to keep the code. Share it privately. Visitors enter it at /preview or on the holding page. Saving these settings or rotating the code revokes earlier preview sessions. A code never grants admin permissions.</p><button class="btn gold">Save website access settings</button></form>
 <section class="form"><h2>Generate a preview code</h2><?php if($code):?><p>Copy this code now; it is shown only once:</p><input id="generated-preview-code" readonly value="<?=esc($code)?>"><button type="button" class="btn" data-copy-target="generated-preview-code">Copy code</button><?php endif;?><form method="post" action="/admin/construction/code"><?=token()?><button class="btn">Generate new preview code</button></form></section><?php });
}
function construction_preview_banner(bool $adminLayout):void{
 if($adminLayout||!construction_enabled()||!construction_access())return;
 ?><aside style="padding:10px 20px;background:#e7c780;color:#172219;font:12px/1.5 Arial,sans-serif;display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:center" aria-label="Private preview"><strong>Private preview · Public website is under construction</strong><a href="/preview" style="color:inherit;text-decoration:underline">Preview access</a><?php if(is_admin()):?><a href="/admin/construction" style="color:inherit;text-decoration:underline">Website access settings</a><?php else:?><form method="post" action="/preview/leave" style="margin:0"><?=token()?><button style="color:inherit;background:none;border:0;text-decoration:underline;cursor:pointer">End preview</button></form><?php endif;?></aside><?php
}
