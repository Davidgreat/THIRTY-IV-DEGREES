<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) {
 ini_set('session.use_strict_mode','1');
 session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','samesite'=>'Lax','path'=>'/']);
 session_start();
}
date_default_timezone_set('Africa/Lagos');
if(PHP_SAPI!=='cli'){header('X-Content-Type-Options: nosniff');header('X-Frame-Options: SAMEORIGIN');}
const ROOT = __DIR__ . '/..';
function cfg(): array { $f=ROOT.'/config.php'; return is_file($f)?require $f:[]; }
function db(): PDO { static $pdo; if(!$pdo){$c=cfg();$pdo=new PDO('mysql:host='.$c['db_host'].';dbname='.$c['db_name'].';charset=utf8mb4',$c['db_user'],$c['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);}return $pdo; }
function sql(string $query,array $params=[]): PDOStatement {$s=db()->prepare($query);$s->execute($params);return $s;}
function all(string $q,array $p=[]):array{return sql($q,$p)->fetchAll();}
function one(string $q,array $p=[]):?array{return sql($q,$p)->fetch()?:null;}
function esc(?string $s):string{return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function money($n):string{return '₦'.number_format((float)$n,2);}
function setting(string $key,string $default=''):string{static $cache=[];if(!array_key_exists($key,$cache)){$r=one('SELECT value FROM settings WHERE `key`=?',[$key]);$cache[$key]=$r['value']??$default;if($key==='brand' && (in_array(strtoupper(trim($cache[$key])),['THIRTY-IV DEGREES','THIRTHY VI DEGREES','THIRTY VI DEGREES','36°'],true) || preg_match('/^thir(?:t|th)ty\s*[- ]?\s*vi\s*degrees$/i',trim($cache[$key]))))$cache[$key]='36°';}return $cache[$key];}
function url(string $path=''):string{return '/'.ltrim($path,'/');}
function go(string $p):never{if(function_exists('checkout_is_json')&&checkout_is_json())checkout_json_navigation($p);header('Location: '.url($p));exit;}
function flash(string $message,string $type='ok'):void{$_SESSION['flash']=['message'=>$message,'type'=>$type];}
function csrf():string{return $_SESSION['csrf']??=bin2hex(random_bytes(24));}
function token():string{return '<input type="hidden" name="csrf" value="'.esc(csrf()).'">';}
function check_csrf():void{if(!hash_equals(csrf(),(string)($_POST['csrf']??'')))abort(419,'Security token expired. Refresh and retry.');}
function abort(int $status,string $message):never{if(function_exists('checkout_is_json')&&checkout_is_json()){checkout_trace('request_rejected',['http_status'=>$status]);checkout_json_reply($status,['ok'=>false,'stage'=>'request','message'=>$message]);}http_response_code($status);echo '<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="referrer" content="strict-origin-when-cross-origin"><style>body{font:16px system-ui;padding:10%;background:#161b1a;color:white}</style><h1>'.esc((string)$status).'</h1><p>'.esc($message).'</p><a style="color:#e7b977" href="/">Return home</a>';exit;}
function user():?array{return isset($_SESSION['uid'])?one('SELECT id,name,email,role FROM users WHERE id=?',[(int)$_SESSION['uid']]):null;}
function require_login():array{return user()??(go('login'));}
function admin():array{$u=require_login();if(!in_array($u['role'],['admin','manager'],true))abort(403,'Admin access required');return $u;}
function staff_can_action(string $action):bool{
 $u=admin();if($u['role']==='admin')return true;
 $permissions=['order/status'=>'orders_manage','booking/update'=>'bookings_manage','booking/note'=>'bookings_manage','whatsapp/draft'=>'messages_send','inventory/save'=>'inventory_manage'];
 if(!isset($permissions[$action]))return false;
 $r=one('SELECT allowed FROM staff_permissions WHERE user_id=? AND permission=?',[$u['id'],$permissions[$action]]);
 return $r && (int)$r['allowed']===1;
}
function is_admin():bool{return in_array(user()['role']??'', ['admin','manager'],true);}
function post():bool{return $_SERVER['REQUEST_METHOD']==='POST';}
function str(string $key,int $max=500):string{return mb_substr(trim((string)($_POST[$key]??'')),0,$max);}
function reference(string $prefix):string{return $prefix.'-'.strtoupper(bin2hex(random_bytes(5)));}
function rate_limit(string $key,int $max=12,int $period=300):void{
 $now=time();$bucket=hash('sha256',$key.'|'.($_SERVER['REMOTE_ADDR']??'cli').'|'.(cfg()['app_key']??cfg()['db_pass']??''));
 sql('INSERT INTO request_rate_limits(bucket,hits,expires_at) VALUES(?,1,?) ON DUPLICATE KEY UPDATE hits=IF(expires_at<=?,1,hits+1),expires_at=IF(expires_at<=?,VALUES(expires_at),expires_at)',[$bucket,$now+$period,$now,$now]);
 $r=one('SELECT hits FROM request_rate_limits WHERE bucket=?',[$bucket]);
 if((int)$r['hits']>$max)abort(429,'Too many attempts. Try again later.');
 if(random_int(1,100)===1)sql('DELETE FROM request_rate_limits WHERE expires_at<? LIMIT 100',[$now]);
}
require_once __DIR__.'/integrations.php';
require_once __DIR__.'/mailer.php';
require_once __DIR__.'/account-recovery.php';
require_once __DIR__.'/paystack.php';
require_once __DIR__.'/paystack-refunds.php';
require_once __DIR__.'/payment-verification.php';


// V5.5.0 order scheduling, status tracking, and curated banners.
function vi_local_now():DateTimeImmutable{return new DateTimeImmutable('now',new DateTimeZone('Africa/Lagos'));}
function vi_schedule_validate(string $value):?string{
 if($value==='')return null;
 $tz=new DateTimeZone('Africa/Lagos');$dt=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$value,$tz);
 if(!$dt||$dt->format('Y-m-d\TH:i')!==$value)throw new InvalidArgumentException('Choose a valid delivery date and time.');
 $now=vi_local_now();$min=$now->modify('+2 hours');$max=$now->modify('+14 days');
 if($dt<$min||$dt>$max)throw new InvalidArgumentException('Schedule at least 2 hours ahead and no more than 14 days in advance.');
 $hour=(int)$dt->format('G');if($hour<9||$hour>=20)throw new InvalidArgumentException('Select a time between 9 AM and 8 PM.');
 return $dt->format('Y-m-d H:i:s');
}
function vi_banners(string $placement):void{
 $banners=all('SELECT headline,subtitle,image_url,target_url,button_text FROM site_banners WHERE placement=? AND enabled=1 ORDER BY sort_order,id LIMIT 3',[$placement]);
 if(!$banners)return;
 ?><section class="vi-banner-group container" aria-label="Offers and announcements"><?php foreach($banners as $b):?><article class="vi-banner <?=str_starts_with((string)$b['image_url'],'/public/assets/banners/')?'vi-banner-designed':''?>"><?php if($b['image_url']):?><img class="vi-banner-photo" src="<?=esc($b['image_url'])?>" alt="" loading="lazy"><?php endif;?><?php if(str_starts_with((string)$b['image_url'],'/public/assets/banners/')):?><a class="vi-banner-full-link" href="<?=esc($b['target_url'])?>" aria-label="<?=esc($b['headline'])?>"></a><?php else:?><div class="vi-banner-copy"><span class="vi-banner-kicker">36°</span><h2><?=esc($b['headline'])?></h2><?php if($b['subtitle']):?><p><?=esc($b['subtitle'])?></p><?php endif;?><a class="vi-banner-link" href="<?=esc($b['target_url'])?>"><?=esc($b['button_text'])?> →</a></div><?php endif;?></article><?php endforeach;?></section><?php
}

function audit(string $action,string $details=''):void{sql('INSERT INTO audit_logs(user_id,action,details) VALUES(?,?,?)',[user()['id']??null,$action,$details]);}
function run_hook(string $name,array $data=[]):void{try{email_enqueue_event($name,$data);}catch(Throwable $e){error_log('Email enqueue failed: '.$e->getMessage());}foreach(glob(ROOT.'/plugins/*/bootstrap.php')?:[] as $file){$plugin=basename(dirname($file));$manifest=dirname($file).'/plugin.json';$meta=is_file($manifest)?json_decode(file_get_contents($manifest),true):[];if(($meta['enabled']??false)===true && version_compare((string)(setting('app_version','2.0.0')), (string)($meta['min_core']??'0.0.0'), '>=')){try{(static function($file,$name,$data){require $file;})($file,$name,$data);}catch(Throwable $e){error_log('Plugin '.$plugin.': '.$e->getMessage());}}}}
function vi_chef_badge():void{
 $available=setting('chef_available','1')==='1';
 ?><span class="chef-availability" data-chef-status data-available="<?=$available?'1':'0'?>" role="status"><span class="chef-availability-dot" aria-hidden="true"></span><span data-chef-label><?=$available?'Chef active · Accepting inquiries':'Chef paused · Inquiries closed'?></span></span><?php
}
function layout(string $title,callable $render,bool $admin=false):void{ $u=user();$brand=setting('brand','36°');?><!doctype html><html lang="en" data-theme="<?=esc(setting('theme_default','dark'))?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="referrer" content="strict-origin-when-cross-origin"><meta name="theme-color" content="<?=setting('theme_default','dark')==='light'?'#f8f5ef':'#14120f'?>"><style>:root{--accent:<?=esc('#D6A84D')?>;--accent-ink:<?=esc('#080808')?>;--site-bg:<?=esc('#080808')?>;--site-light:<?=esc('#FFFFFF')?>}</style><script>(function(){try{var t=localStorage.getItem('thirtyvi-theme');if(t==='light'||t==='dark')document.documentElement.dataset.theme=t}catch(e){}})();</script><title><?=esc($title)?> | <?=esc($brand)?></title><meta name="description" content="Premium cuisine, private dining and exceptional culinary experiences."><link rel="stylesheet" href="/public/assets/style.css?v=5.4.6compact"><link rel="stylesheet" href="/public/assets/revamp.css?v=5.5.0"><link rel="stylesheet" href="/public/assets/checkout-fix.css?v=5.5.0"><link rel="stylesheet" href="/public/assets/experience.css?v=5.5.2"><link rel="stylesheet" href="/public/assets/admin-stability.css?v=5.5.4"><link rel="stylesheet" href="/public/assets/ux-stability.css?v=5.5.5"><link rel="stylesheet" href="/public/assets/admin-analytics-stability.css?v=5.5.6"><link rel="stylesheet" href="/public/assets/order-tracking.css?v=5.6.0"><link rel="stylesheet" href="/public/assets/store-compact.css?v=5.7.0"><link rel="stylesheet" href="/public/assets/customer-dashboard.css?v=5.7.6"><link rel="stylesheet" href="/public/assets/customer-favourites.css?v=5.7.7"><style id="vi-visibility-safety-v5714">html.vi-reveal-ready .vi-reveal,html.vi-reveal-ready .vi-reveal:not(.vi-in-view),body.vi-home-page .vi-reveal:not(.vi-visible){opacity:1!important;visibility:visible!important;transform:none!important;filter:none!important;transition:none!important}.vi-scroll-progress{display:none!important}</style><link rel="stylesheet" href="/public/assets/brand-580.css?v=5.8.0"><link rel="stylesheet" href="/public/assets/studio-610.css?v=6.1.0"><link rel="stylesheet" href="/public/assets/checkout-611.css?v=6.1.1"><link rel="stylesheet" href="/public/assets/atelier-620.css?v=6.2.0"><link rel="stylesheet" href="/public/assets/auth-621.css?v=6.2.1"></head><body class="vi-compact <?= $admin?'studio-admin ':'' ?><?=in_array(trim(parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)??'/','/'),['','home'],true)?'vi-home-page':'vi-inner-page'?>"><header class="topbar"><a class="logo" href="/"><img class="brand-official-logo" src="/public/assets/official-logo-580.png?v=5.8.0" alt="THIRTY VI DEGREES official logo"></a><nav id="site-navigation"><a href="/menu">Order Food</a><a href="/shop">36° Store</a><a href="/private-chef">Private Chef</a><a href="/catering">Catering</a><a href="/about">Our Story</a><?php if($u):?><a href="/account">My Account</a><?php if(is_admin()):?><a href="/admin">Dashboard</a><?php endif;?><a href="/logout">Logout</a><?php else:?><a href="/login">Sign In</a><?php endif;?><a class="cartlink" href="/cart">Bag <span><?=array_sum(array_column($_SESSION['cart']??[],'qty'))?></span></a></nav><button type="button" class="theme-toggle" data-theme-toggle aria-label="Switch colour theme" title="Switch between dark and light theme">◐ <span data-theme-label>Theme</span></button><button class="hamb" aria-label="Toggle navigation" aria-controls="site-navigation" aria-expanded="false" data-site-menu>☰</button></header><?php if(isset($_SESSION['flash'])):$f=$_SESSION['flash'];unset($_SESSION['flash']);?><div class="notice <?=esc($f['type'])?>"><?=esc($f['message'])?></div><?php endif;?><a class="skip-link" href="#main-content">Skip to content</a><main id="main-content"><?php $render(); ?></main><footer class="site-footer"><div class="footer-brand"><div class="logo"><img class="brand-official-logo" src="/public/assets/official-logo-580.png?v=5.8.0" alt="THIRTY VI DEGREES official logo"></div><p>Beyond the plate. Into the experience.</p></div><div class="footer-column"><b>Explore</b><a href="/menu">Our Menu</a><a href="/private-chef">Private Dining</a><a href="/catering">Catering</a></div><div class="footer-column"><b>Get in touch</b><a class="footer-contact-email" href="mailto:<?=esc(setting('email','hello@thirtyvidegrees.com'))?>"><span class="footer-email-full"><?=esc(setting('email','hello@thirtyvidegrees.com'))?></span><span class="footer-email-short">Email us</span></a><?php if(trim((string)setting('phone',''))!==''):?><a class="footer-contact-phone" href="tel:<?=esc(preg_replace('/[^+0-9]/','',(string)setting('phone','')))?>"><?=esc(setting('phone',''))?></a><?php endif;?></div><div class="footer-column"><b>Legal</b><a href="/page/privacy">Privacy</a><a href="/page/terms">Terms</a><a href="/page/refunds">Refund Policy</a></div><div class="footer-bottom"><span>© <?=date('Y')?> 36°. All rights reserved.</span><span class="footer-credit-separator" aria-hidden="true">|</span><span>Developed by <a href="https://wa.me/2349137271619" target="_blank" rel="noopener noreferrer" aria-label="Chat with Bro David on WhatsApp">Bro David</a></span></div></footer><script src="/public/assets/commerce-570.js?v=6.1.2" defer></script><script src="/public/assets/app.js?v=6.2.0" defer></script><script src="/public/assets/ux-stability.js?v=6.1.2" defer></script><script src="/public/assets/admin-analytics-stability.js?v=5.5.6" defer></script><script src="/public/assets/chef-status.js?v=6.1.0" defer></script><script src="/public/assets/customer-favourites.js?v=5.7.7" defer></script><script src="/public/assets/studio-610.js?v=6.1.0" defer></script><script src="/public/assets/checkout-612.js?v=6.1.2" defer></script><script src="/public/assets/atelier-620.js?v=6.2.0" defer></script><script src="/public/assets/auth-621.js?v=6.2.1" defer></script></body></html><?php }
