const assert=require('node:assert/strict');
const fs=require('node:fs'),path=require('node:path'),{execFileSync}=require('node:child_process'),{Script}=require('node:vm');
const {JSDOM}=require(process.env.JSDOM_PATH||'jsdom');
const base=path.resolve(__dirname,'..');
const html=execFileSync(process.env.PHP_BINARY||'php',[path.join(__dirname,'purchase-wizard-fixture.php'),'production'],{encoding:'utf8'});
const dom=new JSDOM('<body>'+html+'</body>',{runScripts:'outside-only',url:'http://localhost/rice/public/production'});
const window=dom.window,document=window.document;window.HTMLElement.prototype.scrollIntoView=function(){};window.confirm=()=>true;
for(const name of ['locale','purchase-wizard','app'])new Script(fs.readFileSync(path.join(base,'public/assets/js/'+name+'.js'),'utf8')).runInContext(dom.getInternalVMContext());
document.addEventListener('DOMContentLoaded',()=>{
    try{
        const form=document.querySelector('[data-operation="production"]'),panes=Array.from(form.querySelectorAll('.purchase-step'));
        const input=(control,value)=>{control.value=value;control.dispatchEvent(new window.Event('input',{bubbles:true}));};
        const field=(row,name)=>row.querySelector('[data-item-field="'+name+'"]');
        const choose=(select,value)=>{const control=select.nextElementSibling.querySelector('input');control.focus();control.click();document.getElementById(control.getAttribute('aria-controls')).querySelector('[data-value="'+value+'"]').click();};
        assert.equal(form.elements.amount_paid,undefined);assert.equal(form.elements.supplier_id,undefined);
        document.getElementById('purchase-next').click();assert.equal(panes[0].hidden,false,'Warehouse is required');
        input(form.elements.warehouse_id,'1');input(form.elements.notes,'Batch A');document.getElementById('purchase-next').click();
        const first=document.querySelector('.purchase-item');choose(field(first,'rice_type_id'),'1');input(field(first,'quantity'),'2.125');
        assert.equal(first.querySelector('.item-available').textContent,'10.125');assert.equal(first.querySelector('.item-remaining').textContent,'8');
        form.querySelector('.add-item').click();const second=document.querySelectorAll('.purchase-item')[1];choose(field(second,'rice_type_id'),'2');input(field(second,'quantity'),'5');
        document.getElementById('purchase-next').click();assert.equal(panes[1].hidden,false,'Insufficient stock blocks review');
        assert.equal(second.querySelector('.item-remaining').textContent,'-0.5');assert.equal(second.querySelector('.item-remaining').classList.contains('stock-shortage'),true);
        input(field(second,'quantity'),'1.5');assert.equal(document.getElementById('wizard-quantity').textContent,'3.625'+window.t(' တင်း'));
        document.getElementById('purchase-next').click();assert.equal(panes[2].hidden,false);
        assert.equal(document.querySelectorAll('#purchase-review-items tr').length,2);assert.equal(form.querySelector('.review-notes').textContent,'Batch A');
        document.getElementById('purchase-back').click();assert.equal(field(first,'quantity').value,'2.125');
        input(form.elements.warehouse_id,'');const blocked=new window.Event('submit',{bubbles:true,cancelable:true});form.dispatchEvent(blocked);
        assert.equal(blocked.defaultPrevented,true);assert.equal(panes[0].hidden,false,'Submission validates prior steps');
        input(form.elements.warehouse_id,'1');document.getElementById('purchase-next').click();document.getElementById('purchase-next').click();
        const accepted=new window.Event('submit',{bubbles:true,cancelable:true});form.dispatchEvent(accepted);
        assert.equal(accepted.defaultPrevented,false);assert.equal(document.getElementById('purchase-save').disabled,true);
        console.log('PASS: Production wizard validates stock, preserves rows, reviews balances, and submits without payment fields');
    }catch(error){console.error(error);process.exitCode=1;}finally{window.close();}
},{once:true});
