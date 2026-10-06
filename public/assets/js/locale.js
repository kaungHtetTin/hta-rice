'use strict';
(() => {
    const element=document.getElementById('locale-data');
    const data=element?JSON.parse(element.textContent):{locale:'en',messages:{}};
    window.RiceLocale=data.locale;
    window.t=(message,parameters={})=>{
        let value=data.messages[message]??message;
        for(const [key,text] of Object.entries(parameters))value=value.split('{'+key+'}').join(String(text));
        return value;
    };
    // Native browser validation otherwise follows the browser's language.
    document.addEventListener('invalid',event=>{
        const input=event.target;
        if(data.locale!=='my'||input.validity.customError)return;
        let message='Please check this value.';
        if(input.validity.valueMissing)message='Please complete this field.';
        else if(input.validity.typeMismatch)message=input.type==='email'?'Enter a valid email address.':'Please check this value.';
        else if(input.validity.rangeUnderflow)message='Value must be at least {min}.';
        else if(input.validity.rangeOverflow)message='Value must not exceed {max}.';
        else if(input.validity.tooShort)message='Use at least {min} characters.';
        input.setCustomValidity(window.t(message,{min:input.type==='password'?input.minLength:input.min,max:input.max}));
        input.dataset.localeValidity='true';
    },true);
    const clear=event=>{if(event.target.dataset.localeValidity){event.target.setCustomValidity('');delete event.target.dataset.localeValidity;}};
    document.addEventListener('input',clear,true);document.addEventListener('change',clear,true);
})();
