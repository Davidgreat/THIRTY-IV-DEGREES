/* One checkout owner: no native redirect until the server confirms an outcome. */
(() => {
 'use strict';
 const form=document.getElementById('checkout-form');if(!form)return;
 const summary=document.getElementById('checkout-errors'),buttons=[...document.querySelectorAll('#checkout-form button[type="submit"],button[form="checkout-form"]')];
 const labels=new Map(buttons.map(b=>[b,b.innerHTML]));let inFlight=false;
 form.noValidate=true;
 const invalidFields=()=>[...form.elements].filter(f=>f.willValidate&&!f.checkValidity());
 function show(messages,ref='',link=null){
  const list=summary.querySelector('ul');while(list.firstChild)list.removeChild(list.firstChild);
  for(const message of messages){const li=document.createElement('li');li.textContent=message;list.appendChild(li);}
  if(ref){const li=document.createElement('li');li.textContent='Checkout reference: '+ref;list.appendChild(li);}
  if(link&&safeNext(link)){const li=document.createElement('li'),a=document.createElement('a');a.href=link;a.textContent=link.startsWith('/order/')?'Open your saved order →':'Return to your bag →';li.appendChild(a);list.appendChild(li);}
  summary.hidden=false;summary.focus();summary.scrollIntoView({block:'start',behavior:'auto'});
 }
 function showFields(fields){
  const messages=[];
  fields.forEach(f=>{f.setAttribute('aria-invalid','true');const label=f.id?form.querySelector('label[for="'+f.id.replace(/[^a-zA-Z0-9_-]/g,'')+'"]'):null;messages.push((label?label.textContent.replace(/\*/g,'').trim():'Order details')+': '+f.validationMessage);let parent=f.parentElement;while(parent&&parent!==form){if(parent.tagName==='DETAILS')parent.open=true;parent=parent.parentElement;}});
  show(messages);
 }
 function safeNext(value){
  try{const u=new URL(value,location.origin);if(u.username||u.password)return false;if(u.origin===location.origin)return /^\/(?:order\/\d+(?:\?new=1)?|booking\/\d+|cart)$/.test(u.pathname+u.search);return u.protocol==='https:'&&u.hostname==='checkout.paystack.com'&&(!u.port||u.port==='443');}catch{return false;}
 }
 function reset(){inFlight=false;buttons.forEach(b=>{b.disabled=false;b.removeAttribute('aria-busy');b.innerHTML=labels.get(b);});}
 async function request(url,options={}){
  const controller=new AbortController(),timer=setTimeout(()=>controller.abort(),45000);
  try{
   const response=await fetch(url,{...options,headers:{Accept:'application/json',...(options.headers||{})},credentials:'same-origin',cache:'no-store',redirect:'error',signal:controller.signal});
   const type=response.headers.get('content-type')||'';
   if(!type.includes('application/json'))throw new Error('The checkout endpoint returned a page instead of a checkout response (HTTP '+response.status+'). Please contact our team.');
   const data=await response.json();if(data.service!=='thirtyvi-checkout'||data.build!=='6.1.2')throw new Error('The checkout endpoint is not running the matching update. Please contact our team.');
   return {response,data};
  }finally{clearTimeout(timer);}
 }
 form.addEventListener('submit',async event=>{
  event.preventDefault();event.stopImmediatePropagation();if(inFlight)return;
  const fields=invalidFields();if(fields.length){showFields(fields);return;}
  inFlight=true;summary.hidden=true;
  // Serialize before any control is disabled, so nothing required disappears from the request.
  const data=new URLSearchParams();for(const [key,value] of new FormData(form))if(typeof value==='string')data.append(key,value);
  buttons.forEach(b=>{b.disabled=true;b.setAttribute('aria-busy','true');b.textContent='Connecting securely…';});
  let stage='starting';
  try{
   const bootstrap=await request(form.action+'?bootstrap=1');
   if(!bootstrap.response.ok||!bootstrap.data.ok){show([bootstrap.data.message||'Checkout could not start.'],bootstrap.data.request_id);return;}
   if(bootstrap.data.mode!==form.dataset.paymentMode){show(['The payment environment changed while this page was open. Refresh checkout before continuing.'],bootstrap.data.request_id);return;}
   if(bootstrap.data.cart_items===0){show([bootstrap.data.saved_order_url?'Your order was already saved. Open it to continue without creating another order.':'Your bag session is empty or expired. Return to your bag before continuing.'],bootstrap.data.request_id,bootstrap.data.saved_order_url||'/cart');return;}
   if(typeof bootstrap.data.csrf!=='string'||!bootstrap.data.csrf)throw new Error('The checkout session could not be confirmed. Refresh your bag and try again.');
   data.set('checkout_mode',bootstrap.data.mode);data.set('csrf',bootstrap.data.csrf);form.elements.csrf.value=bootstrap.data.csrf;
   stage='saving order';buttons.forEach(b=>{b.textContent='Saving order & opening payment…';});
   const result=await request(form.action,{method:'POST',body:data});
   if(!result.response.ok||!result.data.ok){show(result.data.errors?Object.values(result.data.errors):[result.data.message||'Checkout could not continue.'],result.data.request_id,result.data.order_url||result.data.next);return;}
   if(!safeNext(result.data.next))throw new Error('The checkout destination could not be verified. Contact our team with reference '+result.data.request_id+'.');
   form.dispatchEvent(new Event('checkout:remember'));location.assign(result.data.next);
  }catch(error){
   const detail=error instanceof TypeError?'The checkout request could not reach the endpoint or was redirected by the hosting server.':error.name==='AbortError'?'The checkout request timed out. Your order may already be saved; check your orders before starting another checkout.':error.message;
   show([detail,'Stage: '+stage+'. Please contact our team if this persists.']);
  }finally{reset();}
 },true);
 form.addEventListener('input',e=>{if(e.target.willValidate&&e.target.checkValidity())e.target.removeAttribute('aria-invalid');});
 window.addEventListener('pageshow',reset);
})();
