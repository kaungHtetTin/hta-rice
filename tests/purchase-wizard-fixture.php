<?php
// Render the real form with fixed directory choices for DOM interaction tests.
require dirname(__DIR__).'/bootstrap/app.php';
if(isset($argv[2]) && isset(\App\Services\Locale::SUPPORTED[$argv[2]]))$_SESSION['locale']=$argv[2];
$warehouses=[['id'=>1,'name'=>'Main warehouse']];
$riceTypes=[['id'=>1,'name'=>'First rice'],['id'=>2,'name'=>'Second rice']];
$suppliers=[['id'=>1,'name'=>'Test supplier']];$requestKey=str_repeat('a',64);
$balances=[['warehouse_id'=>1,'rice_type_id'=>1,'quantity'=>'10.125'],['warehouse_id'=>1,'rice_type_id'=>2,'quantity'=>'4.500']];
$mode=$argv[1]??'purchase';
$editingTransfer=$mode==='transfer-edit';if($editingTransfer)$mode='transfer';
if($mode==='transfer'){
    $kind='transfer';$warehouses[]=['id'=>2,'name'=>'Second warehouse'];
    $balances[]=['warehouse_id'=>2,'rice_type_id'=>1,'quantity'=>'1.250'];
}
if($editingTransfer){
    $editRecord=['id'=>7,'version'=>2];$formAction='transfer/7/edit';$cancelPath='transfer/7';
    $_SESSION['old']=['warehouse_id'=>'1','destination_id'=>'2','notes'=>'Existing transfer','items'=>[['rice_type_id'=>'1','quantity'=>'8']]];
    $balances[2]['quantity']='-5.000';
}
require BASE_PATH.'/app/Views/'.(in_array($mode,['production','transfer'],true)?'production-form':'purchase-form').'.php';

echo locale_scripts();
