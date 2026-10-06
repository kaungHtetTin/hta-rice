const assert=require('node:assert/strict');
const fs=require('node:fs'),path=require('node:path'),{execFileSync}=require('node:child_process'),{Script}=require('node:vm');
const {JSDOM}=require(process.env.JSDOM_PATH||'jsdom');
const base=path.resolve(__dirname,'..');
const html=execFileSync(process.env.PHP_BINARY||'php',[path.join(__dirname,'purchase-wizard-fixture.php'),'transfer'],{encoding:'utf8'});
const dom=new JSDOM('<body>'+html+'</body>',{runScripts:'outside-only',url:'http://localhost/rice/public/transfer'});
const window=dom.window,document=window.document;window.HTMLElement.prototype.scrollIntoView=function(){};window.confirm=()=>true;
for(const name of ['locale','purchase-wizard','app'])new Script(fs.readFileSync(path.join(base,'public/assets/js/'+name+'.js'),'utf8')).runInContext(dom.getInternalVMContext());
document.addEventListener('DOMContentLoaded',()=>{
    try{
        const form=document.querySelector('[data-operation="transfer"]'),panes=Array.from(form.querySelectorAll('.purchase-step'));
        const input=(control,value)=>{control.value=value;control.dispatchEvent(new window.Event('input',{bubbles:true}));};
        const field=(row,name)=>row.querySelector('[data-item-field="'+name+'"]');
        const choose=(select,value)=>{const control=select.nextElementSibling.querySelector('input');control.focus();control.click();document.getElementById(control.getAttribute('aria-controls')).querySelector('[data-value="'+value+'"]').click();};
        assert.equal(form.elements.amount_paid,undefined);
        input(form.elements.warehouse_id,'1');input(form.elements.destination_id,'1');document.getElementById('purchase-next').click();
        assert.equal(panes[0].hidden,false,'A warehouse cannot transfer stock to itself');
        input(form.elements.destination_id,'2');input(form.elements.notes,'Delivery A');document.getElementById('purchase-next').click();
        const first=document.querySelector('.purchase-item');choose(field(first,'rice_type_id'),'1');input(field(first,'quantity'),'2.125');
        assert.equal(first.querySelector('.item-available').textContent,'10.125');assert.equal(first.querySelector('.item-remaining').textContent,'8');assert.equal(first.querySelector('.item-destination').textContent,'3.375');
        form.querySelector('.add-item').click();const second=document.querySelectorAll('.purchase-item')[1];choose(field(second,'rice_type_id'),'2');input(field(second,'quantity'),'5');
        document.getElementById('purchase-next').click();assert.equal(panes[1].hidden,false,'Insufficient stock blocks transfer review');
        input(field(second,'quantity'),'1.5');document.getElementById('purchase-next').click();assert.equal(panes[2].hidden,false);
        assert.equal(document.querySelectorAll('#purchase-review-items tr').length,2);assert.equal(document.querySelector('#purchase-review-items tr').children.length,6,'Review shows both warehouses before and after');
        assert.ok(form.querySelector('.review-details').textContent.includes('Main warehouse → Second warehouse'));
        document.getElementById('purchase-back').click();assert.equal(field(first,'quantity').value,'2.125');
        input(form.elements.warehouse_id,'2');document.getElementById('purchase-next').click();assert.equal(panes[0].hidden,false,'Changing source revalidates destination');
        input(form.elements.destination_id,'1');document.getElementById('purchase-next').click();document.getElementById('purchase-next').click();assert.equal(panes[1].hidden,false,'Changed source balances are rechecked');
        input(form.elements.warehouse_id,'1');input(form.elements.destination_id,'2');document.getElementById('purchase-next').click();
        const submit=new window.Event('submit',{bubbles:true,cancelable:true});form.dispatchEvent(submit);
        assert.equal(submit.defaultPrevented,false);assert.equal(document.getElementById('purchase-save').disabled,true);
        console.log('PASS: Transfer wizard validates warehouses and source stock, reviews both balances, and preserves rows');
    }catch(error){console.error(error);process.exitCode=1;}finally{window.close();}
},{once:true});
