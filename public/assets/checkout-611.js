/* Make checkout failures visible, including inputs inside collapsed details. */
(() => {
 const form=document.getElementById('checkout-form');if(!form)return;
 const summary=document.getElementById('checkout-errors');
 const buttons=[...document.querySelectorAll('#checkout-form button[type="submit"],button[form="checkout-form"]')];
 buttons.forEach(b=>{b.dataset.checkoutLabel=b.innerHTML;});
 const reset=()=>buttons.forEach(b=>{b.disabled=false;b.removeAttribute('aria-busy');b.innerHTML=b.dataset.checkoutLabel;});
 window.addEventListener('pageshow',reset);
 const invalidFields=()=>[...form.elements].filter(e=>e.willValidate&&!e.checkValidity());
 const show=fields=>{
  if(!summary)return;
  const list=summary.querySelector('ul');list.replaceChildren();
  fields.forEach(field=>{field.setAttribute('aria-invalid','true');const label=field.id?form.querySelector(`label[for="${CSS.escape(field.id)}"]`):null;const li=document.createElement('li');li.textContent=(label?.textContent.replace(/\*/g,'').trim()||'Order details')+': '+field.validationMessage;list.appendChild(li);});
  summary.hidden=false;summary.focus();summary.scrollIntoView({block:'start',behavior:'auto'});
  const first=fields[0];if(first){let parent=first.parentElement;while(parent&&parent!==form){if(parent.tagName==='DETAILS')parent.open=true;parent=parent.parentElement;}}
 };
 // Capture before older submit listeners disable the buttons.
 form.noValidate=true;
 form.addEventListener('submit',e=>{
  const fields=invalidFields();if(fields.length){e.preventDefault();e.stopImmediatePropagation();reset();show(fields);return;}
  if(summary)summary.hidden=true;
 },true);
 form.addEventListener('input',e=>{if(e.target.willValidate&&e.target.checkValidity())e.target.removeAttribute('aria-invalid');if(summary&&!summary.hidden&&invalidFields().length===0)summary.hidden=true;});
 if(summary&&!summary.hidden){summary.focus();summary.scrollIntoView({block:'start'});const schedule=form.elements.scheduled_for;if(schedule?.value)form.querySelector('[data-schedule]').open=true;}
})();
