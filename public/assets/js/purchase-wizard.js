'use strict';
document.addEventListener('DOMContentLoaded',()=>{
    const form=document.querySelector('[data-purchase-wizard]');
    if(!form)return;
    const production=form.dataset.operation==='production';
    const transfer=form.dataset.operation==='transfer',stockOnly=production||transfer;
    const balances=new Map(stockOnly?JSON.parse(document.getElementById('stock-balance-data').textContent).map(balance=>[String(balance.warehouse_id)+':'+String(balance.rice_type_id),balance.quantity]):[]);
    const panes=Array.from(form.querySelectorAll('.purchase-step'));
    const steps=Array.from(form.querySelectorAll('[data-go-step]'));
    const body=document.getElementById('purchase-items');
    const next=document.getElementById('purchase-next'),back=document.getElementById('purchase-back'),save=document.getElementById('purchase-save');
    const error=document.getElementById('wizard-error');
    const maxItems=Number(form.dataset.maxItems);
    let step=0,nextIndex=body.children.length,total=0n;
    const rows=()=>Array.from(body.querySelectorAll('.purchase-item'));
    const field=(row,name)=>row.querySelector('[data-item-field="'+name+'"]');
    function scaled(value,places){
        if(!new RegExp('^\\d{1,18}(?:\\.\\d{1,'+places+'})?$').test(value))return 0n;
        const [whole,fraction='']=value.split('.');return BigInt(whole+fraction.padEnd(places,'0'));
    }
    function decimal(value,places){const digits=value.toString().padStart(places+1,'0');return digits.slice(0,-places)+'.'+digits.slice(-places);}
    function money(value){const digits=decimal(value,2).split('.');return BigInt(digits[0]).toLocaleString('en-US')+'.'+digits[1];}
    function quantity(value){return value<0n?'-'+quantity(-value):decimal(value,3).replace(/\.?0+$/,'');}
    function selected(select){return select.value?select.options[select.selectedIndex].textContent:'';}
    function lineAmount(row){return (scaled(field(row,'quantity').value,3)*scaled(field(row,'unit_price').value,2)+500n)/1000n;}
    function stockBalance(warehouse,row){
        const value=String(balances.get(warehouse+':'+field(row,'rice_type_id').value)||'0');
        // Edit previews remove the original operation; a destination can be below zero
        // until the replacement transfer is added back.
        return value.startsWith('-')?-scaled(value.slice(1),3):scaled(value,3);
    }
    function available(row){return stockBalance(form.elements.warehouse_id.value,row);}
    function destinationBalance(row){return stockBalance(form.elements.destination_id.value,row);}
    function update(){
        total=0n;let qty=0n;
        if(transfer){
            const destination=form.elements.destination_id;
            destination.setCustomValidity(destination.value&&destination.value===form.elements.warehouse_id.value?t('Choose two different warehouses.'):'');
            Array.from(destination.options).forEach(option=>{option.disabled=Boolean(option.value&&option.value===form.elements.warehouse_id.value);option.hidden=option.disabled;});
        }
        const riceSelections=rows().map(row=>field(row,'rice_type_id').value).filter(Boolean);
        rows().forEach((row,index)=>{
            const riceSelect=field(row,'rice_type_id');
            const selectedElsewhere=new Set(rows().filter(other=>other!==row).map(other=>field(other,'rice_type_id').value).filter(Boolean));
            Array.from(riceSelect.options).forEach(option=>{option.disabled=option.value!==''&&selectedElsewhere.has(option.value);option.hidden=option.disabled;});
            const duplicate=riceSelect.value!==''&&selectedElsewhere.has(riceSelect.value);
            row.querySelector('.search-select-input')?.setCustomValidity(duplicate?t('This rice type is already selected in another item.'):(riceSelect.value?'':t('Select an item from the list.')));
            riceSelect.dispatchEvent(new Event('search-select:refresh'));
            (stockOnly?['quantity']:['quantity','weight_lb','unit_price']).forEach(name=>{
                const control=field(row,name),places=name==='unit_price'?2:3;
                control.setCustomValidity(control.value!==''&&!new RegExp('^\\d{1,9}(?:\\.\\d{1,'+places+'})?$').test(control.value)?t('Enter a number with up to {places} decimal places.',{places}):'');
            });
            row.querySelector('.item-number').textContent=index+1;
            const used=scaled(field(row,'quantity').value,3);qty+=used;
            if(stockOnly){
                const stock=available(row),chosen=Boolean(riceSelect.value&&form.elements.warehouse_id.value);
                const quantityInput=field(row,'quantity');quantityInput.max=chosen?decimal(stock<0n?0n:(stock<999999999999n?stock:999999999999n),3):'999999999.999';
                if(chosen&&used>stock)quantityInput.setCustomValidity(t('Insufficient stock in the selected warehouse for this rice type.'));
                if(transfer&&riceSelect.value&&form.elements.destination_id.value&&destinationBalance(row)+used<0n)quantityInput.setCustomValidity(t('This reduction would leave insufficient stock in the destination warehouse.'));
                row.querySelector('.item-available').textContent=chosen?quantity(stock):'—';
                row.querySelector('.item-remaining').textContent=chosen?quantity(stock-used):'—';
                row.querySelector('.item-remaining').classList.toggle('stock-shortage',chosen&&used>stock);
                if(transfer)row.querySelector('.item-destination').textContent=riceSelect.value&&form.elements.destination_id.value?quantity(destinationBalance(row)+used):'—';
            }else{
                const amount=lineAmount(row);total+=amount;row.querySelector('.item-amount').textContent=money(amount);
            }
            const remove=row.querySelector('.remove-item');remove.disabled=rows().length===1;remove.setAttribute('aria-label',t('Remove rice item {number}',{number:index+1}));
        });
        let paid=0n;
        if(!stockOnly){
        paid=scaled(form.elements.amount_paid.value,2);
        form.elements.amount_paid.setCustomValidity(form.elements.amount_paid.value!==''&&!/^\d{1,18}(?:\.\d{1,2})?$/.test(form.elements.amount_paid.value)?t('Enter a payment with up to 2 decimal places.'):'');
        form.elements.amount_paid.max=decimal(total,2);
        }
        document.getElementById('wizard-item-count').textContent=rows().length;
        document.getElementById('item-count').textContent=t('{count} rice item(s)',{count:rows().length});
        document.getElementById('wizard-quantity').textContent=quantity(qty)+t(' တင်း');
        if(!stockOnly){
        document.getElementById('wizard-amount').textContent=money(total)+t(' MMK');
        document.getElementById('wizard-paid').textContent=money(paid)+t(' MMK');
        document.getElementById('wizard-credit').textContent=money(total>paid?total-paid:0n)+t(' MMK');
        }
        const warehouse=selected(form.elements.warehouse_id);
        const supplier=stockOnly?'':selected(form.elements.supplier_id);
        const destination=transfer?selected(form.elements.destination_id):'';
        document.getElementById('summary-destination').textContent=transfer?(warehouse&&destination?warehouse+' → '+destination:t('Choose source and destination warehouses.')):(production?(warehouse||t('Choose a source warehouse.')):(supplier&&warehouse?supplier+' → '+warehouse:t('Choose a supplier and receiving warehouse.')));
        const availableRice=Array.from(field(rows()[0],'rice_type_id').options).filter(option=>option.value!=='').length;
        form.querySelector('.add-item').disabled=rows().length>=maxItems||new Set(riceSelections).size>=availableRice||form.dataset.ready!=='true';
        if(step===2)review();
    }
    function review(){
        const target=document.getElementById('purchase-review-items');target.replaceChildren();
        form.querySelector('.review-details').textContent=form.elements.occurred_on.value+' · '+(stockOnly?'':selected(form.elements.supplier_id)+' · ')+selected(form.elements.warehouse_id)+(transfer?' → '+selected(form.elements.destination_id):'');
        if(stockOnly)form.querySelector('.review-notes').textContent=form.elements.notes.value;
        rows().forEach(row=>{
            const tr=document.createElement('tr');
            const used=scaled(field(row,'quantity').value,3);
            const cells=stockOnly?[selected(field(row,'rice_type_id')),quantity(available(row)),quantity(used),quantity(available(row)-used)]:[selected(field(row,'rice_type_id')),quantity(used),field(row,'weight_lb').value?quantity(scaled(field(row,'weight_lb').value,3)):'—',money(scaled(field(row,'unit_price').value,2)),money(lineAmount(row))];
            if(transfer)cells.push(quantity(destinationBalance(row)),quantity(destinationBalance(row)+used));
            cells.forEach((text,index)=>{
                const td=document.createElement('td');td.textContent=text;if(index)td.className='number';tr.append(td);
            });target.append(tr);
        });
    }
    function show(position,focus=true){
        step=position;
        form.dataset.currentStep=String(step);
        panes.forEach((pane,index)=>pane.hidden=index!==step);
        steps.forEach((button,index)=>{
            const current=index===step,complete=index<step;
            button.classList.toggle('is-active',current);button.classList.toggle('is-complete',complete);
            button.parentElement.classList.toggle('is-reached',index<=step);
            button.querySelector('.wizard-step-status').textContent=current?t('Current'):(complete?t('Done'):t('Next'));
            button.setAttribute('aria-label',button.dataset.stepLabel+'. '+(current?t('Current step'):(complete?t('Completed step'):t('Upcoming step'))));
            if(current)button.setAttribute('aria-current','step');else button.removeAttribute('aria-current');
        });
        back.hidden=step===0;next.hidden=step===2;save.hidden=step!==2;
        next.textContent=step===0?t('Continue to rice items'):(transfer?t('Review transfer'):(production?t('Review production'):t('Review purchase')));
        error.hidden=true;
        if(step===2)review();
        if(focus)document.getElementById('purchase-step-'+step).focus();
    }
    function valid(position){
        for(const control of panes[position].querySelectorAll('input,select,textarea')){
            if(control.hidden||control.disabled||control.type==='hidden')continue;
            if(!control.checkValidity()){show(position);control.reportValidity();return false;}
        }
        if(position===1 && total>99999999999999999999n){show(position);error.textContent=t('Purchase total must not exceed 999,999,999,999,999,999.99 MMK.');error.hidden=false;return false;}
        return true;
    }
    function move(position){
        update();if(position>step){for(let index=0;index<position;index++)if(!valid(index))return;}
        show(position);
    }
    next.addEventListener('click',()=>move(step+1));back.addEventListener('click',()=>move(step-1));
    steps.forEach(button=>button.addEventListener('click',()=>move(Number(button.dataset.goStep))));
    form.querySelector('.add-item').addEventListener('click',()=>{
        if(rows().length>=maxItems)return;
        const template=document.getElementById('purchase-item-template');
        const fragment=template.content.cloneNode(true),row=fragment.querySelector('tr');
        row.dataset.index=nextIndex;
        row.querySelectorAll('[name]').forEach(control=>control.name=control.name.replace('__INDEX__',String(nextIndex)));
        nextIndex++;body.append(fragment);row.querySelectorAll('select[data-searchable]').forEach(initializeSearchSelect);update();
        row.querySelector('.search-select-input,select').focus();
    });
    body.addEventListener('click',event=>{
        const button=event.target.closest('.remove-item');if(!button||rows().length===1)return;
        const row=button.closest('.purchase-item');
        const entered=Array.from(row.querySelectorAll('[data-item-field]')).some(control=>control.value!=='');
        if(entered&&!window.confirm(t('Remove this rice item from the {operation}?',{operation:t(transfer?'Transfer':(production?'Production':'Purchase'))})))return;
        const previous=row.previousElementSibling||row.nextElementSibling;row.querySelectorAll('select[data-searchable]').forEach(select=>select.dispatchEvent(new Event('search-select:dispose')));row.remove();update();previous?.querySelector('.search-select-input,select').focus();
    });
    body.addEventListener('keydown',event=>{
        const control=event.target.closest('input[data-item-field]');
        if(!control||!['Enter','ArrowDown','ArrowUp'].includes(event.key)||event.altKey||event.ctrlKey||event.metaKey)return;
        event.preventDefault();
        const current=control.closest('.purchase-item');
        const backwards=event.key==='ArrowUp'||(event.key==='Enter'&&event.shiftKey);
        let target=backwards?current.previousElementSibling:current.nextElementSibling;
        if(!target&&!backwards&&event.key==='Enter'&&!form.querySelector('.add-item').disabled){
            form.querySelector('.add-item').click();target=current.nextElementSibling;
        }
        if(target){const nextControl=field(target,control.dataset.itemField);nextControl.focus();nextControl.select();}
    });
    form.addEventListener('input',update);form.addEventListener('change',update);
    document.getElementById('wizard-pay-full')?.addEventListener('click',()=>{update();form.elements.amount_paid.value=decimal(total,2);update();});
    form.addEventListener('submit',event=>{
        update();
        for(let index=0;index<3;index++){if(!valid(index)){event.preventDefault();return;}}
        if(step!==2){event.preventDefault();show(2);return;}
        save.dataset.originalText=save.textContent;save.disabled=true;save.textContent=t('Saving…');
    });
    form.noValidate=true;form.classList.add('wizard-enhanced');update();show(Math.max(0,Math.min(2,Number(form.dataset.initialStep)||0)),false);
});
