<?php
use Mini\Database as DB;
use App\Services\Stock;
use App\Services\SupplierCredit;

// Isolated warehouses keep these CRUD checks independent of earlier fixtures.
$crudSource=DB::insert('warehouses',['name'=>'CRUD source']);
$crudDestination=DB::insert('warehouses',['name'=>'CRUD destination']);
$crudSupplier=DB::insert('suppliers',['name'=>'CRUD supplier']);
$crudPurchaseData=['warehouse_id'=>$crudSource,'supplier_id'=>$crudSupplier,'occurred_on'=>'2027-03-01','notes'=>'CRUD purchase','amount_paid'=>'0','request_key'=>bin2hex(random_bytes(32)),'items'=>[
    ['rice_type_id'=>$rice,'quantity'=>'100','unit_price'=>'2','weight_lb'=>'1000'],
    ['rice_type_id'=>$multiRice,'quantity'=>'50','unit_price'=>'3','weight_lb'=>'500'],
]];
$crudPurchase=Stock::record('purchase',$crudPurchaseData,$owner);
$originalReference=DB::fetchValue('SELECT reference FROM purchases WHERE id=?',[$crudPurchase]);
$stockQuantity=static fn($warehouse,$type)=>DB::fetchValue('SELECT quantity FROM inventory WHERE warehouse_id=? AND rice_type_id=?',[$warehouse,$type]);
$crudTransferData=['warehouse_id'=>$crudSource,'destination_id'=>$crudDestination,'occurred_on'=>'2027-03-02','notes'=>'CRUD transfer','request_key'=>bin2hex(random_bytes(32)),'items'=>[['rice_type_id'=>$rice,'quantity'=>'20'],['rice_type_id'=>$multiRice,'quantity'=>'10']]];
$crudTransfer=Stock::record('transfer',$crudTransferData,$owner);
$crudProductionData=['warehouse_id'=>$crudDestination,'occurred_on'=>'2027-03-03','notes'=>'CRUD production','request_key'=>bin2hex(random_bytes(32)),'items'=>[['rice_type_id'=>$rice,'quantity'=>'5'],['rice_type_id'=>$multiRice,'quantity'=>'2']]];
$crudProduction=Stock::record('production',$crudProductionData,$owner);
$crudLedger=request('/stock-movements?q=CRUD&from=2027-03-01&to=2027-03-03');
check(str_contains($crudLedger['body'],'href="/transfer/'.$crudTransfer.'"') && str_contains($crudLedger['body'],'href="/production/'.$crudProduction.'"') && str_contains($crudLedger['body'],'href="/transfer/'.$crudTransfer.'/edit"'),'Stock ledger actions open grouped transfer and production details and edit forms');
foreach(['purchase'=>$crudPurchase,'transfer'=>$crudTransfer,'production'=>$crudProduction] as $kind=>$id) {
    $detail=request('/'.$kind.'/'.$id);$edit=request('/'.$kind.'/'.$id.'/edit');
    check($detail['status']===200 && str_contains($detail['body'],'2 rice items') && str_contains($detail['body'],'Second variety for multi purchase'),'Detail page displays all items: '.$kind);
    check($edit['status']===200 && str_contains($edit['body'],'name="version"') && str_contains($edit['body'],'items[1][rice_type_id]') && str_contains($edit['body'],'value="'.$crudSource.'"'),'Edit wizard preloads operation items and version: '.$kind);
}
$transferChild=(int)DB::fetchValue('SELECT id FROM movements WHERE operation_id=? AND id<>?',[$crudTransfer,$crudTransfer]);
check(request('/transfer/'.$transferChild)['status']===404 && request('/production/'.$crudTransfer)['status']===404 && request('/purchase/999999999')['status']===404,'Missing records, child movement IDs and wrong operation types return 404');
$crudToken=token(request('/purchase/'.$crudPurchase.'/edit'));
$snap=static fn()=>hash('sha256',json_encode([DB::fetchAll('SELECT * FROM purchases ORDER BY id'),DB::fetchAll('SELECT * FROM purchase_items ORDER BY id'),DB::fetchAll('SELECT * FROM movements ORDER BY id'),DB::fetchAll('SELECT * FROM inventory ORDER BY warehouse_id,rice_type_id')]));
$snapshot=$snap();
check(request('/purchase/'.$crudPurchase.'/edit',$crudPurchaseData+['version'=>'1'])['status']===419 && request('/transfer/'.$crudTransfer.'/delete',['version'=>'1'])['status']===419 && $snap()===$snapshot,'Edit and delete requests require CSRF and leave all business records unchanged');
$updatedPurchase=$crudPurchaseData; $updatedPurchase['_token']=$crudToken;$updatedPurchase['version']='1';$updatedPurchase['request_key']=bin2hex(random_bytes(32));
$updatedPurchase['items'][0]['quantity']='90';$updatedPurchase['items'][1]['unit_price']='4';
check(request('/purchase/'.$crudPurchase.'/edit',$updatedPurchase)['location']==='/purchase/'.$crudPurchase && $stockQuantity($crudSource,$rice)==='70.000' && SupplierCredit::balance($crudSupplier)==='380.00','Purchase update applies exact stock and amount differences');
check(DB::fetchValue('SELECT reference FROM purchases WHERE id=?',[$crudPurchase])===$originalReference && (int)DB::fetchValue('SELECT version FROM purchases WHERE id=?',[$crudPurchase])===2 && (int)DB::fetchValue('SELECT COUNT(*) FROM movements WHERE purchase_id=?',[$crudPurchase])===2,'Purchase edit preserves voucher identity and replaces all lines without duplicate movements');
$snapshot=$snap();request('/purchase/'.$crudPurchase.'/edit',$updatedPurchase);
check($snap()===$snapshot && str_contains(request('/purchase/'.$crudPurchase.'/edit')['body'],'changed after you opened'),'Repeated or stale edit cannot apply stock changes twice');
$notesOnly=$updatedPurchase;$notesOnly['version']='2';$notesOnly['notes']='Notes after rice consumption';$notesOnly['request_key']=bin2hex(random_bytes(32));
request('/purchase/'.$crudPurchase.'/edit',$notesOnly);
check((int)DB::fetchValue('SELECT version FROM purchases WHERE id=?',[$crudPurchase])===3 && $stockQuantity($crudSource,$rice)==='70.000','Notes-only purchase edits remain possible after stock has been transferred or consumed');
$badPurchase=$notesOnly;$badPurchase['version']='3';$badPurchase['items'][0]['quantity']='1';$snapshot=$snap();request('/purchase/'.$crudPurchase.'/edit',$badPurchase);
check($snap()===$snapshot && str_contains(request('/purchase/'.$crudPurchase.'/edit')['body'],'insufficient stock'),'Reducing a consumed purchase beyond available stock rolls back every change');
SupplierCredit::pay($crudSupplier,'375','2027-03-04','CRUD payment',bin2hex(random_bytes(32)),$owner);
$badCredit=$notesOnly;$badCredit['version']='3';$badCredit['items'][1]['unit_price']='3';$snapshot=$snap();request('/purchase/'.$crudPurchase.'/edit',$badCredit);
check($snap()===$snapshot && SupplierCredit::balance($crudSupplier)==='5.00' && str_contains(request('/purchase/'.$crudPurchase.'/edit')['body'],'below payments'),'Purchase edit cannot create a negative supplier credit balance after later payments');
$otherSupplier=DB::insert('suppliers',['name'=>'CRUD changed supplier']);$badCredit['supplier_id']=$otherSupplier;$snapshot=$snap();request('/purchase/'.$crudPurchase.'/edit',$badCredit);
check($snap()===$snapshot,'Changing the supplier rolls back if existing payments would overpay the original supplier');
$updatedTransfer=$crudTransferData+['_token'=>$crudToken,'version'=>'1'];$updatedTransfer['request_key']=bin2hex(random_bytes(32));$updatedTransfer['items'][0]['quantity']='25';$updatedTransfer['items'][1]['quantity']='12';
$financeBefore=hash('sha256',json_encode([DB::fetchAll('SELECT * FROM purchases ORDER BY id'),DB::fetchAll('SELECT * FROM supplier_payments ORDER BY id')]));
request('/transfer/'.$crudTransfer.'/edit',$updatedTransfer);
check($stockQuantity($crudSource,$rice)==='65.000' && $stockQuantity($crudDestination,$rice)==='20.000' && $stockQuantity($crudDestination,$multiRice)==='10.000' && (int)DB::fetchValue('SELECT COUNT(*) FROM movements WHERE operation_id=?',[$crudTransfer])===2,'Transfer edit updates every source and destination balance and keeps its group ID');
$badTransfer=$updatedTransfer;$badTransfer['version']='2';$badTransfer['items'][0]['quantity']='1';$snapshot=$snap();request('/transfer/'.$crudTransfer.'/edit',$badTransfer);
check($snap()===$snapshot && str_contains(request('/transfer/'.$crudTransfer.'/edit')['body'],'insufficient stock'),'Reducing a transfer whose destination rice was consumed rolls back the whole batch');
$updatedProduction=$crudProductionData+['_token'=>$crudToken,'version'=>'1'];$updatedProduction['request_key']=bin2hex(random_bytes(32));$updatedProduction['items']=[['rice_type_id'=>$rice,'quantity'=>'8']];
request('/production/'.$crudProduction.'/edit',$updatedProduction);
check($stockQuantity($crudDestination,$rice)==='17.000' && $stockQuantity($crudDestination,$multiRice)==='12.000' && (int)DB::fetchValue('SELECT COUNT(*) FROM movements WHERE operation_id=?',[$crudProduction])===1,'Production edit changes quantities and restores stock for removed rice items');
$badProduction=$updatedProduction;$badProduction['version']='2';$badProduction['items'][0]['quantity']='999';$snapshot=$snap();request('/production/'.$crudProduction.'/edit',$badProduction);
check($snap()===$snapshot && str_contains(request('/production/'.$crudProduction.'/edit')['body'],'insufficient stock'),'Oversized production edit rolls back and retains the entered item');
$snapshot=$snap();request('/transfer/'.$crudTransfer.'/delete',['_token'=>$crudToken,'version'=>'2']);
check($snap()===$snapshot && str_contains(request('/transfer/'.$crudTransfer)['body'],'insufficient stock'),'Deleting a transfer is blocked if destination stock cannot cover reversal');
request('/production/'.$crudProduction.'/delete',['_token'=>$crudToken,'version'=>'2']);
check(request('/production/'.$crudProduction)['status']===404 && $stockQuantity($crudDestination,$rice)==='25.000' && $stockQuantity($crudDestination,$multiRice)==='12.000','Production delete restores exactly the latest edited quantities');
request('/transfer/'.$crudTransfer.'/delete',['_token'=>$crudToken,'version'=>'2']);
check(request('/transfer/'.$crudTransfer)['status']===404 && $stockQuantity($crudSource,$rice)==='90.000' && $stockQuantity($crudSource,$multiRice)==='50.000' && $stockQuantity($crudDestination,$rice)==='0.000','Transfer delete returns the whole edited batch to its source');
check(hash('sha256',json_encode([DB::fetchAll('SELECT * FROM purchases ORDER BY id'),DB::fetchAll('SELECT * FROM supplier_payments ORDER BY id')]))===$financeBefore,'Transfer and production CRUD never changes purchase or payment records');
$snapshot=$snap();request('/purchase/'.$crudPurchase.'/delete',['_token'=>$crudToken,'version'=>'3']);
check($snap()===$snapshot && str_contains(request('/purchase/'.$crudPurchase)['body'],'below payments'),'Purchase delete rolls back when existing supplier payments would exceed remaining credit');
$deleteSupplier=DB::insert('suppliers',['name'=>'CRUD removable supplier']);$deleteData=$crudPurchaseData;$deleteData['supplier_id']=$deleteSupplier;$deleteData['request_key']=bin2hex(random_bytes(32));$deleteId=Stock::record('purchase',$deleteData,$owner);
$beforeDelete=$stockQuantity($crudSource,$rice);
request('/purchase/'.$deleteId.'/delete',['_token'=>$crudToken,'version'=>'1']);
check(request('/purchase/'.$deleteId)['status']===404 && DB::fetchValue('SELECT COUNT(*) FROM purchase_items WHERE purchase_id=?',[$deleteId])===0 && DB::fetchValue('SELECT COUNT(*) FROM movements WHERE purchase_id=?',[$deleteId])===0 && $stockQuantity($crudSource,$rice)==='90.000' && SupplierCredit::balance($deleteSupplier)==='0.00','Purchase delete reverses stock and removes voucher items, movements and unpaid credit together');
check(request('/purchase/'.$deleteId.'/delete',['_token'=>$crudToken,'version'=>'1'])['status']===404 && $stockQuantity($crudSource,$rice)==='90.000','Repeated delete cannot reverse stock twice');
