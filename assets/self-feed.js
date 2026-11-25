(() => {
 const form=document.querySelector('[data-ajax-feed]'),results=document.getElementById('self-feed-results');if(!form||!results)return;
 let controller,timer;
 const load=async url=>{controller?.abort();controller=new AbortController();results.setAttribute('aria-busy','true');try{const r=await fetch(url,{credentials:'same-origin',signal:controller.signal,headers:{'X-Requested-With':'XMLHttpRequest'}});if(!r.ok)throw new Error('Gagal memuat');const html=new DOMParser().parseFromString(await r.text(),'text/html');const next=html.getElementById('self-feed-results');if(!next)throw new Error('Hasil tidak ditemukan');results.replaceChildren(...next.children);history.replaceState(null,'',url)}catch(e){if(e.name!=='AbortError')console.error(e)}finally{results.removeAttribute('aria-busy')}};
 const filter=()=>{const u=new URL(form.action);new FormData(form).forEach((v,k)=>{if(v)u.searchParams.set(k,v)});load(u.href)};
 form.addEventListener('submit',e=>{e.preventDefault();filter()});
 form.querySelector('[name="q"]')?.addEventListener('input',()=>{clearTimeout(timer);timer=setTimeout(filter,280)});
 form.querySelectorAll('select').forEach(el=>el.addEventListener('change',filter));
 results.addEventListener('click',e=>{const link=e.target.closest('a[href]');if(link&&link.closest('.metro-pager')){e.preventDefault();load(link.href)}});
})();
