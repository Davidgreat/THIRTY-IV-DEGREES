/* Availability is a server setting, not an indication of a user's online presence. */
(() => {
 const badges=[...document.querySelectorAll('[data-chef-status]')];
 const form=document.querySelector('[data-booking-wizard]');
 const kind=form?.elements.kind?.value;
 if(!badges.length)return;
 let timer;
 const render=available=>{
  badges.forEach(b=>{b.dataset.available=available?'1':'0';b.querySelector('[data-chef-label]').textContent=available?'Chef active · Accepting inquiries':'Chef paused · Inquiries closed';});
  if(form&&kind==='chef'){
   const notice=document.querySelector('[data-chef-pause-notice]');if(notice)notice.hidden=available;
   const wasPaused=form.dataset.available==='0';form.dataset.available=available?'1':'0';
   if(available&&wasPaused)form.elements.event_date?.dispatchEvent(new Event('change'));
   form.querySelectorAll('[data-booking-step]').forEach(f=>{f.disabled=!available;});
   form.querySelectorAll('[data-booking-next],[data-booking-submit]').forEach(b=>{b.disabled=!available;});
   const submit=form.querySelector('[data-booking-submit]');if(submit)submit.textContent=available?'Send booking request →':'Bookings paused';
   if(!available){const note=document.getElementById('vi-book-availability');if(note)note.textContent='Private chef inquiries are currently paused. Please check back soon.';}
  }
 };
 const refresh=async()=>{if(document.hidden)return;try{const r=await fetch('/chef/status',{cache:'no-store',credentials:'same-origin'});if(r.ok){const d=await r.json();if(typeof d.available==='boolean')render(d.available);}}catch{/* Keep the last confirmed server state on a network failure. */}};
 const schedule=()=>{clearInterval(timer);if(!document.hidden){refresh();timer=setInterval(refresh,20000);}};
 document.addEventListener('visibilitychange',schedule);window.addEventListener('pageshow',schedule);schedule();
})();
