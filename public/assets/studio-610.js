/* Compact progressive enhancement; routes and server validation remain authoritative. */
(() => {
 'use strict';
 const reduce=matchMedia('(prefers-reduced-motion: reduce)').matches;
 const toggle=document.querySelector('[data-site-menu]'),nav=document.querySelector('.topbar nav');
 const close=()=>{nav?.classList.remove('show');toggle?.setAttribute('aria-expanded','false');};
 toggle?.addEventListener('click',()=>{const open=!nav?.classList.contains('show');nav?.classList.toggle('show',open);toggle.setAttribute('aria-expanded',String(open));});
 document.addEventListener('keydown',e=>{if(e.key==='Escape')close();});
 document.addEventListener('click',e=>{if(nav&&!nav.contains(e.target)&&!toggle?.contains(e.target))close();});
 nav?.querySelectorAll('a').forEach(a=>a.addEventListener('click',close));
 // Only one settings group is expanded at a time, unless a search is active.
 const groups=[...document.querySelectorAll('[data-admin-group]')];
 const search=document.querySelector('[data-nav-search]');
 groups.forEach(g=>g.addEventListener('toggle',()=>{if(g.open&&!search?.value.trim())groups.forEach(other=>{if(other!==g)other.open=false;});}));
 search?.addEventListener('input',()=>{
  const q=search.value.trim().toLowerCase();let found=0;
  groups.forEach(g=>{let matches=0;g.querySelectorAll('.admin-nav-link').forEach(a=>{const match=!q||(a.textContent+' '+a.dataset.toolDescription).toLowerCase().includes(q);a.hidden=!match;if(match)matches++;});g.hidden=matches===0;g.open=!!q&&matches>0||!q&&!!g.querySelector('[aria-current="page"]');found+=matches;});
  const empty=document.querySelector('[data-nav-empty]');if(empty)empty.hidden=found>0;
 });
 document.querySelectorAll('[data-copy-target]').forEach(b=>b.addEventListener('click',async()=>{const input=document.getElementById(b.dataset.copyTarget);if(!input)return;try{await navigator.clipboard.writeText(input.value);b.textContent='Copied ✓';}catch{input.focus();input.select();b.textContent='Select and copy the URL';}}));
 const paystack=document.querySelector('[data-paystack-form]');
 const syncMode=()=>{const live=paystack?.querySelector('[name="paystack_mode"]:checked')?.value==='live';paystack?.querySelectorAll('[data-credential-mode]').forEach(panel=>{panel.open=panel.dataset.credentialMode===(live?'live':'test');});const message=document.querySelector('[data-mode-notice]');if(message)message.textContent=live?'Live mode: customers will be charged real money.':'Test mode: use Paystack test cards only.';};
 paystack?.querySelectorAll('[name="paystack_mode"]').forEach(r=>r.addEventListener('change',syncMode));syncMode();
 // app.js manages the header theme control; wire the workspace control to the same action.
 document.querySelector('.admin-theme-switch')?.addEventListener('click',()=>document.querySelector('.topbar [data-theme-toggle]')?.click());
 document.querySelectorAll('.scroll').forEach(el=>{el.tabIndex=0;el.setAttribute('role','region');el.setAttribute('aria-label','Scrollable data table');});
 // All content stays readable if JavaScript is unavailable.
 function tabs(buttonSelector,panelSelector,key,barSelector){
  const buttons=[...document.querySelectorAll(buttonSelector)],panels=[...document.querySelectorAll(panelSelector)],bar=document.querySelector(barSelector);
  if(!buttons.length||!panels.length)return;
  if(bar)bar.hidden=false;
  function show(button,focus=false){const value=button.dataset[key];buttons.forEach(b=>{const selected=b===button;b.setAttribute('aria-selected',String(selected));b.tabIndex=selected?0:-1;});panels.forEach(p=>{const selected=p.dataset[key==='experienceChoice'?'experiencePanel':'accountPanel']===value;p.hidden=!selected;p.setAttribute('role','tabpanel');p.setAttribute('aria-labelledby',buttonSelector.includes('experience')?'tab-'+p.dataset.experiencePanel:'account-tab-'+p.dataset.accountPanel);});if(focus)button.focus();}
  buttons.forEach((b,i)=>{b.addEventListener('click',()=>show(b));b.addEventListener('keydown',e=>{let j;if(e.key==='ArrowRight')j=(i+1)%buttons.length;if(e.key==='ArrowLeft')j=(i-1+buttons.length)%buttons.length;if(e.key==='Home')j=0;if(e.key==='End')j=buttons.length-1;if(j!==undefined){e.preventDefault();show(buttons[j],true);}});});
  const hash=location.hash.replace('#','');const initial=buttons.find(b=>b.dataset[key]===hash)||buttons[0];show(initial);
 }
 tabs('[data-experience-choice]','[data-experience-panel]','experienceChoice','[data-experience-tabs]');
 tabs('[data-account-tab]','[data-account-panel]','accountTab','[data-account-tabs]');
 // The overview has two columns on desktop and one selectable queue on phones.
 const overview=document.querySelector('[data-overview-tabs]');
 if(overview){const media=matchMedia('(max-width:767px)'),buttons=[...overview.querySelectorAll('[data-overview-tab]')],panels=[...document.querySelectorAll('[data-overview-panel]')];let selected='orders';
  const sync=()=>{overview.hidden=!media.matches;buttons.forEach(b=>{const active=b.dataset.overviewTab===selected;b.setAttribute('aria-selected',String(active));b.tabIndex=active?0:-1;});panels.forEach(p=>{p.hidden=media.matches&&p.dataset.overviewPanel!==selected;});};
  buttons.forEach((b,i)=>{b.addEventListener('click',()=>{selected=b.dataset.overviewTab;sync();});b.addEventListener('keydown',e=>{if(e.key==='ArrowLeft'||e.key==='ArrowRight'){e.preventDefault();const next=buttons[(i+1)%buttons.length];selected=next.dataset.overviewTab;sync();next.focus();}});});media.addEventListener('change',sync);sync();
 }
 // Missing remote photographs fall back to existing local artwork.
 document.querySelectorAll('main img').forEach(img=>{const fallback=()=>{if(img.dataset.fallback)return;img.dataset.fallback='1';img.src='/public/assets/banners/signature.webp';img.style.objectPosition='right center';};img.addEventListener('error',fallback,{once:true});if(img.complete&&img.naturalWidth===0)fallback();});
 const form=document.querySelector('[data-booking-wizard]');if(!form)return;
 const steps=[...form.querySelectorAll('[data-booking-step]')],next=form.querySelector('[data-booking-next]'),back=form.querySelector('[data-booking-back]'),submit=form.querySelector('[data-booking-submit]'),note=form.querySelector('[data-booking-step-note]');
 let current=0;form.noValidate=true;form.classList.add('booking-enhanced');
 form.querySelectorAll('[data-booking-controls]').forEach(el=>{el.hidden=false;});note.hidden=false;
 const fieldNames=['event_type','guests','event_date','event_time','location','name','email','phone','budget'];
 function validate(step){for(const field of steps[step].querySelectorAll('input,select,textarea'))if(!field.disabled&&!field.checkValidity()){field.focus();field.reportValidity();return false;}return true;}
 function summary(){const v=n=>form.elements[n]?.value||'';const target=form.querySelector('[data-booking-summary]');if(target)target.textContent=[v('event_type'),v('guests')+' guests',v('event_date'),v('event_time'),v('location')].filter(Boolean).join(' · ');}
 function show(step,focus=false){current=step;steps.forEach((s,i)=>{s.hidden=i!==step;});next.hidden=step===steps.length-1;back.hidden=step===0;submit.hidden=step!==steps.length-1;note.textContent=`Step ${step+1} of 2 · ${step===0?'Your event':'Your details'}`;form.querySelectorAll('[data-booking-goto]').forEach(b=>{if(Number(b.dataset.bookingGoto)===step)b.setAttribute('aria-current','step');else b.removeAttribute('aria-current');});const review=form.querySelector('[data-booking-review]');review.hidden=step!==1;summary();if(focus){const first=steps[step].querySelector('input,select,textarea');first?.focus({preventScroll:true});form.scrollIntoView({behavior:reduce?'auto':'smooth',block:'start'});}}
 next.addEventListener('click',()=>{if(validate(current))show(1,true);});back.addEventListener('click',()=>show(0,true));
 form.querySelectorAll('[data-booking-goto]').forEach(b=>b.addEventListener('click',()=>{const step=Number(b.dataset.bookingGoto);if(step<=current||validate(current))show(step,true);}));
 form.addEventListener('submit',e=>{if(current===0){e.preventDefault();if(validate(0))show(1,true);return;}for(let i=0;i<steps.length;i++)if(![...steps[i].querySelectorAll('input,select,textarea')].every(f=>f.disabled||f.checkValidity())){e.preventDefault();show(i);validate(i);return;}});
 show(0);
 const date=form.elements.event_date,time=form.elements.event_time,availability=document.getElementById('vi-book-availability');let seq=0;
 async function checkAvailability(){const id=++seq;if(!date.value||!time.value){availability.textContent='Choose a date and time to check availability.';return;}availability.textContent='Checking availability…';try{const q=new URLSearchParams({kind:form.elements.kind.value,date:date.value,time:time.value});const r=await fetch('/booking/availability?'+q,{cache:'no-store',credentials:'same-origin'});if(!r.ok)throw Error();const d=await r.json();if(id===seq)availability.textContent=d.available?'Open for requests · confirmation follows review.':'This time is unavailable. Please choose another.';}catch{if(id===seq)availability.textContent='Availability could not be checked. Our team will review your request.';}}
 date.addEventListener('change',checkAvailability);time.addEventListener('change',checkAvailability);
})();
