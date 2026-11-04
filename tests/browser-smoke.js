const {chromium}=require('playwright');
const assert=require('node:assert/strict');
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:'/usr/bin/chromium',args:['--no-sandbox']});
 const context=await browser.newContext({viewport:{width:1440,height:1000}});const page=await context.newPage();
 const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.route('**/*',route=>new URL(route.request().url()).hostname==='127.0.0.1'?route.continue():route.abort());
 const base='http://127.0.0.1:8086';let checks=0;
 async function screenshot(options){
  await page.evaluate(()=>document.querySelectorAll('img').forEach(img=>{img.loading='eager';}));
  await page.waitForFunction(()=>[...document.images].every(i=>i.complete),{timeout:10000}).catch(()=>{});
  await page.screenshot(options);
 }

 async function visit(path,text){const r=await page.goto(base+path);assert.equal(r.status(),200,path);assert(!(await page.locator('body').innerText()).includes('Fatal error'),path);if(text)assert((await page.locator('body').innerText()).includes(text),path);checks++;}
 await visit('/','Food worth');await screenshot({path:'test-results/home-desktop.png',fullPage:true});
 for(const path of ['/menu','/shop','/private-chef','/catering','/about','/cart','/track','/login','/register','/forgot-password'])await visit(path);
 await page.goto(base+'/login');await page.locator('[name=email]').fill('admin@example.test');await page.locator('[name=password]').fill('Test-password-600');await page.locator('button[type=submit], form button').first().click();await page.waitForURL(base+'/admin');checks++;
 for(const path of ['/admin','/admin/paystack','/admin/orders','/admin/bookings','/admin/payments','/admin/inventory','/admin/analytics','/admin/settings','/admin/integrations','/admin/email','/admin/updates','/admin/calendar','/admin/products','/admin/staff','/admin/refunds','/admin/media','/admin/sliders','/admin/pages','/admin/plugins','/admin/banners','/admin/meal-options?product_id=1'])await visit(path);
 await page.goto(base+'/admin/paystack');await screenshot({path:'test-results/paystack-desktop.png',fullPage:true});
 await page.goto(base+'/admin');await screenshot({path:'test-results/admin-desktop.png',fullPage:true});
 await page.goto(base+'/admin/paystack');await page.evaluate(()=>{localStorage.setItem('thirtyvi-theme','light')});await page.reload();
 assert.equal(await page.locator('html').getAttribute('data-theme'),'light');checks++;
 assert((await page.locator('main').evaluate(e=>getComputedStyle(e).backgroundColor))!=='rgb(14, 18, 18)');checks++;
 await screenshot({path:'test-results/paystack-light.png',fullPage:true});
 await page.evaluate(()=>{localStorage.setItem('thirtyvi-theme','dark')});await page.reload();
 for(const width of [390,768]){
  await page.setViewportSize({width,height:844});
  for(const path of ['/','/menu','/private-chef','/admin','/admin/paystack']){
   await visit(path);const overflow=await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+2);assert.equal(overflow,false,`Horizontal overflow: ${path} @ ${width}`);checks++;
   if(width===390)await screenshot({path:`test-results/${path.replaceAll('/','-')||'home'}-mobile.png`,fullPage:true});
  }
 }
 await page.goto(base+'/logout');await page.goto(base+'/login');await page.locator('[name=email]').fill('customer@example.test');await page.locator('[name=password]').fill('Test-password-600');await page.locator('form button').click();await page.waitForURL(base+'/account');checks++;
 await visit('/account','Welcome back');await screenshot({path:'test-results/account-mobile.png',fullPage:true});
 assert.equal((await page.goto(base+'/admin/paystack')).status(),403);checks++;
 await page.goto(base+'/logout');await page.goto(base+'/login');await page.locator('[name=email]').fill('manager@example.test');await page.locator('[name=password]').fill('Test-password-600');await page.locator('form button').click();await page.waitForURL(base+'/admin');
 assert.equal((await page.goto(base+'/admin/paystack')).status(),403);checks++;
 assert.deepEqual(errors,[],'Browser JavaScript errors');checks++;
 console.log(JSON.stringify({checks,jsErrors:errors,result:'PASS'}));await browser.close();
})().catch(e=>{console.error(e);process.exit(1)});
