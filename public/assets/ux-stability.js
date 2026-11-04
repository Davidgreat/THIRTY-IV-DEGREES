/* V5.5.5: navigation accessibility and checkout submit feedback. */
(function(){'use strict';
function init(){
 var nav=document.querySelector('.topbar nav'),toggle=document.querySelector('.topbar .hamb');
 if(nav){var current=location.pathname.replace(/\/$/,'')||'/';nav.querySelectorAll('a[href]').forEach(function(a){var href=a.getAttribute('href');if(href===current||(href==='/admin'&&current.startsWith('/admin/')))a.setAttribute('aria-current','page')});}
 if(nav&&toggle){toggle.setAttribute('aria-expanded',nav.classList.contains('show')?'true':'false');toggle.setAttribute('aria-controls','vi-primary-nav');nav.id='vi-primary-nav';
 toggle.addEventListener('click',function(){requestAnimationFrame(function(){toggle.setAttribute('aria-expanded',nav.classList.contains('show')?'true':'false')})});
 nav.addEventListener('click',function(e){if(e.target.closest('a')&&nav.classList.contains('show')){nav.classList.remove('show');toggle.setAttribute('aria-expanded','false')}});
 document.addEventListener('keydown',function(e){if(e.key==='Escape'&&nav.classList.contains('show')){nav.classList.remove('show');toggle.setAttribute('aria-expanded','false');toggle.focus()}});
 }

}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();
