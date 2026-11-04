const {chromium}=require('playwright');
const assert=require('node:assert/strict');
const fs=require('node:fs');
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:'/usr/bin/chromium',args:['--no-sandbox']});
 let checks=0;const errors=[],measurements=[];const base='http://127.0.0.1:8086';
 const context=await browser.newContext({viewport:{width:390,height:844}});const page=await context.newPage();
 page.on('pageerror',e=>errors.push(e.message));await page.route('**/*',r=>new URL(r.request().url()).hostname==='127.0.0.1'?r.continue():r.abort());
 function check(value,label){assert(value,label);checks++;}
 async function go(path){const r=await page.goto(base+path);check(r.status()===200,`GET ${path}: ${r.status()}`);check(!(await page.locator('body').innerText()).includes('Fatal error'),`No PHP error ${path}`);}
 async function capture(name){await page.evaluate(()=>document.querySelectorAll('img').forEach(i=>{i.loading='eager'}));await page.waitForFunction(()=>[...document.images].every(i=>i.complete),null,{timeout:8000}).catch(()=>{});await page.screenshot({path:`test-results/6.1.0/${name}.png`,fullPage:true});}
 async function noOverflow(label){check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),`No page overflow: ${label}`);}
 await go('/');check(await page.locator('[data-experience-panel]:visible').count()===1,'One homepage experience visible');await page.locator('[data-experience-choice=chef]').click();check(await page.locator('[data-experience-panel=chef]').isVisible(),'Chef experience tab works');await page.locator('[data-experience-choice=chef]').press('ArrowRight');check(await page.locator('[data-experience-panel=events]').isVisible(),'Experience tabs support keyboard');
 await page.locator('[data-site-menu]').click();check(await page.locator('.topbar nav').isVisible(),'Mobile navigation opens');await page.keyboard.press('Escape');check(!await page.locator('.topbar nav').isVisible(),'Mobile navigation closes with Escape');
 for(const width of [320,375,390,430,768,1280,1440]){
  await page.setViewportSize({width,height:900});
  for(const path of ['/','/menu','/shop','/private-chef','/catering','/about','/login','/cart']){
   await go(path);await noOverflow(`${path} @ ${width}`);
   check((await page.locator('.topbar').evaluate(e=>e.getBoundingClientRect().height))===(width<768?56:64),'Compact header height');
   if(width<768)check((await page.locator('.site-footer').evaluate(e=>e.getBoundingClientRect().height))<300,'Footer is compact on mobile');
   if(path==='/'){
    const h=await page.locator('.home-hero').evaluate(e=>e.getBoundingClientRect().height);check(h<=(width<768?345:430),`Bounded hero ${width}: ${h}`);
    const panels=await page.locator('[data-experience-panel]:visible').count();check(panels===1,'No stacked experience boxes');
    if(width===390||width===1440)await capture(`home-${width}`);
   }
   if(path==='/shop'){check(await page.locator('.vi-shop-tabs').evaluate(e=>getComputedStyle(e).position)==='static','Store categories stay in normal flow');check(await page.locator('.vi-shop-tabs').evaluate(e=>getComputedStyle(e).flexDirection)==='row','Store categories stay horizontal');}
   if(path==='/private-chef'){
    const h=await page.locator('[data-booking-wizard]').evaluate(e=>e.getBoundingClientRect().height);check(h<550,`Compact booking step ${width}: ${h}`);
    check(await page.locator('[data-booking-step]:visible').count()===1,'Only one booking step visible');
    measurements.push({width,bookingStepHeight:h,pageHeight:await page.locator('body').evaluate(e=>e.scrollHeight)});
    if(width===390||width===1440)await capture(`booking-${width}`);
   }
  }
 }
 await page.setViewportSize({width:390,height:844});await go('/private-chef');await page.locator('[data-booking-next]').click();check(await page.locator('[data-booking-step="0"]').isVisible(),'Cannot skip required event fields');
 const date=new Date(Date.now()+10*86400000).toISOString().slice(0,10);
 await page.locator('[name=event_date]').fill(date);await page.locator('[name=event_time]').fill('14:00');await page.locator('[name=location]').fill('Test venue, Lagos');await page.locator('[data-booking-next]').click();
 check(await page.locator('[data-booking-step="1"]').isVisible(),'Valid event proceeds to contact step');check((await page.locator('[data-booking-summary]').innerText()).includes(date),'Summary uses entered event details');await capture('booking-details-390');
 await page.locator('[data-booking-back]').click();check(await page.locator('[name=location]').inputValue()==='Test venue, Lagos','Back preserves entered event details');await page.locator('[data-booking-next]').click();await page.locator('[name=name]').fill('Compact Test');await page.locator('[name=email]').fill('compact@example.test');await page.locator('[name=phone]').fill('08012345678');await page.locator('[data-booking-submit]').click();await page.waitForURL(/\/booking\/\d+/);check(true,'Two-step booking submits to existing backend');
 // Owner workflows use the same protected routes and existing saved credentials.
 await go('/login');await page.locator('[name=email]').fill('admin@example.test');await page.locator('[name=password]').fill('Test-password-600');await page.locator('form button').click();await page.waitForURL(base+'/admin');
 check(await page.locator('[data-admin-group]').count()===6,'Admin tools grouped into six sections');
 await page.locator('.admin-theme-switch').click();check(await page.locator('html').getAttribute('data-theme')==='light','Workspace theme switch works');await page.locator('.admin-theme-switch').click();check(await page.locator('html').getAttribute('data-theme')==='dark','Workspace returns to dark theme');
 check(await page.locator('[data-overview-panel]:visible').count()===1,'Phone overview shows one work queue');await page.locator('[data-overview-tab=bookings]').click();check(await page.locator('[data-overview-panel=bookings]').isVisible(),'Booking queue tab opens');await page.locator('[data-overview-tab=orders]').click();
 const navRoutes=await page.locator('.admin-nav a').evaluateAll(a=>a.map(e=>e.getAttribute('href')));
 for(const route of ['orders','products','bookings','calendar','inventory','analytics','customers','payments','paystack','refunds','staff','reviews','product-reviews','pages','settings','whatsapp','email','email-templates','integrations','sliders','banners','media','plugins','updates'])check(navRoutes.includes('/admin/'+route),`Admin route preserved: ${route}`);
 await page.locator('[data-admin-menu]').click();await page.locator('[data-nav-search]').fill('Paystack');check(await page.locator('.admin-nav-link[href="/admin/paystack"]').isVisible(),'Search reveals payment tool inside collapsed group');await page.locator('.admin-nav-link[href="/admin/paystack"]').click();await page.waitForURL(base+'/admin/paystack');
 check(!await page.locator('[name=paystack_test_secret]').inputValue(),'Stored secret never rendered');
 check(await page.locator('[data-credential-mode=test]').getAttribute('open')!==null,'Active credentials expanded');check(await page.locator('[data-credential-mode=live]').getAttribute('open')===null,'Inactive credentials folded away');
 await page.locator('[name=paystack_mode][value=live]').check();check(await page.locator('[data-credential-mode=live]').getAttribute('open')!==null,'Mode selection opens matching credential section');
 for(const width of [320,390,768,1440]){
  await page.setViewportSize({width,height:900});
  for(const path of ['/admin','/admin/workspace','/admin/paystack','/admin/orders','/admin/bookings','/admin/settings','/admin/products','/admin/updates']){await go(path);await noOverflow(`${path} @ ${width}`);if([390,1440].includes(width)&&['/admin','/admin/workspace','/admin/paystack'].includes(path))await capture(`${path.split('/').filter(Boolean).join('-')}-${width}`);}
 }
 await page.setViewportSize({width:390,height:844});await go('/admin/settings');
 const csrf=await page.locator('[name=csrf]').first().inputValue();
 const guest=await context.newPage();await guest.route('**/*',r=>new URL(r.request().url()).hostname==='127.0.0.1'?r.continue():r.abort());await guest.goto(base+'/private-chef');await guest.locator('[name=location]').fill('Retained location');
 await page.request.post(base+'/admin/chef/availability',{form:{csrf,available:'0'}});await guest.evaluate(()=>window.dispatchEvent(new Event('pageshow')));await guest.waitForFunction(()=>document.querySelector('[data-chef-status]').dataset.available==='0');
 const red=await guest.locator('.chef-availability').evaluate(e=>getComputedStyle(e).color);check(red==='rgb(242, 117, 117)','Paused status is red');check(await guest.locator('[name=location]').isDisabled(),'Live pause disables new inquiry fields');check(await guest.locator('[data-booking-next]').isDisabled(),'Live pause disables continue');check(await guest.locator('[data-chef-pause-notice]').isVisible(),'Live pause displays clear notice');await guest.screenshot({path:'test-results/6.1.0/chef-paused-390.png',fullPage:true});
 await page.request.post(base+'/admin/chef/availability',{form:{csrf,available:'1'}});await guest.evaluate(()=>window.dispatchEvent(new Event('pageshow')));await guest.waitForFunction(()=>document.querySelector('[data-chef-status]').dataset.available==='1');
 check(await guest.locator('[name=location]').inputValue()==='Retained location','Status refresh preserves draft');check(!await guest.locator('[name=location]').isDisabled(),'Resume enables inquiry fields');check(!await guest.locator('[data-chef-pause-notice]').isVisible(),'Resume removes stale pause notice');const green=await guest.locator('.chef-availability').evaluate(e=>getComputedStyle(e).color);check(green==='rgb(68, 200, 135)','Active status is green');
 check(await guest.locator('.chef-availability-dot').evaluate(e=>getComputedStyle(e,'::after').animationName)==='chef-signal','Availability indicator pulses');await guest.emulateMedia({reducedMotion:'reduce'});check(await guest.locator('.chef-availability-dot').evaluate(e=>getComputedStyle(e,'::after').animationName)==='none','Reduced motion disables pulse');await guest.close();
 // Customer tabs retain orders, bookings, favourites and review actions.
 await go('/logout');await go('/login');await page.locator('[name=email]').fill('customer@example.test');await page.locator('[name=password]').fill('Test-password-600');await page.locator('form button').click();await page.waitForURL(base+'/account');
 for(const key of ['orders','bookings','favourites']){await page.locator(`[data-account-tab=${key}]`).click();check(await page.locator(`[data-account-panel=${key}]`).isVisible(),`Customer ${key} tab works`);}check(await page.locator('[data-account-panel]:visible').count()===1,'Account no longer stacks every activity section');await page.locator('[data-account-tab=orders]').click();check(await page.locator('.customer-shortcuts').evaluate(e=>getComputedStyle(e).position)==='static','Account shortcuts do not cover activity');await capture('account-390');await noOverflow('customer account 390');
 await page.setViewportSize({width:1440,height:1000});await capture('account-1440');
 // A manager keeps operational navigation without owner-only settings.
 await go('/logout');await go('/login');await page.locator('[name=email]').fill('manager@example.test');await page.locator('[name=password]').fill('Test-password-600');await page.locator('form button').click();await page.waitForURL(base+'/admin');await go('/admin/workspace');
 check(await page.locator('a[href="/admin/paystack"]').count()===0,'Manager guide excludes payment credentials');check((await page.goto(base+'/admin/paystack')).status()===403,'Manager cannot open payment setup');
 // Checkout retains its gift/pickup behavior under the compact shared styles.
 const shop=await context.newPage();await shop.route('**/*',r=>new URL(r.request().url()).hostname==='127.0.0.1'?r.continue():r.abort());await shop.goto(base+'/product/2');const cartToken=await shop.locator('[name=csrf]').first().inputValue();await shop.request.post(base+'/cart/add',{form:{csrf:cartToken,product_id:'2',qty:'1'}});
 for(const width of [320,390,768,1440]){await shop.setViewportSize({width,height:900});const response=await shop.goto(base+'/checkout');check(response.status()===200,'Checkout opens with cart');check(await shop.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),`Checkout fits ${width}`);}
 await shop.setViewportSize({width:390,height:844});await shop.locator('[name=fulfillment][value=delivery]').check();await shop.locator('[data-gift-toggle]').check();check(await shop.locator('[data-gift-fields]').isVisible(),'Gift recipient fields remain functional');await shop.locator('[name=fulfillment][value=pickup]').check();check(!await shop.locator('[data-gift-fields]').isVisible(),'Pickup clears delivery-only gift fields');await shop.close();
 // No-JavaScript fallback retains complete form and all homepage destinations.
 const nojs=await browser.newContext({javaScriptEnabled:false,viewport:{width:390,height:844}});const fallback=await nojs.newPage();await fallback.route('**/*',r=>new URL(r.request().url()).hostname==='127.0.0.1'?r.continue():r.abort());await fallback.goto(base+'/private-chef');check(await fallback.locator('[data-booking-step]:visible').count()===2,'Without JavaScript all booking fields remain available');check(await fallback.locator('[data-booking-submit]').isVisible(),'Without JavaScript submit remains available');
 check(errors.length===0,`No browser JS errors: ${errors.join(', ')}`);
 fs.writeFileSync('test-results/6.1.0/layout-metrics.json',JSON.stringify(measurements,null,2));fs.writeFileSync('test-results/6.1.0/results.json',JSON.stringify({checks,errors,measurements},null,2));console.log(JSON.stringify({checks,errors,measurements,result:'PASS'},null,2));await browser.close();
})().catch(e=>{console.error(e);process.exit(1)});
