<?php
declare(strict_types=1);
// Integration tests create a disposable database; real business records are untouched.
$testDatabase = 'rice_test_' . bin2hex(random_bytes(6));
$_ENV['DB_DATABASE'] = $testDatabase;
require dirname(__DIR__) . '/bootstrap/app.php';
use Mini\Database as DB;
use App\Services\Stock;
function check(bool $passed, string $message): void { if (!$passed) throw new RuntimeException('FAIL: '.$message); echo "PASS: $message\n"; }
$pdo = DB::connection(true);
$server = null; $logs = BASE_PATH.'/storage/test-server.log'; $testLogos=[];
$port = random_int(18000,25000); $cookie='';
function request(string $path, ?array $data = null, array $files = []): array {
    global $port,$cookie;
    $headers="Connection: close\r\n";
    if($cookie) $headers.="Cookie: $cookie\r\n";
    $content=$data===null?'':http_build_query($data);
    if($files){
        $boundary='rice-test-'.bin2hex(random_bytes(12));$content='';
        $headers.="Content-Type: multipart/form-data; boundary=$boundary\r\n";
        foreach($data??[] as $name=>$value)$content.="--$boundary\r\nContent-Disposition: form-data; name=\"$name\"\r\n\r\n$value\r\n";
        foreach($files as $name=>$file)$content.="--$boundary\r\nContent-Disposition: form-data; name=\"$name\"; filename=\"{$file['name']}\"\r\nContent-Type: {$file['type']}\r\n\r\n{$file['bytes']}\r\n";
        $content.="--$boundary--\r\n";
    }elseif($data !== null) $headers.="Content-Type: application/x-www-form-urlencoded\r\n";
    $context=stream_context_create(['http'=>['method'=>$data===null?'GET':'POST','header'=>$headers,'content'=>$content,'ignore_errors'=>true,'follow_location'=>0,'timeout'=>10]]);
    $body=file_get_contents('http://127.0.0.1:'.$port.$path,false,$context);
    clearstatcache(); // The HTTP server may have replaced or removed an uploaded file.
    $status=(int)explode(' ',$http_response_header[0])[1];$location='';
    foreach($http_response_header as $header){if(str_starts_with(strtolower($header),'set-cookie:')) { $pair=explode(';',trim(substr($header,11)))[0];$jar=[];foreach(explode('; ',$cookie) as $stored)if(str_contains($stored,'='))$jar[explode('=',$stored,2)[0]]=$stored;$jar[explode('=',$pair,2)[0]]=$pair;$cookie=implode('; ',array_values($jar)); } if(str_starts_with(strtolower($header),'location:')) $location=trim(substr($header,9));}
    return ['status'=>$status,'body'=>(string)$body,'location'=>$location];
}
function token(array $response): string { preg_match('/name="_token" value="([a-f0-9]+)"/',$response['body'],$m); if(empty($m[1])) throw new RuntimeException('CSRF token missing: '.$response['body']); return $m[1]; }
try {
    $pdo->exec("CREATE DATABASE `$testDatabase` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    DB::connection()->exec('CREATE TABLE migrations (id INT PRIMARY KEY) ENGINE=InnoDB'); DB::statement('INSERT INTO migrations VALUES (1)');
    foreach(glob(BASE_PATH.'/migrations/*.php') as $file){$migration=require $file;foreach($migration['up'] as $sql) DB::connection()->exec($sql);}
    $env=getenv();$env['DB_DATABASE']=$testDatabase;$env['APP_URL']='';$env['APP_DEBUG']='true';$env['SESSION_NAME']='rice_test_'.bin2hex(random_bytes(3));
    $server=proc_open([PHP_BINARY,'-S','127.0.0.1:'.$port,'-t',BASE_PATH.'/public',BASE_PATH.'/public/router.php'],[0=>['pipe','r'],1=>['file',$logs,'w'],2=>['file',$logs,'a']],$pipes,BASE_PATH,$env);
    if(!is_resource($server)) throw new RuntimeException('Cannot start test server.');
    fclose($pipes[0]);
    for($attempt=0;$attempt<40;$attempt++){ $connection=@fsockopen('127.0.0.1',$port);if($connection){fclose($connection);break;}usleep(100000); }
    check(request('/')['location']==='/login','Unauthenticated dashboard redirects to sign in');
    $setup=request('/setup');check($setup['status']===200,'Owner setup renders');
    check(request('/setup',['name'=>'Owner','email'=>'owner@example.test','password'=>'test-owner-password'])['status']===419,'CSRF rejects unprotected account creation');
    $created=request('/setup',['_token'=>token($setup),'name'=>'Test Owner','email'=>'owner@example.test','password'=>'test-owner-password']);check($created['status']===302,'Owner account created through setup');
    check(request('/setup')['location']==='/login','Setup is closed after first owner');
    $dashboard=request('/');check($dashboard['status']===200 && str_contains($dashboard['body'],'Total rice in stock'),'Empty dashboard renders without errors');
    $owner=(int)DB::fetchValue('SELECT id FROM users LIMIT 1');
    $w1=DB::insert('warehouses',['name'=>'Main warehouse','location'=>'Yangon']);$w2=DB::insert('warehouses',['name'=>'Second warehouse','location'=>'Mandalay']);
    $rice=DB::insert('rice_types',['name'=>'ပေါ်ဆန်း']);$supplier=DB::insert('suppliers',['name'=>'Test Supplier']);
    $form=request('/purchase/new');check($form['status']===200,'Purchase form renders');
    preg_match('/name="request_key" value="([a-f0-9]+)"/',$form['body'],$key);
    $purchase=['_token'=>token($form),'request_key'=>$key[1],'warehouse_id'=>$w1,'rice_type_id'=>$rice,'supplier_id'=>$supplier,'quantity'=>'100.125','weight_lb'=>'12345.678','unit_price'=>'25000.25','occurred_on'=>'2026-10-06','notes'=>'<script>alert(1)</script>'];
    $saved=request('/purchase/new',$purchase);check($saved['status']===302 && str_starts_with($saved['location'],'/voucher/'),'Purchase saves and redirects to voucher');
    check(DB::fetchValue('SELECT quantity FROM inventory WHERE warehouse_id=?',[$w1])==='100.125','Purchase adds exact decimal stock');
    check(DB::fetchValue('SELECT weight_lb FROM purchases LIMIT 1')==='12345.678','Purchase records exact decimal weight in pounds');
    check(DB::fetchValue('SELECT amount FROM purchases LIMIT 1')==='2503150.03','Purchase amount is rounded exactly to two decimals');
    $voucher=request($saved['location']);check($voucher['status']===200 && str_contains($voucher['body'],'Print voucher') && str_contains($voucher['body'],'2,503,150.03') && str_contains($voucher['body'],'&lt;script&gt;'),'Printable voucher includes correct amount and escaped notes');
    check(str_contains($voucher['body'],'Weight (ပေါင်)') && str_contains($voucher['body'],'12,345.678') && str_contains(request('/purchases')['body'],'12,345.678'),'Purchase register and voucher display weight');
    request('/purchase/new',$purchase);check((int)DB::fetchValue('SELECT COUNT(*) FROM purchases')===1 && DB::fetchValue('SELECT quantity FROM inventory WHERE warehouse_id=?',[$w1])==='100.125','Repeated submission does not duplicate purchase or stock');
    $base=['warehouse_id'=>$w1,'rice_type_id'=>$rice,'destination_id'=>$w2,'quantity'=>'30.125','occurred_on'=>'2026-10-06','notes'=>'Test transfer','request_key'=>bin2hex(random_bytes(32))];
    Stock::record('transfer',$base,$owner);
    check(DB::fetchValue('SELECT quantity FROM inventory WHERE warehouse_id=?',[$w1])==='70.000' && DB::fetchValue('SELECT quantity FROM inventory WHERE warehouse_id=?',[$w2])==='30.125','Transfer conserves stock across warehouses');
    $total=DB::fetchValue('SELECT SUM(quantity) FROM inventory');$base['request_key']=bin2hex(random_bytes(32));$base['quantity']='500';
    try{Stock::record('transfer',$base,$owner);throw new RuntimeException('Transfer should fail.');}catch(InvalidArgumentException $e){}
    check(DB::fetchValue('SELECT SUM(quantity) FROM inventory')===$total && (int)DB::fetchValue('SELECT COUNT(*) FROM movements')===2,'Insufficient transfer rolls back every write');
    $productionForm=request('/production');preg_match('/name="request_key" value="([a-f0-9]+)"/',$productionForm['body'],$prodKey);
    $production=['_token'=>token($productionForm),'request_key'=>$prodKey[1],'warehouse_id'=>$w2,'rice_type_id'=>$rice,'quantity'=>'10.125','occurred_on'=>'2026-10-06','notes'=>'Batch 1'];
    check(request('/production',$production)['status']===302 && DB::fetchValue('SELECT quantity FROM inventory WHERE warehouse_id=?',[$w2])==='20.000','Production form deducts stock only');
    check((int)DB::fetchValue('SELECT COUNT(*) FROM purchases')===1,'Production does not create financial records');
    $bad=$production;$bad['quantity']='100';$bad['request_key']=bin2hex(random_bytes(32));request('/production',$bad);
    $errorForm=request('/production');check(str_contains($errorForm['body'],'Insufficient stock') && str_contains($errorForm['body'],'value="100"'),'Production overspend is rejected and inputs are retained');
    check(DB::fetchValue('SELECT quantity FROM inventory WHERE warehouse_id=?',[$w2])==='20.000','Production cannot create negative stock');
    $report=request('/reports?start=2026-10-01&end=2026-10-31&group=month&warehouse='.$w1);check($report['status']===200 && str_contains($report['body'],'2,503,150.03'),'Monthly warehouse purchase report matches ledger');
    $otherReport=request('/reports?start=2026-10-01&end=2026-10-31&group=month&warehouse='.$w2);check(!str_contains($otherReport['body'],'2,503,150.03'),'Transfers do not count as destination purchases');
    $yearReport=request('/reports?start=2025-01-01&end=2027-12-31&group=year');check($yearReport['status']===200 && str_contains($yearReport['body'],'2,503,150.03'),'Yearly reports aggregate purchase amount');
    $badReport=request('/reports?start=2026-12-31&end=2026-01-01');check(str_contains($badReport['body'],'Start date must be before'),'Invalid report ranges show actionable error');
    $matrix=request('/inventory');
    preg_match('/<tr data-rice-id="'.$rice.'">(.*?)<\/tr>/s',$matrix['body'],$matrixRow);
    check(isset($matrixRow[1]) && str_contains($matrixRow[1],'<td class="number">70</td>') && str_contains($matrixRow[1],'<td class="number">20</td>') && str_contains($matrixRow[1],'<strong>90</strong>'),'Inventory matrix shows each warehouse balance and rice total');
    check(str_contains($matrix['body'],'<th scope="col">No.</th>') && str_contains($matrix['body'],'<th scope="col" class="number">Main warehouse</th>') && str_contains($matrix['body'],'<th scope="col" class="number">Second warehouse</th>'),'Inventory matrix uses dynamic warehouse columns');
    $zeroRice=DB::insert('rice_types',['name'=>'Zero-stock rice']);$zeroMatrix=request('/inventory');
    preg_match('/<tr data-rice-id="'.$zeroRice.'">(.*?)<\/tr>/s',$zeroMatrix['body'],$zeroRow);
    check(isset($zeroRow[1]) && substr_count($zeroRow[1],'<td class="number">0</td>')===2 && str_contains($zeroRow[1],'<strong>0</strong>'),'Inventory matrix includes rice types with no stock');
    foreach(['/inventory','/stock-movements','/purchases','/transfer','/warehouses','/rice-types','/suppliers','/supplier-credits','/users'] as $path){$response=request($path);check($response['status']===200 && !str_contains($response['body'],'Warning:'),'Page renders: '.$path);}
    check(request('/settings')['location']==='/warehouses','Old business settings route redirects to warehouse directory');
    $crudToken=token(request('/warehouses'));
    foreach(['warehouses'=>['location'=>'Test address'],'rice-types'=>[],'suppliers'=>['phone'=>'091234567','address'=>'Test address']] as $type=>$extra){
        $table=$type==='rice-types'?'rice_types':$type;
        $name='Unused '.$type;
        check(request('/'.$type,['_token'=>$crudToken,'name'=>$name]+$extra)['status']===302,'Create directory record: '.$type);
        $id=(int)DB::fetchValue('SELECT id FROM '.$table.' WHERE name=?',[$name]);
        check($id>0 && request('/'.$type.'/'.$id.'/edit')['status']===200,'Read edit form: '.$type);
        request('/'.$type,['_token'=>$crudToken,'id'=>$id,'name'=>$name.' updated']+$extra);
        check(DB::fetchValue('SELECT name FROM '.$table.' WHERE id=?',[$id])===$name.' updated','Update directory record: '.$type);
        request('/'.$type.'/'.$id.'/delete',['_token'=>$crudToken]);
        check(!DB::fetch('SELECT id FROM '.$table.' WHERE id=?',[$id]),'Delete unused record: '.$type);
    }
    foreach(['warehouses'=>$w1,'rice-types'=>$rice,'suppliers'=>$supplier] as $type=>$id){
        request('/'.$type.'/'.$id.'/delete',['_token'=>$crudToken]);
        $table=$type==='rice-types'?'rice_types':$type;
        check(DB::fetch('SELECT id FROM '.$table.' WHERE id=?',[$id])!==null,'Protect referenced record: '.$type);
    }
    check(\App\Services\SupplierCredit::balance($supplier)==='0.00','Legacy fully-paid purchases create no supplier debt');
    $creditForm=request('/purchase/new');preg_match('/name="request_key" value="([a-f0-9]+)"/',$creditForm['body'],$creditKey);
    $creditPurchase=$purchase;$creditPurchase['_token']=token($creditForm);$creditPurchase['request_key']=$creditKey[1];$creditPurchase['quantity']='10';$creditPurchase['unit_price']='100.50';$creditPurchase['amount_paid']='100.25';
    check(request('/purchase/new',$creditPurchase)['status']===302 && \App\Services\SupplierCredit::balance($supplier)==='904.75','Partial payment creates exact supplier credit');
    $payForm=request('/suppliers/'.$supplier.'/credit');preg_match('/name="request_key" value="([a-f0-9]+)"/',$payForm['body'],$payKey);
    $payment=['_token'=>token($payForm),'request_key'=>$payKey[1],'amount'=>'200.25','paid_on'=>'2026-10-06','notes'=>'Test payment'];
    $stockBeforePayment=DB::fetchValue('SELECT SUM(quantity) FROM inventory');
    check(request('/suppliers/'.$supplier.'/payments',$payment)['status']===302 && \App\Services\SupplierCredit::balance($supplier)==='704.50','Supplier payment reduces balance exactly');
    check(DB::fetchValue('SELECT SUM(quantity) FROM inventory')===$stockBeforePayment,'Supplier payment never changes inventory');
    request('/suppliers/'.$supplier.'/payments',$payment);
    check((int)DB::fetchValue('SELECT COUNT(*) FROM supplier_payments')===1,'Repeated payment does not deduct credit twice');
    $overpay=$payment;$overpay['request_key']=bin2hex(random_bytes(32));$overpay['amount']='800';request('/suppliers/'.$supplier.'/payments',$overpay);
    check(\App\Services\SupplierCredit::balance($supplier)==='704.50' && str_contains(request('/suppliers/'.$supplier.'/credit')['body'],'Payment exceeds'),'Overpayment is rejected without changing credit');
    $settle=$payment;$settle['request_key']=bin2hex(random_bytes(32));$settle['amount']='704.50';request('/suppliers/'.$supplier.'/payments',$settle);
    check(\App\Services\SupplierCredit::balance($supplier)==='0.00','Supplier credit can be fully settled');
    $invalidPaid=$creditPurchase;$invalidPaid['request_key']=bin2hex(random_bytes(32));$invalidPaid['amount_paid']='1006';$beforeCount=DB::fetchValue('SELECT COUNT(*) FROM purchases');$beforeStock=DB::fetchValue('SELECT SUM(quantity) FROM inventory');request('/purchase/new',$invalidPaid);
    check(DB::fetchValue('SELECT COUNT(*) FROM purchases')===$beforeCount && DB::fetchValue('SELECT SUM(quantity) FROM inventory')===$beforeStock,'Purchase overpayment rolls back stock and purchase');
    $badWeight=$creditPurchase;$badWeight['request_key']=bin2hex(random_bytes(32));$badWeight['weight_lb']='-1';request('/purchase/new',$badWeight);
    check(DB::fetchValue('SELECT COUNT(*) FROM purchases')===$beforeCount && DB::fetchValue('SELECT SUM(quantity) FROM inventory')===$beforeStock,'Invalid purchase weight cannot change stock or purchase records');
    $largePurchase=$creditPurchase;unset($largePurchase['weight_lb']);$largePurchase['request_key']=bin2hex(random_bytes(32));$largePurchase['quantity']='100000';$largePurchase['unit_price']='25000';$largePurchase['amount_paid']='2500000000';
    check(request('/purchase/new',$largePurchase)['status']===302 && \App\Services\SupplierCredit::balance($supplier)==='0.00','Fully-paid large MMK purchases are supported without creating credit');
    check(DB::fetch('SELECT weight_lb FROM purchases ORDER BY id DESC LIMIT 1')['weight_lb']===null,'Purchase weight is optional and missing weight is not recorded as zero');
    $voucherSettingsPage=request('/voucher-settings');
    check($voucherSettingsPage['status']===200 && str_contains($voucherSettingsPage['body'],'Default paper size'),'Voucher settings page renders');
    $voucherSettings=['_token'=>token($voucherSettingsPage),'business_name'=>'Test Rice Business','voucher_title'=>'Purchase receipt','header_text'=>'Custom header <script>alert(1)</script>','address'=>"Business address line 1\nBusiness address line 2",'phone'=>'09 555 123','email'=>'office@example.test','website'=>'example.test','registration_no'=>'REG-001','footer_text'=>'Custom footer terms','show_weight'=>'1','show_signatures'=>'1','show_recorded_by'=>'1'];
    foreach(['A4','A5','88mm','80mm','58mm'] as $paper){
        $voucherSettings['paper_size']=$paper;
        check(request('/voucher-settings',$voucherSettings)['status']===302,'Save voucher paper size: '.$paper);
        $configuredVoucher=request($saved['location']);
        check($configuredVoucher['status']===200 && str_contains($configuredVoucher['body'],'data-paper="'.$paper.'"') && str_contains($configuredVoucher['body'],'Test Rice Business') && str_contains($configuredVoucher['body'],'Custom footer terms') && str_contains($configuredVoucher['body'],'Business address line 1') && str_contains($configuredVoucher['body'],'&lt;script&gt;'),'Voucher applies paper size and escaped custom content: '.$paper);
    }
    $previewCount=DB::fetchValue('SELECT COUNT(*) FROM purchases');
    $settingsPreview=request('/voucher-settings/preview');
    check($settingsPreview['status']===200 && str_contains($settingsPreview['body'],'PREVIEW-ONLY') && DB::fetchValue('SELECT COUNT(*) FROM purchases')===$previewCount,'Saved voucher preview creates no purchases');
    $hiddenDetails=$voucherSettings;unset($hiddenDetails['show_weight'],$hiddenDetails['show_signatures'],$hiddenDetails['show_recorded_by']);
    request('/voucher-settings',$hiddenDetails);$hiddenVoucher=request($saved['location']);
    $visibleVoucher=preg_replace('#<script\b[^>]*>.*?</script>#s','',$hiddenVoucher['body']);
    check(!str_contains($visibleVoucher,'Weight (ပေါင်)') && !str_contains($visibleVoucher,'Supplier signature') && !str_contains($visibleVoucher,'Recorded by'),'Voucher information visibility follows saved settings');
    $invalidPaper=$voucherSettings;$invalidPaper['paper_size']='invalid';request('/voucher-settings',$invalidPaper);
    check(\App\Services\VoucherSettings::get()['paper_size']==='58mm','Invalid paper size leaves saved settings unchanged');
    $logoBytes=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aD1sAAAAASUVORK5CYII=');
    $logoUpload=['company_logo'=>['name'=>'company.png','type'=>'image/png','bytes'=>$logoBytes]];
    $companySettings=$voucherSettings;$companySettings['business_name']='Configured Company';
    check(request('/voucher-settings',$companySettings,$logoUpload)['status']===302,'Company name and logo upload save through the settings form');
    $logoFile=\App\Services\VoucherSettings::get()['logo_file'];$testLogos[]=$logoFile;
    check(is_file(\App\Services\VoucherSettings::logoPath($logoFile)) && request('/company-logo')['body']===$logoBytes,'Logo is stored outside the public directory and served as an image');
    check(str_contains(request('/inventory')['body'],'class="brand-logo"') && str_contains(request('/inventory')['body'],'Configured Company'),'Company name and logo appear in app branding');
    $ownerCookie=$cookie;$cookie='';$companyLogin=request('/login');$publicLogo=request('/company-logo');$cookie=$ownerCookie;
    check(str_contains($companyLogin['body'],'Configured Company') && str_contains($companyLogin['body'],'class="brand-logo"') && $publicLogo['body']===$logoBytes,'Login branding and logo are available before sign in');
    foreach(['A4','A5','88mm','80mm','58mm'] as $paper){
        $companySettings['paper_size']=$paper;request('/voucher-settings',$companySettings);
        check(\App\Services\VoucherSettings::get()['logo_file']===$logoFile && str_contains(request($saved['location'])['body'],'class="voucher-company-logo"'),'Logo is preserved and displayed on voucher paper: '.$paper);
    }
    $badLogo=['company_logo'=>['name'=>'bad.png','type'=>'image/png','bytes'=>'<?php echo "unsafe"; ?>']];
    request('/voucher-settings',$companySettings,$badLogo);
    check(\App\Services\VoucherSettings::get()['logo_file']===$logoFile && str_contains(request('/voucher-settings')['body'],'Choose a PNG'),'Invalid logo content is rejected without changing the saved logo');
    request('/voucher-settings',$companySettings,$logoUpload);
    $replacement=\App\Services\VoucherSettings::get()['logo_file'];$testLogos[]=$replacement;
    check($replacement!==$logoFile && !is_file(\App\Services\VoucherSettings::logoPath($logoFile)),'Replacing a logo removes the previous stored image');
    request('/voucher-settings',$companySettings+['remove_logo'=>'1']);
    check(\App\Services\VoucherSettings::get()['logo_file']==='' && !is_file(\App\Services\VoucherSettings::logoPath($replacement)) && request('/company-logo')['status']===404,'Removing a logo restores the default app mark and removes it from vouchers');
    $userForm=request('/users');
    $multiRice=DB::insert('rice_types',['name'=>'Second variety for multi purchase']);
    $thirdRice=DB::insert('rice_types',['name'=>'Third variety for multi purchase']);
    $multiForm=request('/purchase/new');preg_match('/name="request_key" value="([a-f0-9]+)"/',$multiForm['body'],$multiKey);
    check(str_contains($multiForm['body'],'data-purchase-wizard') && str_contains($multiForm['body'],'Payment &amp; review') && str_contains($multiForm['body'],'items[0][rice_type_id]'),'Multi-item purchase form renders the three-step wizard');
    $multi=['_token'=>token($multiForm),'request_key'=>$multiKey[1],'warehouse_id'=>$w1,'supplier_id'=>$supplier,'occurred_on'=>'2026-09-15','notes'=>'Multi-item delivery','amount_paid'=>'100','items'=>[
        ['rice_type_id'=>$rice,'quantity'=>'2.125','weight_lb'=>'100.001','unit_price'=>'10.25'],
        ['rice_type_id'=>$multiRice,'quantity'=>'3.333','weight_lb'=>'200.002','unit_price'=>'20.75'],
        ['rice_type_id'=>$thirdRice,'quantity'=>'1','weight_lb'=>'50.003','unit_price'=>'12.50'],
    ]];
    $beforeStock=DB::fetchValue('SELECT quantity FROM inventory WHERE warehouse_id=? AND rice_type_id=?',[$w1,$rice]);
    $multiResult=request('/purchase/new',$multi);
    $multiPurchase=DB::fetch('SELECT * FROM purchases WHERE request_key=?',[$multiKey[1]]);
    check($multiResult['status']===302 && $multiPurchase && str_starts_with($multiResult['location'],'/voucher/'),'Multiple rice types save as one purchase and voucher');
    $multiId=(int)$multiPurchase['id'];
    check($multiPurchase['amount']==='103.44' && $multiPurchase['quantity']==='6.458' && $multiPurchase['weight_lb']==='350.006','Purchase totals sum separately rounded lines and exact decimal quantities and weights');
    check(array_column(DB::fetchAll('SELECT amount FROM purchase_items WHERE purchase_id=? ORDER BY id',[$multiId]),'amount')===['21.78','69.16','12.50'],'Each rice item records its own rounded amount');
    check(DB::fetchValue('SELECT quantity FROM inventory WHERE warehouse_id=? AND rice_type_id=?',[$w1,$multiRice])==='3.333' && DB::fetchValue('SELECT quantity FROM inventory WHERE warehouse_id=? AND rice_type_id=?',[$w1,$thirdRice])==='1.000' && (bool)DB::fetchValue('SELECT quantity=CAST(? AS DECIMAL(16,3))+2.125 FROM inventory WHERE warehouse_id=? AND rice_type_id=?',[$beforeStock,$w1,$rice]),'All selected rice balances update by their exact item quantities');
    check((int)DB::fetchValue('SELECT COUNT(*) FROM movements WHERE purchase_id=?',[$multiId])===3 && \App\Services\SupplierCredit::balance($supplier)==='3.44','Stock ledger records every item while supplier credit uses the purchase total once');
    $multiReport=request('/reports?start=2026-09-01&end=2026-09-30&group=month&warehouse='.$w1);
    check(str_contains($multiReport['body'],'103.44') && str_contains($multiReport['body'],'6.458'),'Reports aggregate a multi-item purchase without duplicating its amount');
    $multiRegister=request('/purchases?q='.rawurlencode('Second variety for multi purchase'));
    check(str_contains($multiRegister['body'],$multiPurchase['reference']) && str_contains($multiRegister['body'],'3 items') && str_contains($multiRegister['body'],'Mixed prices'),'Purchase register searches any rice item and displays combined purchases');
    foreach(['A4','A5','88mm','80mm','58mm'] as $paper){
        $companySettings['paper_size']=$paper;request('/voucher-settings',$companySettings);
        $multiVoucher=request($multiResult['location']);
        check(str_contains($multiVoucher['body'],'Second variety for multi purchase') && str_contains($multiVoucher['body'],'21.78') && str_contains($multiVoucher['body'],'69.16') && str_contains($multiVoucher['body'],'103.44'),'Multi-item voucher includes all item amounts and total: '.$paper);
    }
    $purchaseCount=DB::fetchValue('SELECT COUNT(*) FROM purchases');$movementCount=DB::fetchValue('SELECT COUNT(*) FROM movements');$itemCount=DB::fetchValue('SELECT COUNT(*) FROM purchase_items');$stockCount=DB::fetchValue('SELECT SUM(quantity) FROM inventory');
    request('/purchase/new',$multi);
    check(DB::fetchValue('SELECT COUNT(*) FROM purchases')===$purchaseCount && DB::fetchValue('SELECT COUNT(*) FROM movements')===$movementCount && DB::fetchValue('SELECT SUM(quantity) FROM inventory')===$stockCount,'Repeated multi-item submissions cannot duplicate purchases, movements or inventory');
    $duplicateRice=$multi;$duplicateRice['request_key']=bin2hex(random_bytes(32));$duplicateRice['items'][1]['rice_type_id']=$rice;request('/purchase/new',$duplicateRice);
    check(str_contains(request('/purchase/new')['body'],'This rice type is already selected') && DB::fetchValue('SELECT COUNT(*) FROM purchases')===$purchaseCount && DB::fetchValue('SELECT SUM(quantity) FROM inventory')===$stockCount,'Duplicate rice types are rejected on the server without changing purchases or inventory');
    $invalidMulti=$multi;$invalidMulti['request_key']=bin2hex(random_bytes(32));$invalidMulti['items'][1]['quantity']='0';request('/purchase/new',$invalidMulti);
    $retry=request('/purchase/new');
    check(str_contains($retry['body'],'Item 2:') && str_contains($retry['body'],'items[2][rice_type_id]') && str_contains($retry['body'],'value="3.333"')===false && str_contains($retry['body'],'data-initial-step="1"'),'Invalid item retains all entered rows and reopens the rice item step');
    $invalidMulti=$multi;$invalidMulti['request_key']=bin2hex(random_bytes(32));$invalidMulti['amount_paid']='104';request('/purchase/new',$invalidMulti);
    check(DB::fetchValue('SELECT COUNT(*) FROM purchases')===$purchaseCount && DB::fetchValue('SELECT COUNT(*) FROM purchase_items')===$itemCount && DB::fetchValue('SELECT COUNT(*) FROM movements')===$movementCount && DB::fetchValue('SELECT SUM(quantity) FROM inventory')===$stockCount,'Multi-item overpayment rolls back the entire purchase and every stock write');
    $missingMulti=$multi;$missingMulti['request_key']=bin2hex(random_bytes(32));$missingMulti['items'][1]['rice_type_id']='999999';request('/purchase/new',$missingMulti);
    $emptyMulti=$multi;$emptyMulti['request_key']=bin2hex(random_bytes(32));$emptyMulti['items']=[];
    try{\App\Services\Purchase::record($emptyMulti,$owner);throw new RuntimeException('Empty purchase should fail.');}catch(InvalidArgumentException $error){}
    $tooMany=$multi;$tooMany['request_key']=bin2hex(random_bytes(32));$tooMany['items']=array_fill(0,51,$multi['items'][0]);
    try{\App\Services\Purchase::record($tooMany,$owner);throw new RuntimeException('Excess items should fail.');}catch(InvalidArgumentException $error){}
    check(DB::fetchValue('SELECT COUNT(*) FROM purchases')===$purchaseCount && DB::fetchValue('SELECT SUM(quantity) FROM inventory')===$stockCount,'Missing rice types, empty item lists and excessive item counts create no business records');
    DB::statement('INSERT INTO inventory (warehouse_id,rice_type_id,quantity) VALUES (?,?,?)',[$w2,$multiRice,'5.125']);
    $productionWizard=request('/production');preg_match('/name="request_key" value="([a-f0-9]+)"/',$productionWizard['body'],$productionKey);
    check(str_contains($productionWizard['body'],'data-operation="production"') && str_contains($productionWizard['body'],'Review production') && !str_contains($productionWizard['body'],'name="amount_paid"'),'Production uses a three-step wizard with stock review and no payment fields');
    $productionBatch=['_token'=>token($productionWizard),'request_key'=>$productionKey[1],'warehouse_id'=>$w2,'occurred_on'=>'2026-10-06','notes'=>'Production batch A','items'=>[['rice_type_id'=>$rice,'quantity'=>'2.125'],['rice_type_id'=>$multiRice,'quantity'=>'3.125']]];
    $financialBefore=hash('sha256',json_encode([DB::fetchAll('SELECT * FROM purchases ORDER BY id'),DB::fetchAll('SELECT * FROM supplier_payments ORDER BY id')]));
    check(request('/production',$productionBatch)['status']===302 && DB::fetchValue('SELECT quantity FROM inventory WHERE warehouse_id=? AND rice_type_id=?',[$w2,$rice])==='17.875' && DB::fetchValue('SELECT quantity FROM inventory WHERE warehouse_id=? AND rice_type_id=?',[$w2,$multiRice])==='2.000','Production wizard deducts every rice item using exact quantities');
    $productionMovementCount=DB::fetchValue('SELECT COUNT(*) FROM movements');$productionStock=DB::fetchValue('SELECT SUM(quantity) FROM inventory');
    request('/production',$productionBatch);
    check(DB::fetchValue('SELECT COUNT(*) FROM movements')===$productionMovementCount && DB::fetchValue('SELECT SUM(quantity) FROM inventory')===$productionStock,'Repeated multi-item production submissions cannot duplicate deductions');
    $failedBatch=$productionBatch;$failedBatch['request_key']=bin2hex(random_bytes(32));$failedBatch['items'][0]['quantity']='1';$failedBatch['items'][1]['quantity']='3';request('/production',$failedBatch);
    $productionRetry=request('/production');
    check(DB::fetchValue('SELECT COUNT(*) FROM movements')===$productionMovementCount && DB::fetchValue('SELECT SUM(quantity) FROM inventory')===$productionStock && str_contains($productionRetry['body'],'Item 2: Insufficient stock') && str_contains($productionRetry['body'],'items[1][quantity]') && str_contains($productionRetry['body'],'data-initial-step="1"'),'Insufficient production stock rolls back all items and retains the item-step draft');
    $duplicateBatch=$productionBatch;$duplicateBatch['request_key']=bin2hex(random_bytes(32));$duplicateBatch['items'][1]['rice_type_id']=$rice;request('/production',$duplicateBatch);
    check(DB::fetchValue('SELECT SUM(quantity) FROM inventory')===$productionStock && str_contains(request('/production')['body'],'already selected'),'Production rejects duplicate rice types without changing stock');
    check(hash('sha256',json_encode([DB::fetchAll('SELECT * FROM purchases ORDER BY id'),DB::fetchAll('SELECT * FROM supplier_payments ORDER BY id')]))===$financialBefore,'Production wizard never changes purchases or supplier payments');
    $transferWizard=request('/transfer');preg_match('/name="request_key" value="([a-f0-9]+)"/',$transferWizard['body'],$transferKey);
    check(str_contains($transferWizard['body'],'data-operation="transfer"') && str_contains($transferWizard['body'],'Destination warehouse') && str_contains($transferWizard['body'],'Dest. after'),'Transfer uses a three-step wizard with source and destination balance review');
    $transferBatch=['_token'=>token($transferWizard),'request_key'=>$transferKey[1],'warehouse_id'=>$w2,'destination_id'=>$w1,'occurred_on'=>'2026-10-06','notes'=>'Multi-item transfer','items'=>[['rice_type_id'=>$rice,'quantity'=>'1.875'],['rice_type_id'=>$multiRice,'quantity'=>'0.500']]];
    $totalStockBefore=DB::fetchValue('SELECT SUM(quantity) FROM inventory');
    $destinationBefore=DB::fetchValue('SELECT quantity FROM inventory WHERE warehouse_id=? AND rice_type_id=?',[$w1,$rice]);
    check(request('/transfer',$transferBatch)['status']===302 && DB::fetchValue('SELECT quantity FROM inventory WHERE warehouse_id=? AND rice_type_id=?',[$w2,$rice])==='16.000' && DB::fetchValue('SELECT quantity FROM inventory WHERE warehouse_id=? AND rice_type_id=?',[$w2,$multiRice])==='1.500','Multi-item transfer deducts exact quantities from every source rice balance');
    check((bool)DB::fetchValue('SELECT quantity=CAST(? AS DECIMAL(16,3))+1.875 FROM inventory WHERE warehouse_id=? AND rice_type_id=?',[$destinationBefore,$w1,$rice]) && DB::fetchValue('SELECT quantity FROM inventory WHERE warehouse_id=? AND rice_type_id=?',[$w1,$multiRice])==='3.833' && DB::fetchValue('SELECT SUM(quantity) FROM inventory')===$totalStockBefore,'Multi-item transfer adds every destination balance and conserves total stock');
    $transferMovementCount=DB::fetchValue('SELECT COUNT(*) FROM movements');$transferInventory=hash('sha256',json_encode(DB::fetchAll('SELECT * FROM inventory ORDER BY warehouse_id,rice_type_id')));
    request('/transfer',$transferBatch);
    check(DB::fetchValue('SELECT COUNT(*) FROM movements')===$transferMovementCount && hash('sha256',json_encode(DB::fetchAll('SELECT * FROM inventory ORDER BY warehouse_id,rice_type_id')))===$transferInventory,'Repeated multi-item transfer submissions cannot duplicate movements or balances');
    $failedTransfer=$transferBatch;$failedTransfer['request_key']=bin2hex(random_bytes(32));$failedTransfer['items'][0]['quantity']='1';$failedTransfer['items'][1]['quantity']='2';request('/transfer',$failedTransfer);
    $transferRetry=request('/transfer');
    check(DB::fetchValue('SELECT COUNT(*) FROM movements')===$transferMovementCount && hash('sha256',json_encode(DB::fetchAll('SELECT * FROM inventory ORDER BY warehouse_id,rice_type_id')))===$transferInventory && str_contains($transferRetry['body'],'Item 2: Insufficient stock') && str_contains($transferRetry['body'],'data-initial-step="1"'),'One insufficient item rolls back both warehouses and retains the transfer draft');
    $sameWarehouse=$transferBatch;$sameWarehouse['request_key']=bin2hex(random_bytes(32));$sameWarehouse['destination_id']=$w2;request('/transfer',$sameWarehouse);
    $duplicateTransfer=$transferBatch;$duplicateTransfer['request_key']=bin2hex(random_bytes(32));$duplicateTransfer['items'][1]['rice_type_id']=$rice;request('/transfer',$duplicateTransfer);
    check(DB::fetchValue('SELECT COUNT(*) FROM movements')===$transferMovementCount && hash('sha256',json_encode(DB::fetchAll('SELECT * FROM inventory ORDER BY warehouse_id,rice_type_id')))===$transferInventory,'Same-warehouse transfers and duplicate rice types cannot change stock');
    check(hash('sha256',json_encode([DB::fetchAll('SELECT * FROM purchases ORDER BY id'),DB::fetchAll('SELECT * FROM supplier_payments ORDER BY id')]))===$financialBefore,'Transfer wizard leaves purchase and supplier-payment records unchanged');
    require __DIR__.'/operation-crud.php';
    require __DIR__.'/list-pagination.php';
    require __DIR__.'/locale.php';
    $userForm=request('/users');
    $staffCreate=request('/users',['_token'=>token($userForm),'name'=>'Test Staff','email'=>'staff@example.test','password'=>'test-staff-password','active'=>'1','permissions'=>['inventory.view','production.create','users.manage']]);
    check($staffCreate['status']===302,'Owner can create staff account');
    check(!str_contains(DB::fetchValue('SELECT permissions FROM users WHERE email=?',['staff@example.test']),'users.manage'),'Staff cannot be granted owner account management');
    $logout=request('/logout',['_token'=>token(request('/'))]);check($logout['status']===302,'Sign out works');
    $login=request('/login');check(request('/login',['_token'=>token($login),'email'=>'staff@example.test','password'=>'test-staff-password'])['status']===302,'Staff sign in works');
    foreach(['/users','/settings','/warehouses','/rice-types','/suppliers','/supplier-credits','/suppliers/'.$supplier.'/credit','/reports','/purchases','/purchase/new','/transfer'] as $path)check(request($path)['status']===403,'Staff permission blocks '.$path);
    $directoryStaffId=(int)DB::fetchValue('SELECT id FROM users WHERE email=?',['staff@example.test']);
    DB::statement('UPDATE users SET permissions=? WHERE id=?',[json_encode(['inventory.view','production.create','settings.manage']),$directoryStaffId]);
    $restrictedDirectory=request('/suppliers?q=Pagination%20fixture&balance=outstanding&per_page=10');
    $restrictedDirectoryMarkup=preg_replace('#<script\b[^>]*>.*?</script>#s','',$restrictedDirectory['body']);
    check($restrictedDirectory['status']===200 && str_contains($restrictedDirectoryMarkup,'Showing 1–10 of 35 records') && !str_contains($restrictedDirectoryMarkup,'name="balance"') && !str_contains($restrictedDirectoryMarkup,'Balance to pay'),'Supplier credit filters and balances remain unavailable without credit permission');
    DB::statement('UPDATE users SET permissions=? WHERE id=?',[json_encode(['inventory.view','production.create']),$directoryStaffId]);
    foreach(['purchase'=>$crudPurchase,'transfer'=>$crudTransfer,'production'=>$crudProduction] as $kind=>$id) {
        check(request('/'.$kind.'/'.$id.'/edit')['status']===403 && request('/'.$kind.'/'.$id.'/edit',['_token'=>token(request('/profile')),'version'=>'1'])['status']===403 && request('/'.$kind.'/'.$id.'/delete',['_token'=>token(request('/profile')),'version'=>'1'])['status']===403,'Staff without mutation permission cannot edit or delete: '.$kind);
    }
    check(request('/voucher-settings')['status']===403 && request('/voucher-settings/preview')['status']===403 && request('/voucher-settings',$voucherSettings)['status']===403,'Staff without settings permission cannot view or modify voucher settings');
    check(request('/suppliers/'.$supplier.'/payments',$payment)['status']===403,'Direct staff POST cannot record supplier payments');
    $staffDash=request('/');check($staffDash['status']===200 && !str_contains($staffDash['body'],'2,503,150.03') && !str_contains($staffDash['body'],'id="chart-data"'),'Staff dashboard does not expose purchase amounts without report access');
    check(request('/users',['_token'=>token($staffDash),'name'=>'Intruder','email'=>'intruder@example.test','password'=>'intruder-password','active'=>'1'])['status']===403,'Direct staff POST cannot create accounts');
    $profile=request('/profile');check($profile['status']===200 && str_contains($profile['body'],'Change password'),'Staff can access their own profile settings');
    $profileToken=token($profile);$staffId=(int)DB::fetchValue('SELECT id FROM users WHERE email=?',['staff@example.test']);
    check(request('/profile',['name'=>'Updated Staff','email'=>'staff@example.test'])['status']===419,'Profile updates require CSRF');
    request('/profile',['_token'=>$profileToken,'id'=>$owner,'name'=>'Updated Staff','email'=>'staff@example.test','role'=>'owner','permissions'=>['users.manage']]);
    check(DB::fetchValue('SELECT name FROM users WHERE id=?',[$staffId])==='Updated Staff' && DB::fetchValue('SELECT role FROM users WHERE id=?',[$staffId])==='staff' && DB::fetchValue('SELECT name FROM users WHERE id=?',[$owner])==='Test Owner','Profile changes affect only current user and cannot escalate role');
    request('/profile',['_token'=>$profileToken,'name'=>'Updated Staff','email'=>'newstaff@example.test','current_password'=>'wrong-password']);
    check(DB::fetchValue('SELECT email FROM users WHERE id=?',[$staffId])==='staff@example.test','Email change rejects incorrect current password');
    request('/profile',['_token'=>$profileToken,'name'=>'Updated Staff','email'=>'owner@example.test','current_password'=>'test-staff-password']);
    check(DB::fetchValue('SELECT email FROM users WHERE id=?',[$staffId])==='staff@example.test' && str_contains(request('/profile')['body'],'already in use'),'Profile rejects duplicate email without changing account');
    request('/profile',['_token'=>$profileToken,'name'=>'Updated Staff','email'=>'newstaff@example.test','current_password'=>'test-staff-password']);
    check(DB::fetchValue('SELECT email FROM users WHERE id=?',[$staffId])==='newstaff@example.test','Verified email change succeeds');
    $profileToken=token(request('/profile'));
    $passwordData=['_token'=>$profileToken,'current_password'=>'wrong-password','new_password'=>' new-secure-password ','password_confirmation'=>' new-secure-password '];
    $oldHash=DB::fetchValue('SELECT password FROM users WHERE id=?',[$staffId]);
    request('/profile/password',$passwordData);check(DB::fetchValue('SELECT password FROM users WHERE id=?',[$staffId])===$oldHash,'Password change rejects incorrect current password');
    $passwordData['current_password']='test-staff-password';$passwordData['password_confirmation']='not-matching';request('/profile/password',$passwordData);
    check(DB::fetchValue('SELECT password FROM users WHERE id=?',[$staffId])===$oldHash,'Password confirmation mismatch leaves hash unchanged');
    $passwordData['password_confirmation']=$passwordData['new_password'];
    $currentCookie=$cookie;$cookie='';$secondLogin=request('/login');request('/login',['_token'=>token($secondLogin),'email'=>'newstaff@example.test','password'=>'test-staff-password']);$otherCookie=$cookie;$cookie=$currentCookie;
    request('/profile/password',$passwordData);
    check(password_verify($passwordData['new_password'],DB::fetchValue('SELECT password FROM users WHERE id=?',[$staffId])) && request('/profile')['status']===200,'Password change stores valid hash and preserves current session');
    $newCookie=$cookie;$cookie=$otherCookie;check(request('/profile')['location']==='/login','Password change revokes other logged-in sessions');$cookie=$newCookie;
    request('/logout',['_token'=>token(request('/profile'))]);$login=request('/login');request('/login',['_token'=>token($login),'email'=>'newstaff@example.test','password'=>'test-staff-password']);
    check(request('/profile')['location']==='/login','Old password no longer signs in');
    $login=request('/login');request('/login',['_token'=>token($login),'email'=>'newstaff@example.test','password'=>$passwordData['new_password']]);check(request('/profile')['status']===200,'New password signs in without trimming significant spaces');
    DB::statement('UPDATE users SET active=0 WHERE id=?',[$staffId]);check(request('/inventory')['location']==='/login','Disabled account loses existing-session access');
    echo "\nAll integration checks passed.\n";
} finally {
    foreach($testLogos as $file){$path=\App\Services\VoucherSettings::logoPath($file);if($path && is_file($path))unlink($path);}
    if(is_resource($server)){proc_terminate($server);proc_close($server);}
    if(preg_match('/^rice_test_[a-f0-9]{12}$/D',$testDatabase)) $pdo->exec("DROP DATABASE IF EXISTS `$testDatabase`");
}
