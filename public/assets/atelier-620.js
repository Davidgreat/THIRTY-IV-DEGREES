/* Presentation only. Checkout submission and payment endpoints remain independent. */
(() => {
 'use strict';
 const reduced=matchMedia('(prefers-reduced-motion: reduce)');
 const phrase=document.querySelector('[data-hero-phrase]'),control=document.querySelector('[data-hero-motion]');
 const words=['gathering for.','craving.','celebrating.','sharing.','savouring.','coming home for.'];
 let queue=[],timer,paused=false;
 function nextWord(){
  if(!queue.length){queue=[...words];for(let i=queue.length-1;i>0;i--){const j=Math.floor(Math.random()*(i+1));[queue[i],queue[j]]=[queue[j],queue[i]];}if(queue[0]===phrase.textContent)[queue[0],queue[1]]=[queue[1],queue[0]];}
  phrase.textContent=queue.shift();phrase.classList.remove('phrase-enter');void phrase.offsetWidth;phrase.classList.add('phrase-enter');
 }
 function sync(){clearInterval(timer);if(control){control.hidden=reduced.matches;control.setAttribute('aria-pressed',String(paused));control.textContent=paused?'Play words ▷':'Pause words Ⅱ';}if(phrase&&!paused&&!reduced.matches&&!document.hidden)timer=setInterval(nextWord,3600);}
 if(phrase){control?.addEventListener('click',()=>{paused=!paused;sync();});document.addEventListener('visibilitychange',sync);reduced.addEventListener('change',sync);sync();}
 // No hidden content or scroll interception: a brief entrance when a section first appears.
 if(!reduced.matches&&'IntersectionObserver' in window){const observer=new IntersectionObserver(entries=>{entries.forEach(entry=>{if(entry.isIntersecting){entry.target.classList.add('atelier-arrive');observer.unobserve(entry.target);}});},{threshold:.12});document.querySelectorAll('.home-experiences,.home-favourites,.hospitality-note,.booking-intro,.customer-welcome').forEach(el=>observer.observe(el));}
 // Keep the order recap out of the way on phones; native details is still keyboard accessible.
 const sections=[...document.querySelectorAll('[data-checkout-section]')],advance=document.querySelector('[data-checkout-details-next]');
 if(sections.length===2&&matchMedia('(max-width:767px)').matches){sections[1].open=false;if(advance){advance.hidden=false;advance.addEventListener('click',()=>{const invalid=[...sections[0].querySelectorAll('input,select,textarea')].find(f=>f.willValidate&&!f.checkValidity());if(invalid){invalid.reportValidity();return;}sections[0].open=false;sections[1].open=true;sections[1].querySelector('input')?.focus({preventScroll:true});sections[1].scrollIntoView({block:'start',behavior:reduced.matches?'auto':'smooth'});});}}
 const recap=document.querySelector('.checkout-order-card');if(recap&&matchMedia('(max-width:767px)').matches)recap.open=false;
})();
