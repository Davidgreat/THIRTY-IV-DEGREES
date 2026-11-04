(()=>{'use strict';
const init=()=>{
 if(!document.body.classList.contains('vi-home-page'))return;
 const word=document.querySelector('[data-vi-rotating-word]');
 const words=['gathering for.','craving for.','coming back for.','celebrating over.','sharing together.'];
 const reduce=!!(window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches);
 // Copy rotation is content, not optional motion: rotate even when motion is reduced.
 if(word&&!reduce){let i=0,busy=false;
  window.setInterval(()=>{
   if(document.hidden||busy)return;busy=true;
   const next=()=>{i=(i+1)%words.length;word.textContent=words[i];busy=false};
   if(reduce||typeof word.animate!=='function'){next();return}
   try{const out=word.animate([{opacity:1,transform:'translateY(0)'},{opacity:0,transform:'translateY(-.4em)'}],{duration:230,easing:'ease-in'});
    out.finished.then(()=>{next();word.animate([{opacity:0,transform:'translateY(.45em)'},{opacity:1,transform:'translateY(0)'}],{duration:410,easing:'ease-out'});}).catch(next);
   }catch(_){next()}
  },3000);
 }
 if(reduce||typeof Element==='undefined'||!Element.prototype.animate)return;
 // Animate only visible, already-rendered elements. No CSS pre-hide or persistent transforms.
 const queries=['.services-grid .service-tile','.section h2','.section .eyebrow','.section .card','.section .food-tile','.section .btn','.vi-banner-group','.vi-track-home','.review-tile'];
 const nodes=[...new Set(queries.flatMap(q=>Array.from(document.querySelectorAll(q))))].filter(el=>el.getBoundingClientRect().width>0);
 const seen=new WeakSet();
 const reveal=(el)=>{if(seen.has(el))return;seen.add(el);
  try{el.animate([{opacity:.45,transform:'translateY(24px)'},{opacity:1,transform:'translateY(0)'}],{duration:720,easing:'cubic-bezier(.2,.8,.2,1)'});}catch(_){}
 };
 if(!('IntersectionObserver' in window)){nodes.forEach(reveal);return}
 const obs=new IntersectionObserver(entries=>{entries.forEach(entry=>{if(entry.isIntersecting){obs.unobserve(entry.target);reveal(entry.target)}})},{threshold:.05,rootMargin:'0px 0px -3% 0px'});
 nodes.forEach(el=>obs.observe(el));
};
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});else init();
})();