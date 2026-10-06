'use strict';
const root = document.body;
try { root.dataset.theme = localStorage.getItem('rice-theme') || 'light'; root.dataset.density = localStorage.getItem('rice-density') || 'compact'; if(localStorage.getItem('rice-nav') === 'collapsed' && innerWidth > 760) root.classList.add('nav-collapsed'); } catch (_) {}
function savePreference(key, value) { try { localStorage.setItem(key, value); } catch (_) {} }
document.getElementById('theme-toggle')?.addEventListener('click', () => { root.dataset.theme = root.dataset.theme === 'dark' ? 'light' : 'dark'; savePreference('rice-theme',root.dataset.theme); });
document.getElementById('density-toggle')?.addEventListener('click', () => { root.dataset.density = root.dataset.density === 'compact' ? 'comfortable' : 'compact'; savePreference('rice-density',root.dataset.density); });
const navToggle = document.getElementById('nav-toggle');
const overlay = document.querySelector('.nav-overlay');
const sidebar = document.getElementById('sidebar');
function updateNav(){if(sidebar)sidebar.inert=innerWidth<=760&&!root.classList.contains('nav-open');}
updateNav();window.addEventListener('resize',updateNav);
function closeNav(){root.classList.remove('nav-open');if(overlay)overlay.hidden=true;navToggle?.setAttribute('aria-expanded','false');updateNav();}
navToggle?.addEventListener('click', () => { if(innerWidth <= 760){const open=root.classList.toggle('nav-open');overlay.hidden=!open;navToggle.setAttribute('aria-expanded',String(open));updateNav();if(open)sidebar.querySelector('a')?.focus();}else{const collapsed=root.classList.toggle('nav-collapsed');savePreference('rice-nav',collapsed?'collapsed':'expanded');navToggle.setAttribute('aria-expanded',String(!collapsed));} });
overlay?.addEventListener('click',closeNav);
document.addEventListener('keydown', e=>{if(e.key==='Escape'&&!document.querySelector('dialog[open]')){closeNav();navToggle?.focus();}});
document.getElementById('print-voucher')?.addEventListener('click',async()=>{
    const logo=document.querySelector('.voucher-company-logo');
    if(logo)try{await logo.decode();}catch{}
    await document.fonts.ready;
    window.print();
});
const receiptVoucher=document.querySelector('.voucher[data-receipt="true"]');
if(receiptVoucher){
    window.addEventListener('beforeprint',()=>{
        const width=Number.parseInt(receiptVoucher.dataset.paper,10);
        const height=Math.max(50,Math.ceil(receiptVoucher.getBoundingClientRect().height*25.4/96+8));
        document.getElementById('voucher-paper-style').textContent='@page { size: '+width+'mm '+height+'mm; margin: 3mm; }';
    });
}
let searchSelectIndex=0;
function initializeSearchSelect(select){
    if(select.dataset.searchReady)return;
    select.dataset.searchReady='true';
    const index=searchSelectIndex++;
    const options=Array.from(select.options).filter(option=>option.value!=='').map(option=>({source:option,value:option.value,label:option.textContent,search:option.textContent.normalize('NFC').toLocaleLowerCase()}));
    const container=document.createElement('span');container.className='search-select';
    const input=document.createElement('input');input.type='text';input.className='search-select-input';input.autocomplete='off';input.spellcheck=false;input.required=select.required;
    input.placeholder=select.dataset.itemField==='rice_type_id'?t('Search rice type'):select.options[0].textContent+t(' — search');input.setAttribute('role','combobox');input.setAttribute('aria-autocomplete','list');input.setAttribute('aria-expanded','false');input.setAttribute('aria-label',select.closest('label').firstChild.textContent.trim());
    const list=document.createElement('span');list.className='search-select-menu';list.id='search-options-'+index;list.setAttribute('role','listbox');list.hidden=true;input.setAttribute('aria-controls',list.id);
    const status=document.createElement('span');status.className='search-select-status';status.id='search-status-'+index;status.setAttribute('role','status');input.setAttribute('aria-describedby',status.id);
    container.append(input,status);select.after(container);document.body.append(list);select.hidden=true;select.required=false;
    const sync=()=>{input.value=select.value?select.options[select.selectedIndex].textContent:'';input.setCustomValidity(select.value?'':t('Select an item from the list.'));};
    sync();input.defaultValue=input.value;
    let matches=[],active=-1;
    function close(){list.hidden=true;input.setAttribute('aria-expanded','false');input.removeAttribute('aria-activedescendant');status.textContent='';active=-1;}
    function highlight(position){active=position;Array.from(list.children).forEach((row,i)=>{row.classList.toggle('is-highlighted',i===active);row.setAttribute('aria-selected',String(row.dataset.value===select.value));});if(active>=0){input.setAttribute('aria-activedescendant',list.children[active].id);list.children[active].scrollIntoView({block:'nearest'});}else input.removeAttribute('aria-activedescendant');}
    function choose(option){if(option.source.disabled||option.source.hidden)return;select.value=option.value;sync();close();select.dispatchEvent(new Event('input',{bubbles:true}));select.dispatchEvent(new Event('change',{bubbles:true}));input.focus();}
    function positionMenu(){
        if(list.hidden)return;
        const rect=input.getBoundingClientRect(),viewport=window.visualViewport;
        const viewportTop=viewport?.offsetTop||0,viewportLeft=viewport?.offsetLeft||0;
        const viewportHeight=viewport?.height||innerHeight,viewportWidth=viewport?.width||innerWidth;
        if(rect.bottom<viewportTop||rect.top>viewportTop+viewportHeight||rect.right<viewportLeft||rect.left>viewportLeft+viewportWidth){close();return;}
        const width=Math.min(rect.width,viewportWidth-16);
        list.style.position='fixed';list.style.width=width+'px';list.style.left=Math.max(viewportLeft+8,Math.min(rect.left,viewportLeft+viewportWidth-width-8))+'px';list.style.right='auto';
        list.style.maxHeight=Math.min(240,viewportHeight-16)+'px';
        // Use the actual menu height: short lists should sit directly beside the input.
        const menuHeight=list.getBoundingClientRect().height;
        const below=Math.max(0,viewportTop+viewportHeight-rect.bottom-8),above=Math.max(0,rect.top-viewportTop-8);
        const openBelow=menuHeight<=below||below>=above;
        list.style.maxHeight=Math.max(0,Math.min(240,openBelow?below:above))+'px';
        const fittedHeight=list.getBoundingClientRect().height;
        list.style.top=(openBelow?rect.bottom+4:Math.max(viewportTop+8,rect.top-fittedHeight-4))+'px';
    }
    function show(query=''){
        const search=query.normalize('NFC').toLocaleLowerCase().trim();const filtered=options.filter(option=>!option.source.disabled&&!option.source.hidden&&option.search.includes(search));matches=filtered.slice(0,100);list.replaceChildren();
        matches.forEach((option,i)=>{const row=document.createElement('span');row.className='search-select-option';row.id=list.id+'-'+i;row.dataset.value=option.value;row.setAttribute('role','option');row.textContent=option.label;row.addEventListener('mousedown',event=>event.preventDefault());row.addEventListener('click',()=>choose(option));list.append(row);});
        if(!matches.length){const empty=document.createElement('span');empty.className='search-select-empty';empty.textContent=t('No matches found');list.append(empty);}
        list.hidden=false;positionMenu();input.setAttribute('aria-expanded','true');highlight(-1);status.textContent=filtered.length>100?t('Showing first 100 matches. Type to narrow the list.'):t('{count} matches',{count:filtered.length});
    }
    input.addEventListener('focus',()=>show());
    input.addEventListener('click',()=>{if(list.hidden)show();});
    input.addEventListener('input',()=>{select.value='';input.setCustomValidity(t('Select an item from the list.'));show(input.value);});
    input.addEventListener('keydown',event=>{
        if(event.key==='ArrowDown'||event.key==='ArrowUp'){event.preventDefault();if(list.hidden)show(select.value?'':input.value);if(matches.length)highlight(event.key==='ArrowDown'?Math.min(active+1,matches.length-1):Math.max(active-1,0));}
        else if(event.key==='Enter'&&!list.hidden){event.preventDefault();if(active>=0&&matches[active])choose(matches[active]);else if(matches.length===1)choose(matches[0]);}
        else if(event.key==='Escape'&&!list.hidden){event.preventDefault();event.stopPropagation();close();}
        else if(event.key==='Tab')close();
    });
    container.addEventListener('focusout',event=>{if(!container.contains(event.relatedTarget)&&!list.contains(event.relatedTarget))close();});
    const outsideClick=event=>{if(!container.isConnected){destroy();return;}if(!container.contains(event.target)&&!list.contains(event.target))close();};
    document.addEventListener('click',outsideClick);
    const onScroll=event=>{if(!container.isConnected){destroy();return;}if(!list.contains(event.target))positionMenu();};
    document.addEventListener('scroll',onScroll,true);window.addEventListener('resize',positionMenu);
    window.visualViewport?.addEventListener('resize',positionMenu);window.visualViewport?.addEventListener('scroll',positionMenu);
    const onReset=()=>setTimeout(()=>{sync();close();},0);
    select.form?.addEventListener('reset',onReset);
    function destroy(){
        close();list.remove();document.removeEventListener('click',outsideClick);document.removeEventListener('scroll',onScroll,true);window.removeEventListener('resize',positionMenu);
        window.visualViewport?.removeEventListener('resize',positionMenu);window.visualViewport?.removeEventListener('scroll',positionMenu);select.form?.removeEventListener('reset',onReset);
    }
    select.addEventListener('search-select:dispose',destroy,{once:true});
    select.addEventListener('search-select:refresh',()=>{if(!list.hidden)show(select.value?'':input.value);});
}
document.querySelectorAll('select[data-searchable]').forEach(initializeSearchSelect);
const stockForm = document.querySelector('.stock-form');
if(stockForm){
    const balances=JSON.parse(document.getElementById('balance-data').textContent);
    function preview(){
        const qty=Number(stockForm.elements.quantity.value||0);
        document.getElementById('quantity-preview').textContent=qty.toLocaleString(undefined,{maximumFractionDigits:3})+t(' တင်း');
        if(stockForm.dataset.kind==='purchase'){
            const amount=qty*Number(stockForm.elements.unit_price.value||0);
            document.getElementById('amount-preview').textContent=amount.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})+t(' MMK');
            const paid=Number(stockForm.elements.amount_paid.value||0);
            document.getElementById('credit-preview').textContent=Math.max(0,amount-paid).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})+t(' MMK');
            stockForm.elements.amount_paid.max=(Math.round(amount*100)/100).toFixed(2);
        }else{
            const w=stockForm.elements.warehouse_id.value,r=stockForm.elements.rice_type_id.value;
            const row=balances.find(b=>String(b.warehouse_id)===w&&String(b.rice_type_id)===r);
            document.getElementById('balance-preview').textContent=w&&r?Number(row?.quantity||0).toLocaleString(undefined,{maximumFractionDigits:3})+t(' တင်း'):t('Select warehouse & rice type');
        }
    }
    stockForm.addEventListener('input',preview);preview();
    document.getElementById('pay-in-full')?.addEventListener('click',()=>{stockForm.elements.amount_paid.value=(Number(stockForm.elements.quantity.value||0)*Number(stockForm.elements.unit_price.value||0)).toFixed(2);preview();});
}
const chartData=document.getElementById('chart-data');
if(chartData && typeof Chart!=='undefined'){
    const rows=JSON.parse(chartData.textContent);
    const dark=root.dataset.theme==='dark';
    const purchaseChart=new Chart(document.getElementById('purchase-chart'),{type:'bar',data:{labels:rows.map(r=>r.period),datasets:[{label:t('Purchase amount (MMK)'),data:rows.map(r=>Number(r.amount)),backgroundColor:getComputedStyle(root).getPropertyValue('--primary').trim(),borderRadius:3,maxBarThickness:32}]},options:{responsive:true,maintainAspectRatio:false,animation:matchMedia('(prefers-reduced-motion: reduce)').matches?false:{duration:300},plugins:{legend:{display:false},tooltip:{callbacks:{label:c=>Number(c.raw).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})+t(' MMK')}}},scales:{x:{grid:{display:false},ticks:{color:dark?'#96a9b2':'#70818a',font:{size:10}},border:{display:false}},y:{beginAtZero:true,grid:{color:dark?'#2c3b44':'#eef2f3'},border:{display:false},ticks:{color:dark?'#96a9b2':'#70818a',font:{size:10},callback:v=>Number(v).toLocaleString(undefined,{notation:'compact'})}}}}});
    document.getElementById('theme-toggle')?.addEventListener('click',()=>{const style=getComputedStyle(root);purchaseChart.data.datasets[0].backgroundColor=style.getPropertyValue('--primary').trim();purchaseChart.options.scales.x.ticks.color=style.getPropertyValue('--muted').trim();purchaseChart.options.scales.y.ticks.color=style.getPropertyValue('--muted').trim();purchaseChart.options.scales.y.grid.color=style.getPropertyValue('--border').trim();purchaseChart.update('none');});
}
const userForm=document.getElementById('user-form');
const recordDialog=document.getElementById('record-dialog');
if(recordDialog){
    const recordForm=document.getElementById('record-form');
    const openButton=document.querySelector('[data-open-record]');
    const showRecord=()=>{recordDialog.showModal();root.classList.add('modal-open');};
    openButton?.addEventListener('click',()=>{
        recordForm.reset();recordForm.elements.id.value='0';
        recordForm.querySelectorAll('input:not([type="hidden"])').forEach(input=>input.value='');
        const singular=recordDialog.dataset.singular;
        document.getElementById('record-dialog-title').textContent=t('Add {type}',{type:t(singular)});
        document.getElementById('record-form-help').textContent=t('Create a new {type} for your business.',{type:t(singular)});
        const submit=document.getElementById('record-submit');submit.textContent=t('Create {type}',{type:t(singular)});submit.disabled=false;delete submit.dataset.originalText;
        recordDialog.querySelector('.dialog-error')?.remove();
        showRecord();
    });
    recordDialog.querySelectorAll('[data-close-record]').forEach(button=>button.addEventListener('click',()=>recordDialog.close()));
    recordDialog.addEventListener('click',event=>{if(event.target===recordDialog){const rect=recordDialog.getBoundingClientRect();if(event.clientX<rect.left||event.clientX>rect.right||event.clientY<rect.top||event.clientY>rect.bottom)recordDialog.close();}});
    recordDialog.addEventListener('close',()=>{root.classList.remove('modal-open');openButton?.focus();});
    if(recordDialog.dataset.autoOpen==='true')showRecord();
}
if(userForm){
    document.querySelectorAll('.edit-user').forEach(button=>button.addEventListener('click',()=>{
        const user=JSON.parse(button.dataset.user);userForm.reset();
        ['id','name','email'].forEach(key=>userForm.elements[key].value=user[key]);
        userForm.elements.active.checked=Number(user.active)===1;
        userForm.elements.password.required=false;
        const granted=JSON.parse(user.permissions);
        userForm.querySelectorAll('[name="permissions[]"]').forEach(box=>box.checked=granted.includes(box.value));
        document.getElementById('user-form-title').textContent=t('Edit staff account');
        document.getElementById('password-help').textContent=t('Leave password empty to keep the current password.');
        document.getElementById('user-editor').scrollIntoView({behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'instant':'smooth'});
        userForm.elements.name.focus();
    }));
    document.getElementById('reset-user').addEventListener('click',()=>{userForm.reset();userForm.elements.id.value=0;userForm.elements.password.required=true;document.getElementById('user-form-title').textContent=t('Add staff account');document.getElementById('password-help').textContent=t('Password must contain 10–72 characters.');});
}
document.querySelectorAll('form[method="post"]:not([data-purchase-wizard])').forEach(form=>form.addEventListener('submit',(event)=>{
    if(event.defaultPrevented)return;
    if(form.dataset.confirm && !window.confirm(form.dataset.confirm)){event.preventDefault();return;}
    const submit=form.querySelector('[type="submit"]');if(submit){submit.dataset.originalText=submit.textContent;submit.disabled=true;submit.textContent=t('Saving…');}
}));
window.addEventListener('pageshow',()=>{document.querySelectorAll('[data-original-text]').forEach(button=>{button.disabled=false;button.textContent=button.dataset.originalText;});});
