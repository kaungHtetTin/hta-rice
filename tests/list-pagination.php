<?php
use Mini\Database as DB;
use App\Services\Stock;
// Runs inside the disposable database and authenticated owner session in run.php.
$directorySupplier=DB::insert('suppliers',['name'=>'Pagination fixture 00','phone'=>'091234567','address'=>'Pagination street']);
for($i=1;$i<=34;$i++) DB::insert('suppliers',['name'=>sprintf('Pagination fixture %02d',$i)]);
$fixtureReferences=[];
for($i=1;$i<=35;$i++) {
    $id=Stock::record('purchase',['warehouse_id'=>$w1,'supplier_id'=>$directorySupplier,'occurred_on'=>'2027-01-15','notes'=>sprintf('Pagination movement %02d',$i),'amount_paid'=>'0','request_key'=>bin2hex(random_bytes(32)),'items'=>[
        ['rice_type_id'=>$rice,'quantity'=>'1','unit_price'=>'1','weight_lb'=>''],
        ['rice_type_id'=>$multiRice,'quantity'=>'1','unit_price'=>'1','weight_lb'=>''],
    ]],$owner);
    $fixtureReferences[]=DB::fetchValue('SELECT reference FROM purchases WHERE id=?',[$id]);
}
$filter='?from=2027-01-15&to=2027-01-15&warehouse='.$w1.'&supplier='.$directorySupplier.'&rice_type='.$multiRice.'&per_page=10';
$firstPage=request('/purchases'.$filter);
$secondPage=request('/purchases'.$filter.'&page=2');
check($firstPage['status']===200 && str_contains($firstPage['body'],'Showing 1–10 of 35 records') && str_contains($secondPage['body'],'Showing 11–20 of 35 records'),'Purchase filters count vouchers once despite multiple matching items and paginate ten at a time');
check(str_contains($firstPage['body'],$fixtureReferences[34]) && !str_contains($firstPage['body'],$fixtureReferences[24]) && str_contains($secondPage['body'],$fixtureReferences[24]) && !str_contains($secondPage['body'],$fixtureReferences[34]),'Purchase pages contain distinct rows in stable newest-first order');
preg_match('/rel="next" href="([^"]+)"/',$firstPage['body'],$nextPage);
$nextUrl=html_entity_decode($nextPage[1],ENT_QUOTES,'UTF-8'); parse_str(parse_url($nextUrl,PHP_URL_QUERY),$nextFilters);
check($nextFilters['page']==='2' && $nextFilters['from']==='2027-01-15' && $nextFilters['to']==='2027-01-15' && (int)$nextFilters['warehouse']===$w1 && (int)$nextFilters['supplier']===$directorySupplier && (int)$nextFilters['rice_type']===$multiRice && $nextFilters['per_page']==='10','Pagination links preserve every applied purchase filter');
check(str_contains(request('/purchases'.$filter.'&page=999')['body'],'Showing 31–35 of 35 records') && str_contains(request('/purchases'.$filter.'&page=0')['body'],'Showing 1–10 of 35 records'),'Out-of-range page numbers clamp to valid pages');
check(str_contains(request('/purchases?from=2027-01-16&to=2027-01-16')['body'],'Showing 0–0 of 0 records'),'Purchase dates are inclusive and exclude other days');
$ledgerFilter='?q=Pagination%20movement&kind=purchase&from=2027-01-15&to=2027-01-15&warehouse='.$w1.'&rice_type='.$multiRice.'&per_page=10';
$ledger=request('/stock-movements'.$ledgerFilter.'&page=4');
check($ledger['status']===200 && str_contains($ledger['body'],'Showing 31–35 of 35 records') && str_contains($ledger['body'],'Pagination movement 05') && !str_contains($ledger['body'],'Pagination movement 06'),'Stock ledger searches and filters the entire history with stable pagination');
foreach([$w1,$w2] as $warehouseId) {
    $transferLedger=request('/stock-movements?q=Multi-item%20transfer&kind=transfer&warehouse='.$warehouseId.'&rice_type='.$multiRice);
    check(str_contains($transferLedger['body'],'Showing 1–1 of 1 records'),'Warehouse filter includes source and destination transfers: '.$warehouseId);
}
$suppliersPage=request('/suppliers?q=Pagination%20fixture&per_page=10&page=2');
preg_match('/<tbody>(.*?)<\/tbody>/s',$suppliersPage['body'],$supplierBody);
check(str_contains($suppliersPage['body'],'Showing 11–20 of 35 records') && str_contains($supplierBody[1],'Pagination fixture 10') && !str_contains($supplierBody[1],'Pagination fixture 00'),'Supplier directory filters and paginates in stable name order');
check(str_contains(request('/suppliers?q=Pagination%20fixture&balance=outstanding')['body'],'Showing 1–1 of 1 records') && str_contains(request('/suppliers?q=Pagination%20fixture&balance=settled')['body'],'Showing 1–25 of 34 records'),'Supplier credit filters account for purchase payments and later payments');
\App\Services\SupplierCredit::pay($directorySupplier,'70','2027-01-16','Settle pagination fixtures',bin2hex(random_bytes(32)),$owner);
check(str_contains(request('/suppliers?q=Pagination%20fixture&balance=outstanding')['body'],'Showing 0–0 of 0 records') && str_contains(request('/suppliers?q=Pagination%20fixture&balance=settled')['body'],'Showing 1–25 of 35 records'),'Settled supplier payments update the directory credit filters');
check(str_contains(request('/suppliers?q=091234567')['body'],'Showing 1–1 of 1 records') && str_contains(request('/suppliers?q=Pagination%20street')['body'],'Showing 1–1 of 1 records'),'Supplier search matches phone and address');
foreach(['/purchases','/stock-movements'] as $path) {
    $invalidDates=request($path.'?from=2027-02-30');
    check($invalidDates['status']===200 && str_contains($invalidDates['body'],'Enter a valid From date.') && str_contains($invalidDates['body'],'Showing 0–0 of 0 records'),'Invalid dates produce visible errors and no unfiltered results: '.$path);
    check(str_contains(request($path.'?from=2027-02-01&to=2027-01-01')['body'],'From date must be on or before To date.'),'Reversed date ranges produce a visible error: '.$path);
}
foreach(['/purchases','/stock-movements','/suppliers'] as $path) {
    $malformed=request($path.'?q[]=bad&warehouse[]=1&page[]=2&per_page=100000&from[]=bad');
    check($malformed['status']===200 && !str_contains($malformed['body'],'Warning:') && !str_contains($malformed['body'],'Fatal error'),'Malformed parameters use bounded safe defaults: '.$path);
    check(str_contains(request($path.'?q=%25')['body'],'Showing 0–0 of 0 records'),'Search treats SQL wildcard characters literally: '.$path);
    preg_match('/<form class="list-filters".*?<\/form>/s',request($path)['body'],$filterForm);
    check(!str_contains($filterForm[0],'name="page"'),'Applying filters starts at page one: '.$path);
}
