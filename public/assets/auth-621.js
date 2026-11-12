/* Reveal only the explicitly associated password input; never persist its value. */
(() => {
 'use strict';
 document.querySelectorAll('[data-password-toggle]').forEach(box=>{
  const input=document.getElementById(box.getAttribute('aria-controls'));
  if(!input||input.type!=='password'||input.form!==box.form)return;
  const mask=()=>{box.checked=false;input.type='password';};
  box.closest('label').hidden=false;
  box.addEventListener('change',()=>{input.type=box.checked?'text':'password';});
  box.form?.addEventListener('submit',mask);
  box.form?.addEventListener('reset',mask);
  window.addEventListener('pagehide',mask);
  window.addEventListener('pageshow',mask);
 });
})();
