/* Progressive enhancement only. Every business action remains a server form. */
(() => {
 'use strict';
 const toggle=document.querySelector('[data-site-menu]'),nav=document.getElementById('site-navigation');
 const close=()=>{nav?.classList.remove('show');toggle?.setAttribute('aria-expanded','false');};
 toggle?.addEventListener('click',()=>{const open=toggle.getAttribute('aria-expanded')!=='true';nav?.classList.toggle('show',open);toggle.setAttribute('aria-expanded',String(open));});
 document.addEventListener('keydown',e=>{if(e.key==='Escape')close();});
 nav?.querySelectorAll('a').forEach(a=>a.addEventListener('click',close));
 document.addEventListener('click',e=>{if(nav&&!nav.contains(e.target)&&!toggle?.contains(e.target))close();});
 document.querySelector('[data-nav-search]')?.addEventListener('input',e=>{const q=e.target.value.trim().toLowerCase();document.querySelectorAll('.admin-nav-link').forEach(a=>{a.hidden=!a.textContent.toLowerCase().includes(q);a.style.display=a.hidden?'none':'';});});
 document.querySelectorAll('[data-copy-target]').forEach(button=>button.addEventListener('click',async()=>{const input=document.getElementById(button.dataset.copyTarget);if(!input)return;try{await navigator.clipboard.writeText(input.value);button.textContent='Copied ✓';}catch{input.focus();input.select();button.textContent='Select and copy the URL';}}));
 const form=document.querySelector('[data-paystack-form]');
 const message=document.querySelector('[data-mode-notice]');
 const update=()=>{const live=form?.querySelector('[name="paystack_mode"]:checked')?.value==='live';if(message)message.textContent=live?'Live mode: customers will be charged real money.':'Test mode: use Paystack test cards only.';};
 form?.querySelectorAll('[name="paystack_mode"]').forEach(r=>r.addEventListener('change',update));
 update();
 // Mark all table scrollers as keyboard-accessible regions on small screens.
 document.querySelectorAll('.scroll').forEach(el=>{el.tabIndex=0;el.setAttribute('role','region');el.setAttribute('aria-label','Scrollable data table');});
})();
// Keep meals useful when an old remote image has disappeared.
(()=>{document.querySelectorAll('.food-photo img,.product-image img').forEach(img=>{const fallback=()=>{if(img.dataset.fallback)return;img.dataset.fallback='1';img.src='/public/assets/banners/signature.webp';img.style.objectPosition='right center';};img.addEventListener('error',fallback,{once:true});if(img.complete&&img.naturalWidth===0)fallback();});})();
