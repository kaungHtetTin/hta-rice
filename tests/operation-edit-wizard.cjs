const assert=require('node:assert/strict');
const fs=require('node:fs'),path=require('node:path'),{execFileSync}=require('node:child_process'),{Script}=require('node:vm');
const {JSDOM}=require(process.env.JSDOM_PATH||'jsdom');
const base=path.resolve(__dirname,'..');
const html=execFileSync(process.env.PHP_BINARY||'php',[path.join(__dirname,'purchase-wizard-fixture.php'),'transfer-edit'],{encoding:'utf8'});
const dom=new JSDOM('<body>'+html+'</body>',{runScripts:'outside-only',url:'http://localhost/rice/public/transfer/7/edit'});
const window=dom.window,document=window.document;window.HTMLElement.prototype.scrollIntoView=function(){};
for(const name of ['locale','purchase-wizard','app'])new Script(fs.readFileSync(path.join(base,'public/assets/js/'+name+'.js'),'utf8')).runInContext(dom.getInternalVMContext());
document.addEventListener('DOMContentLoaded',()=>{
    try {
        const form=document.querySelector('[data-operation="transfer"]');
        assert.ok(form.getAttribute('action').endsWith('/transfer/7/edit'));
        assert.equal(form.elements.version.value,'2');assert.equal(form.elements.warehouse_id.value,'1');assert.equal(form.elements.destination_id.value,'2');
        const row=document.querySelector('.purchase-item'),quantity=row.querySelector('[data-item-field="quantity"]');
        assert.equal(quantity.value,'8');assert.equal(row.querySelector('.item-destination').textContent,'3','Destination preview applies the net effect after earlier consumption');
        quantity.value='4';quantity.dispatchEvent(new window.Event('input',{bubbles:true}));
        assert.equal(row.querySelector('.item-destination').textContent,'-1');assert.match(quantity.validationMessage,/destination warehouse/);
        quantity.value='6';quantity.dispatchEvent(new window.Event('input',{bubbles:true}));
        assert.equal(quantity.validationMessage,'');assert.equal(row.querySelector('.item-destination').textContent,'1');
        console.log('PASS: Edit wizard preloads values and version, submits to update route, and validates transfer reductions after stock consumption');
    } catch(error){console.error(error);process.exitCode=1;} finally{window.close();}
},{once:true});
