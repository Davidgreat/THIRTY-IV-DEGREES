(()=>{'use strict';
const start=()=>{if(!document.body.classList.contains('vi-home-page'))return;
if(!('IntersectionObserver' in window)||!Element.prototype.animate||matchMedia('(prefers-reduced-motion: reduce)').matches)return;
const groups=['.vi-home-page .section h2','.vi-home-page .section .eyebrow','.vi-home-page .section .section-intro','.vi-home-page .section .card','.vi-home-page .section .service-card','.vi-home-page .section .food-tile','.vi-home-page .section .btn','.vi-home-page .section .feature','.vi-home-page .section .story-card'];
const nodes=[...new Set(groups.flatMap(s=>[...document.querySelectorAll(s)]))].filter(e=>!e.closest('.hero,.site-footer')&&e.getBoundingClientRect().width>0);
const observer=new IntersectionObserver((entries)=>{for(const e of entries){if(!e.isIntersecting)continue;observer.unobserve(e.target);const el=e.target;if(el.dataset.viMotionDone)return;el.dataset.viMotionDone='1';
try{el.animate([{opacity:0.78,transform:'translate3d(0,18px,0)'},{opacity:1,transform:'translate3d(0,0,0)'}],{duration:620,easing:'cubic-bezier(.2,.8,.2,1)',fill:'none'});}catch(_){/* content stays visible */}
}},{rootMargin:'0px 0px -8% 0px',threshold:0.12});
nodes.forEach(n=>observer.observe(n));
};if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',start,{once:true});else start();
})();