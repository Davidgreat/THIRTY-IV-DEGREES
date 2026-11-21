(() => {
 const status=document.querySelector('[data-card-status]');let busy=false;
 document.querySelectorAll('[data-card-share]').forEach(button=>button.addEventListener('click',async()=>{
  if(busy)return;busy=true;const url=location.origin+'/digital-card';
  try{if(navigator.share){await navigator.share({title:document.title,url});status.textContent='Card shared.';}else if(navigator.clipboard&&window.isSecureContext){await navigator.clipboard.writeText(url);status.textContent='Card link copied. Ready to share.';}else{status.replaceChildren();const label=document.createElement('label');label.textContent='Copy this card link: ';const input=document.createElement('input');input.value=url;input.readOnly=true;input.style.cssText='max-width:100%;padding:10px';label.append(input);status.append(label);input.focus();input.select();}}
  catch(error){if(error.name!=='AbortError')status.textContent='Sharing could not finish. You can share '+url;}
  finally{busy=false;}
 }));
})();
