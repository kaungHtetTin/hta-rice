const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const {JSDOM}=require(process.env.JSDOM_PATH||'jsdom');
const root=path.resolve(__dirname,'..'),directory=path.join(root,'storage/i18n-qa');
const userText=JSON.parse(fs.readFileSync(path.join(directory,'user-text.json'),'utf8')).sort((a,b)=>b.length-a.length);
const technical=new Set(['English','MMK','PNG','JPG','WebP','A4','A5','MB','Tab','Enter','Shift','mm','PAY','PREVIEW','ONLY','REG']);
let checked=0;
for(const name of fs.readdirSync(directory).filter(name=>name.endsWith('.html'))){
    const dom=new JSDOM(fs.readFileSync(path.join(directory,name),'utf8'));
    const document=dom.window.document;document.querySelectorAll('script,style').forEach(node=>node.remove());
    const values=[document.body.textContent,...Array.from(document.querySelectorAll('[aria-label],[title],[alt],[placeholder],[data-confirm]')).flatMap(node=>['aria-label','title','alt','placeholder','data-confirm'].map(key=>node.getAttribute(key)||''))];
    for(let value of values){
        for(const entered of userText)value=value.split(entered).join('');
        value=value.replace(/PUR-\d+-[A-F0-9]+/g,'').replace(/PAY-\d+/g,'').replace(/REG-\d+/g,'').replace(/\b[0-9A-F]{20,}\b/g,'').replace(/\bA[45]\b/g,'');
        const remaining=(value.match(/[A-Za-z]+/g)||[]).filter(word=>!technical.has(word)&&!/^T$/.test(word));
        assert.deepEqual(remaining,[],name+' contains untranslated interface text: '+remaining.join(' '));
    }
    dom.window.close();checked++;
}
console.log('PASS: '+checked+' Myanmar pages contain localized visible text, accessibility labels, placeholders and confirmations; only entered data and standard technical tokens remain');
