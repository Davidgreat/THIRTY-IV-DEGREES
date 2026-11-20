(() => {
 const note=document.querySelector('[data-wallet-refresh]'),token=document.querySelector('meta[name="customer-csrf"]')?.content;if(!note||!token)return;
 let busy=false,stopped=false,timer;
 async function refresh(){
  if(stopped||busy||document.hidden)return;busy=true;const controller=new AbortController(),timeout=setTimeout(()=>controller.abort(),35000);
  try{const response=await fetch('/account/action/wallet/refresh',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({csrf:token}),signal:controller.signal,cache:'no-store'});if(!response.ok)throw Error();const result=await response.json();
   if(result.credited||result.mode!==note.dataset.mode||String(result.balance)!==note.dataset.balance){location.reload();return;}
   note.textContent=result.message||'Automatic check complete. Unconfirmed payments remain pending; you can also check a payment below.';
  }catch{note.textContent='Automatic check could not connect. Use Check status below or try again shortly. Do not repeat a payment already debited.';}
  finally{clearTimeout(timeout);busy=false;}
 }
 timer=setTimeout(()=>{refresh();timer=setInterval(refresh,60000);},3000);window.addEventListener('pagehide',()=>{stopped=true;clearTimeout(timer);clearInterval(timer);},{once:true});
})();
