/* Native links still open each photograph without JavaScript. */
(() => {
 document.querySelectorAll('[data-product-gallery]').forEach(gallery=>{
  const main=gallery.querySelector('[data-gallery-main]'),links=[...gallery.querySelectorAll('[data-gallery-image]')],counter=gallery.querySelector('[data-gallery-count]');
  function select(link,focus=false){main.src=link.href;main.alt=link.dataset.imageAlt;links.forEach(item=>{if(item===link)item.setAttribute('aria-current','true');else item.removeAttribute('aria-current');});if(counter)counter.textContent=`Image ${links.indexOf(link)+1} of ${links.length}`;if(focus)link.focus();}
  links.forEach((link,i)=>{link.addEventListener('click',event=>{if(event.ctrlKey||event.metaKey||event.shiftKey||event.altKey)return;event.preventDefault();select(link);});link.addEventListener('keydown',event=>{let next;if(event.key==='ArrowRight')next=(i+1)%links.length;if(event.key==='ArrowLeft')next=(i-1+links.length)%links.length;if(event.key==='Home')next=0;if(event.key==='End')next=links.length-1;if(next!==undefined){event.preventDefault();select(links[next],true);}});});
 });
})();
