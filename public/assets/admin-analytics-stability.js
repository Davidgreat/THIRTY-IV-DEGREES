/* Non-invasive admin chart keyboard access and table-scroll guidance. */
(function(){'use strict';
function init(){var shell=document.querySelector('.admin-shell');if(!shell)return;
shell.querySelectorAll('.admin-chart-slot').forEach(function(slot){
 if(slot.hasAttribute('title')&&!slot.hasAttribute('aria-label'))slot.setAttribute('aria-label',slot.getAttribute('title'));
 slot.setAttribute('tabindex','0');slot.setAttribute('role','listitem');
});
shell.querySelectorAll('.admin-chart-bars').forEach(function(bars){bars.setAttribute('role','list');bars.setAttribute('aria-label','Revenue for each of the last 14 days');});
shell.querySelectorAll('.scroll').forEach(function(scroller){
 var table=scroller.querySelector('table');if(!table)return;
 if(!scroller.hasAttribute('tabindex'))scroller.setAttribute('tabindex','0');
 if(!scroller.hasAttribute('aria-label'))scroller.setAttribute('aria-label','Scrollable data table');
 if(scroller.previousElementSibling&&scroller.previousElementSibling.classList.contains('admin-scroll-hint'))return;
 var hint=document.createElement('p');hint.className='admin-scroll-hint';hint.textContent='Swipe sideways to view all table columns.';
 scroller.parentNode.insertBefore(hint,scroller);
});}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();
