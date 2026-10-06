<?php
declare(strict_types=1);
$testDatabase='rice_test_'.bin2hex(random_bytes(6));$_ENV['DB_DATABASE']=$testDatabase;
require dirname(__DIR__).'/bootstrap/app.php';
use Mini\Database as DB;
function checkMigration(bool $passed,string $message):void{if(!$passed)throw new RuntimeException('FAIL: '.$message);echo 'PASS: '.$message."\n";}
$pdo=DB::connection(true);
try {
    $pdo->exec("CREATE DATABASE `$testDatabase` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    foreach(glob(BASE_PATH.'/migrations/*.php') as $file){if(str_contains($file,'005_purchase_items'))break;$migration=require $file;foreach($migration['up'] as $sql)DB::connection()->exec($sql);}
    $user=DB::insert('users',['name'=>'Migration owner','email'=>'migration@example.test','password'=>'unused','role'=>'owner','permissions'=>'[]']);
    $warehouse=DB::insert('warehouses',['name'=>'Legacy warehouse']);$rice=DB::insert('rice_types',['name'=>'Legacy rice']);$supplier=DB::insert('suppliers',['name'=>'Legacy supplier']);
    $purchase=DB::insert('purchases',['reference'=>'LEGACY','supplier_id'=>$supplier,'warehouse_id'=>$warehouse,'rice_type_id'=>$rice,'quantity'=>'10.125','unit_price'=>'25.75','amount'=>'260.72','amount_paid'=>'100.00','weight_lb'=>'500.125','purchased_on'=>'2026-10-01','notes'=>'Existing record','user_id'=>$user,'request_key'=>str_repeat('a',64)]);
    DB::statement('INSERT INTO inventory VALUES (?,?,?)',[$warehouse,$rice,'10.125']);
    DB::insert('movements',['kind'=>'purchase','warehouse_id'=>$warehouse,'rice_type_id'=>$rice,'quantity'=>'10.125','occurred_on'=>'2026-10-01','notes'=>'Existing record','purchase_id'=>$purchase,'user_id'=>$user,'request_key'=>str_repeat('a',64)]);
    $before=DB::fetch('SELECT * FROM purchases WHERE id=?',[$purchase]);
    $migration=require BASE_PATH.'/migrations/20261006_005_purchase_items.php';foreach($migration['up'] as $sql)DB::connection()->exec($sql);
    $item=DB::fetch('SELECT * FROM purchase_items WHERE purchase_id=?',[$purchase]);
    checkMigration($item && $item['quantity']==='10.125' && $item['weight_lb']==='500.125' && $item['unit_price']==='25.75' && $item['amount']==='260.72','Legacy purchase becomes one exact rice item');
    checkMigration(DB::fetch('SELECT * FROM purchases WHERE id=?',[$purchase])===$before && DB::fetchValue('SELECT quantity FROM inventory')==='10.125' && (int)DB::fetchValue('SELECT COUNT(*) FROM movements')===1,'Migration preserves original purchases, payments, stock and ledger');
    checkMigration((int)DB::fetchValue("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=? AND TABLE_NAME='movements' AND COLUMN_NAME='purchase_id' AND NON_UNIQUE=1",[$testDatabase])>=1,'Purchase movements retain an indexed foreign key and allow multiple item entries');
    try{DB::connection()->exec($migration['down'][0]);throw new RuntimeException('Rollback should stop.');}catch(PDOException $error){checkMigration($error->getCode()==='45000','Unsafe rollback stops before changing existing purchase items');}
    $destination=DB::insert('warehouses',['name'=>'Legacy destination']);$groups=[];
    foreach(['transfer','production'] as $kind) {
        $key=bin2hex(random_bytes(32));$ids=[];
        foreach([0,1,49] as $index)$ids[]=DB::insert('movements',['kind'=>$kind,'warehouse_id'=>$warehouse,'destination_id'=>$kind==='transfer'?$destination:null,'rice_type_id'=>$rice,'quantity'=>'1','occurred_on'=>'2026-10-02','notes'=>'Identical legacy notes','user_id'=>$user,'request_key'=>$index===0?$key:hash('sha256',$key.':'.$kind.'-item:'.$index)]);
        $groups[$kind]=$ids;
    }
    $standalone=DB::insert('movements',['kind'=>'production','warehouse_id'=>$warehouse,'rice_type_id'=>$rice,'quantity'=>'1','occurred_on'=>'2026-10-02','notes'=>'Identical legacy notes','user_id'=>$user,'request_key'=>bin2hex(random_bytes(32))]);
    $beforeStock=DB::fetchAll('SELECT * FROM inventory');
    $migration=require BASE_PATH.'/migrations/20261006_006_operation_crud.php';foreach($migration['up'] as $sql)DB::connection()->exec($sql);
    foreach($groups as $kind=>$ids)checkMigration(array_map('intval',array_column(DB::fetchAll('SELECT operation_id FROM movements WHERE id IN ('.implode(',',$ids).') ORDER BY id'),'operation_id'))===array_fill(0,3,$ids[0]),'Migration groups legacy '.$kind.' rows by item keys, including the 50th item');
    checkMigration((int)DB::fetchValue('SELECT operation_id FROM movements WHERE id=?',[$standalone])===$standalone && DB::fetchValue('SELECT operation_id FROM movements WHERE purchase_id=?',[$purchase])===null && DB::fetchAll('SELECT * FROM inventory')===$beforeStock,'Migration preserves independent legacy operations and current inventory');
} finally {
    if(preg_match('/^rice_test_[a-f0-9]{12}$/D',$testDatabase))$pdo->exec("DROP DATABASE IF EXISTS `$testDatabase`");
}
