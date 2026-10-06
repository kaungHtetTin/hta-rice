const assert=require('node:assert/strict');
const fs=require('node:fs'),path=require('node:path'),{execFileSync}=require('node:child_process'),{Script}=require('node:vm');
const {JSDOM}=require(process.env.JSDOM_PATH||'jsdom');
const base=path.resolve(__dirname,'..');
const html=execFileSync(process.env.PHP_BINARY||'php',[path.join(__dirname,'purchase-wizard-fixture.php'),'production','my'],{encoding:'utf8'});
const dom=new JSDOM('<html lang="my"><body>'+html+'</body></html>',{runScripts:'outside-only',url:'http://localhost/rice/public/production'});
const window=dom.window,document=window.document;window.HTMLElement.prototype.scrollIntoView=function(){};
let confirmation='';window.confirm=message=>{confirmation=message;return true;};
for(const name of ['locale','purchase-wizard','app'])new Script(fs.readFileSync(path.join(base,'public/assets/js/'+name+'.js'),'utf8')).runInContext(dom.getInternalVMContext());
document.addEventListener('DOMContentLoaded',()=>{
    try {
        const t=window.t,form=document.querySelector('[data-operation="production"]');
        const input=(control,value)=>{control.value=value;control.dispatchEvent(new window.Event('input',{bubbles:true}));};
        const field=(row,name)=>row.querySelector('[data-item-field="'+name+'"]');
        const choose=(select,value)=>{const control=select.nextElementSibling.querySelector('input');control.focus();document.getElementById(control.getAttribute('aria-controls')).querySelector('[data-value="'+value+'"]').click();};
        assert.equal(window.RiceLocale,'my');assert.equal(document.getElementById('purchase-next').textContent,t('Continue to rice items'));
        assert.equal(document.querySelector('.wizard-step-status').textContent,t('Current'));
        input(form.elements.warehouse_id,'1');document.getElementById('purchase-next').click();
        const first=document.querySelector('.purchase-item'),rice=field(first,'rice_type_id'),search=rice.nextElementSibling.querySelector('input');
        assert.equal(search.placeholder,t('Search rice type'));
        input(search,'no such product');assert.equal(document.querySelector('.search-select-empty').textContent,t('No matches found'));
        choose(rice,'1');assert.equal(search.value,'First rice','Rice names remain exactly as entered');
        const quantity=field(first,'quantity');quantity.dispatchEvent(new window.Event('invalid',{bubbles:false,cancelable:true}));
        assert.equal(quantity.validationMessage,t('Please complete this field.'));
        input(quantity,'1000');assert.equal(quantity.validationMessage,t('Insufficient stock in the selected warehouse for this rice type.'));
        input(quantity,'2.125');assert.equal(quantity.validationMessage,'');assert.equal(document.getElementById('wizard-quantity').textContent,'2.125'+t(' တင်း'));
        assert.equal(document.getElementById('item-count').textContent,t('{count} rice item(s)',{count:1}));
        form.querySelector('.add-item').click();const second=document.querySelectorAll('.purchase-item')[1];choose(field(second,'rice_type_id'),'2');input(field(second,'quantity'),'1');second.querySelector('.remove-item').click();
        assert.equal(confirmation,t('Remove this rice item from the {operation}?',{operation:t('Production')}));
        document.getElementById('purchase-next').click();assert.equal(document.querySelector('.wizard-step-status').textContent,t('Done'));
        assert.equal(document.querySelector('#purchase-review-items td').textContent,'First rice');
        const submit=new window.Event('submit',{bubbles:true,cancelable:true});form.dispatchEvent(submit);
        assert.equal(submit.defaultPrevented,false);assert.equal(document.getElementById('purchase-save').textContent,t('Saving…'));
        console.log('PASS: Myanmar wizard labels, search feedback, native and stock validation, counts, confirmations, steps, units and submit state; user data is unchanged');
    } catch(error){console.error(error);process.exitCode=1;} finally{window.close();}
},{once:true});
