(()=>{'use strict';const panel=document.querySelector('[data-favourites-panel]');if(!panel)return;
const key='thirtyvi-favourite-products';
const get=()=>{try{const x=JSON.parse(localStorage.getItem(key)||'[]');return Array.isArray(x)?[...new Set(x.map(Number).filter(n=>Number.isSafeInteger(n)&&n>0))].slice(0,30):[]}catch(e){return []}};
const escape=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const empty=()=>{panel.innerHTML='<div class="vi-dash-fav-empty">No saved meals yet. Tap the ♡ on any dish to keep it here. <a href="/menu">Explore the menu →</a></div>'};
const load=async()=>{const ids=get();if(!ids.length){empty();return}try{const r=await fetch('/favourites/products?ids='+encodeURIComponent(ids.join(',')),{credentials:'same-origin',cache:'no-store'});if(!r.ok)throw Error('Request failed');const data=await r.json();if(!Array.isArray(data.products)||!data.products.length){empty();return}
panel.innerHTML=data.products.map(p=>`<article class="vi-dash-fav"><a href="${escape(p.url)}"><img loading="lazy" src="${escape(p.image)}" alt="${escape(p.name)}"></a><div class="vi-dash-fav-content"><strong>${escape(p.name)}</strong><small>${escape(p.price)}</small><div class="vi-dash-fav-actions"><a href="${escape(p.url)}">View dish →</a><button type="button" data-remove-favourite="${Number(p.id)}" aria-label="Remove ${escape(p.name)} from favourites">Remove ×</button></div></div></article>`).join('');
}catch(e){panel.innerHTML='<div class="vi-dash-fav-empty">Saved meals could not load right now. Please refresh to retry.</div>'}};
panel.addEventListener('click',e=>{const b=e.target.closest('[data-remove-favourite]');if(!b)return;const id=Number(b.dataset.removeFavourite);try{localStorage.setItem(key,JSON.stringify(get().filter(x=>x!==id)))}catch(e){}load()});
load();window.addEventListener('storage',e=>{if(e.key===key)load()});
})();