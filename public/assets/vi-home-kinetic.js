(()=>{'use strict';
const start=()=>{
 if(!document.body.classList.contains('vi-home-page'))return;
 const reduce=window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches;
 const word=document.querySelector('[data-vi-rotating-word]');
 const words=['gathering for.','craving for.','coming back for.','celebrating over.','sharing together.'];
 if(word&&!reduce){
  let index=0,waiting=false;
  const change=()=>{
   if(waiting||document.hidden)return;waiting=true;
   word.classList.remove('vi-word-enter');word.classList.add('vi-word-exit');
   window.setTimeout(()=>{
    index=(index+1)%words.length;word.textContent=words[index];
    word.classList.remove('vi-word-exit');
    void word.offsetWidth;
    word.classList.add('vi-word-enter');
    window.setTimeout(()=>{word.classList.remove('vi-word-enter');waiting=false},590);
   },350);
  };
  window.setInterval(change,3200);
 }
 // Never hide sections. Animate with the Web Animations API only after intersection.
 if(reduce||!('IntersectionObserver' in window)||!Element.prototype.animate)return;
 const selectors=['.section-head h2','.section-head .eyebrow','.section-head p','.services-grid .service-tile','.dark-section .split>div','.dark-section .split>img','.home-featured-grid .food-tile','.vi-track-home'];
 const nodes=[...new Set(selectors.flatMap(q=>Array.from(document.querySelectorAll(q))))];
 const seen=new WeakSet();
 const observer=new IntersectionObserver(entries=>{
  entries.forEach(entry=>{
   if(!entry.isIntersecting)return;
   const el=entry.target;observer.unobserve(el);
   if(seen.has(el))return;seen.add(el);
   const parent=el.parentElement;
   const siblings=parent?Array.from(parent.children).filter(n=>n.matches&&n.matches('.service-tile,.food-tile')):[];
   const offset=Math.max(0,siblings.indexOf(el))*95;
   try{el.animate([{opacity:.35,transform:'translate3d(0,26px,0)'},{opacity:1,transform:'translate3d(0,0,0)'}],{duration:690,delay:offset,easing:'cubic-bezier(.2,.75,.2,1)',fill:'none'});}catch(e){}
  });
 },{threshold:.08,rootMargin:'0px 0px -4% 0px'});
 nodes.forEach(el=>observer.observe(el));
};
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',start,{once:true});else start();
})();