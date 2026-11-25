(() => {
 const input=document.getElementById('counterpart-search');if(!input)return;
 const id=document.getElementById('counterpart-id'),list=document.getElementById('counterpart-results'),preview=document.getElementById('counterpart-preview');
 let timer,controller;
 input.addEventListener('input',()=>{id.value='';preview.hidden=true;list.hidden=true;clearTimeout(timer);controller?.abort();const q=input.value.trim();if(q.length<2)return;
  timer=setTimeout(async()=>{controller=new AbortController();try{const r=await fetch(input.dataset.url+'?q='+encodeURIComponent(q),{signal:controller.signal,credentials:'same-origin'});if(!r.ok)return;const rows=await r.json();list.replaceChildren();for(const row of rows){const button=document.createElement('button');button.type='button';button.className='counterpart-option';button.setAttribute('role','option');const strong=document.createElement('strong');strong.textContent=row.label;const small=document.createElement('small');small.textContent=row.detail;button.append(strong,small);button.addEventListener('click',()=>{id.value=row.id;input.value=row.label;preview.textContent=row.label+' · '+row.detail;preview.hidden=false;list.hidden=true});list.appendChild(button)}if(!rows.length)list.textContent='Tidak ada akun yang sesuai dan telah disetujui.';list.hidden=false}catch(e){if(e.name!=='AbortError')list.hidden=true}},230)
 });
 input.closest('form')?.addEventListener('submit',e=>{if(!id.value){e.preventDefault();input.focus()}});
})();
