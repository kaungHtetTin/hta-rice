<?php
use Mini\Database as DB;
use App\Services\Stock;
use App\Services\Locale;
$my=Locale::catalog('my');
$localeMarkup=static fn(string $html):string=>preg_replace('#<(script|style)\b[^>]*>.*?</\1>#s','',$html);
$localeToken=token(request('/profile'));
check(request('/locale',['locale'=>'my','return'=>'/purchases'])['status']===419,'Locale changes require CSRF');
check(request('/locale',['_token'=>$localeToken,'locale'=>'xx','return'=>'/'])['status']===422 && str_contains(request('/profile')['body'],'<html lang="en">'),'Unsupported locale leaves the selected language unchanged');
$response=request('/locale',['_token'=>$localeToken,'locale'=>'my','return'=>'/purchases?q=rice&page=2']);
check($response['location']==='/purchases?q=rice&page=2','Language switching preserves the current path and query parameters');
check(str_contains(request('/profile')['body'],'<html lang="my">') && str_contains(request('/profile')['body'],$my['Profile settings']),'Myanmar locale persists through subsequent requests');
$localeTransfer=Stock::record('transfer',['warehouse_id'=>$crudSource,'destination_id'=>$crudDestination,'occurred_on'=>'2028-01-01','notes'=>'Locale transfer','request_key'=>bin2hex(random_bytes(32)),'items'=>[['rice_type_id'=>$rice,'quantity'=>'0.001']]],$owner);
$localeProduction=Stock::record('production',['warehouse_id'=>$crudDestination,'occurred_on'=>'2028-01-01','notes'=>'Locale production','request_key'=>bin2hex(random_bytes(32)),'items'=>[['rice_type_id'=>$rice,'quantity'=>'0.001']]],$owner);
$localePages=[
    '/'=>'Overview','/inventory'=>'Inventory','/stock-movements'=>'Stock movement ledger','/purchases'=>'Purchase register',
    '/purchase/new'=>'Purchase details','/transfer'=>'Transfer details','/production'=>'Production details',
    '/warehouses'=>'Warehouses','/rice-types'=>'Rice types','/suppliers'=>'Suppliers','/supplier-credits'=>'Supplier balances',
    '/suppliers/'.$supplier.'/credit'=>'Record supplier payment','/reports'=>'Period breakdown','/users'=>'Staff permissions',
    '/profile'=>'Change password','/voucher-settings'=>'Business details & printed text','/voucher-settings/preview'=>'Print voucher',
    '/purchase/'.$crudPurchase=>'Purchase record','/transfer/'.$localeTransfer=>'Transfer record','/production/'.$localeProduction=>'Production record',
    '/purchase/'.$crudPurchase.'/edit'=>'Save changes','/transfer/'.$localeTransfer.'/edit'=>'Save changes','/production/'.$localeProduction.'/edit'=>'Save changes',
];
$localeQa=BASE_PATH.'/storage/i18n-qa';if(!is_dir($localeQa))mkdir($localeQa,0755,true);
foreach($localePages as $path=>$expected) {
    $page=request($path);$markup=preg_replace('#<(script|style)\b[^>]*>.*?</\1>#s','',$page['body']);
    check($page['status']===200 && str_contains($markup,'<html lang="my">') && str_contains($markup,$my[$expected]) && !str_contains($page['body'],'Warning:') && !str_contains($page['body'],'Fatal error'),'Myanmar page renders localized labels: '.$path);
    file_put_contents($localeQa.'/'.str_replace('/','_',trim($path,'/')).'.html',$page['body']);
}
$myLedger=request('/stock-movements?kind=transfer&per_page=10&page=2');
check(str_contains($myLedger['body'],'kind=transfer') && str_contains($myLedger['body'],'per_page=10') && str_contains($myLedger['body'],'<html lang="my">'),'Filtering and pagination preserve locale and stable database enum values');
$myDates=request('/purchases?from=2028-02-30');
check(str_contains($localeMarkup($myDates['body']),$my['Enter a valid From date.']),'Date validation errors are in Myanmar');
$myForm=request('/production');
request('/production',['_token'=>token($myForm),'request_key'=>bin2hex(random_bytes(32)),'warehouse_id'=>$crudSource,'occurred_on'=>'2028-01-01','items'=>[['rice_type_id'=>$rice,'quantity'=>'999999999']]]);
check(str_contains($localeMarkup(request('/production')['body']),$my[': Insufficient stock in the selected warehouse for this rice type.']),'Server stock validation messages are in Myanmar');
$myProfile=request('/profile');request('/profile',['_token'=>token($myProfile),'name'=>'','email'=>'owner@example.test']);
check(str_contains($localeMarkup(request('/profile')['body']),$my['Enter a name of up to 120 characters.']),'Profile validation messages are in Myanmar');
check(request('/missing-locale-test')['status']===404 && str_contains(request('/missing-locale-test')['body'],$my['Page not found']),'Route errors are localized');
$xssName='Overview <script>alert(1)</script>';$localeSupplier=DB::insert('suppliers',['name'=>$xssName]);
$supplierPage=request('/suppliers?q=Overview');
check(str_contains($supplierPage['body'],'Overview &lt;script&gt;alert(1)&lt;/script&gt;') && !str_contains($supplierPage['body'],'<script>alert(1)</script>'),'Translations preserve user-entered text and HTML escaping');
foreach(['https://example.test','//example.test','/\\example.test','/%2fexample.test','/../outside','/%2e%2e/outside',"/purchases\r\nX-Test: injected"] as $return)check(request('/locale',['_token'=>$localeToken,'locale'=>'my','return'=>$return])['location']==='/','Language switch rejects external or unsafe return URLs');
request('/logout',['_token'=>token(request('/profile'))]);
$myLogin=request('/login');
file_put_contents($localeQa.'/login.html',$myLogin['body']);
check(str_contains($myLogin['body'],'<html lang="my">') && str_contains($myLogin['body'],$my['Sign in']),'Language preference survives sign-out and applies to sign-in');
request('/login',['_token'=>token($myLogin),'email'=>'owner@example.test','password'=>'incorrect-password']);
check(str_contains($localeMarkup(request('/login')['body']),$my['Email or password is incorrect.']),'Sign-in errors are localized before authentication');
$myLogin=request('/login');request('/login',['_token'=>token($myLogin),'email'=>'owner@example.test','password'=>'test-owner-password']);
check(str_contains(request('/profile')['body'],'<html lang="my">'),'Language preference survives sign-in');
$savedLocaleCookie=$cookie;$cookie='rice_locale=my';$freshLocale=request('/login');
check(str_contains($freshLocale['body'],'<html lang="my">'),'Remembered language applies in a fresh unauthenticated session');$cookie=$savedLocaleCookie;
$restore=request('/locale',['_token'=>token(request('/profile')),'locale'=>'en','return'=>'/profile']);
check($restore['location']==='/profile' && str_contains(request('/profile')['body'],'<html lang="en">') && str_contains(request('/profile')['body'],'Change password'),'Switching back to English restores English interface text');
$enteredText=[];
foreach(['users'=>['name','email'],'warehouses'=>['name','location'],'rice_types'=>['name'],'suppliers'=>['name','phone','address'],'purchases'=>['reference','notes'],'movements'=>['notes'],'supplier_payments'=>['notes']] as $table=>$columns)foreach(DB::fetchAll('SELECT '.implode(',',$columns).' FROM '.$table) as $row)foreach($row as $value)if(is_string($value)&&$value!=='')$enteredText[]=$value;
foreach(\App\Services\VoucherSettings::get() as $value)if(is_string($value)&&$value!=='')$enteredText[]=$value;
file_put_contents($localeQa.'/user-text.json',json_encode(array_values(array_unique($enteredText)),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
