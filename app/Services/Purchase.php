<?php
declare(strict_types=1);
namespace App\Services;
use Mini\Database as DB;
use InvalidArgumentException;

final class Purchase
{
    public const MAX_ITEMS=50;

    public static function record(array $data,int $user,int $edit=0,int $version=0): int
    {
        $date=valid_date((string)($data['occurred_on']??''));
        foreach(['warehouse_id','supplier_id'] as $field){
            if(filter_var($data[$field]??null,FILTER_VALIDATE_INT)===false || (int)$data[$field]<1)throw new InvalidArgumentException(t('Select a valid warehouse and supplier.'));
        }
        $key=$data['request_key']??'';
        if(!is_string($key)||!preg_match('/^[a-f0-9]{64}$/D',$key))throw new InvalidArgumentException(t('Invalid form reference. Refresh and try again.'));
        $notes=$data['notes']??'';
        if(!is_string($notes)||strlen($notes)>4000)throw new InvalidArgumentException(t('Notes must be less than 4,000 characters.'));
        // Preserve single-item API callers while the browser uses the new items array.
        $submitted=$data['items']??[$data];
        if(!is_array($submitted)||!count($submitted)||count($submitted)>self::MAX_ITEMS)throw new InvalidArgumentException(t('Add between 1 and {max} rice items.',['max'=>self::MAX_ITEMS]));
        $items=[];
        foreach(array_values($submitted) as $index=>$row){
            try {
                if(!is_array($row))throw new InvalidArgumentException(t('Invalid rice item.'));
                foreach(['rice_type_id','quantity','unit_price','weight_lb'] as $field){if(isset($row[$field])&&!is_scalar($row[$field]))throw new InvalidArgumentException(t('Invalid item input.'));}
                $rice=$row['rice_type_id']??'';
                if(filter_var($rice,FILTER_VALIDATE_INT)===false||(int)$rice<1)throw new InvalidArgumentException(t('Select a rice type.'));
                if(in_array((int)$rice,array_column($items,'rice_type_id'),true))throw new InvalidArgumentException(t('This rice type is already selected. Use one item per rice type.'));
                $items[]=['rice_type_id'=>(int)$rice,'quantity'=>decimal_value(trim((string)($row['quantity']??'')),3),'unit_price'=>decimal_value(trim((string)($row['unit_price']??'')),2),'weight_lb'=>isset($row['weight_lb'])&&trim((string)$row['weight_lb'])!==''?decimal_value(trim((string)$row['weight_lb']),3):null];
            } catch(InvalidArgumentException $error){throw new InvalidArgumentException(t('Item ').($index+1).': '.$error->getMessage());}
        }
        $warehouse=(int)$data['warehouse_id'];$supplier=(int)$data['supplier_id'];
        return DB::transaction(function()use($items,$date,$key,$notes,$warehouse,$supplier,$data,$user,$edit,$version):int{
            $before=[];$original=null;
            if($edit){$original=Operations::find('purchase',$edit,true);Operations::checkVersion($original,$version);$before=Operations::rows('purchase',$edit);Operations::lockSuppliers([$supplier,$original['supplier_id']]);}
            else {$existing=DB::fetch('SELECT id FROM purchases WHERE request_key=?',[$key]);if($existing)return (int)$existing['id'];}
            if(!DB::fetch('SELECT id FROM suppliers WHERE id=? FOR UPDATE',[$supplier]))throw new InvalidArgumentException(t('Supplier does not exist.'));
            if(!DB::fetch('SELECT id FROM warehouses WHERE id=?',[$warehouse]))throw new InvalidArgumentException(t('Warehouse does not exist.'));
            // Lock balances in rice ID order so concurrent purchases use a consistent order.
            $riceIds=array_unique(array_column($items,'rice_type_id'));sort($riceIds);
            foreach($riceIds as $rice){
                if(!DB::fetch('SELECT id FROM rice_types WHERE id=?',[$rice]))throw new InvalidArgumentException(t('A selected rice type no longer exists.'));
                DB::statement('INSERT INTO inventory (warehouse_id,rice_type_id,quantity) VALUES (?,?,0) ON DUPLICATE KEY UPDATE quantity=quantity',[$warehouse,$rice]);
                DB::fetch('SELECT quantity FROM inventory WHERE warehouse_id=? AND rice_type_id=? FOR UPDATE',[$warehouse,$rice]);
            }
            // Header amounts are totals; legacy rice/price columns identify the first item.
            $header=['reference'=>'PUR-'.date('Ymd').'-'.strtoupper(bin2hex(random_bytes(5))),'supplier_id'=>$supplier,'warehouse_id'=>$warehouse,'rice_type_id'=>$items[0]['rice_type_id'],'quantity'=>'0','weight_lb'=>null,'unit_price'=>$items[0]['unit_price'],'amount'=>'0','amount_paid'=>'0','purchased_on'=>$date,'notes'=>$notes,'user_id'=>$user,'request_key'=>$key];
            if($edit){
                unset($header['reference'],$header['user_id'],$header['request_key']);$header['version']=$version+1;DB::update('purchases',$edit,$header);$id=$edit;
                DB::statement('DELETE FROM purchase_items WHERE purchase_id=?',[$id]);DB::statement('DELETE FROM movements WHERE purchase_id=?',[$id]);
                $key=$original['request_key'];
            } else $id=DB::insert('purchases',$header);
            foreach($items as $index=>$item){
                $item['amount']=DB::fetchValue('SELECT ROUND(CAST(? AS DECIMAL(16,3))*CAST(? AS DECIMAL(16,2)),2)',[$item['quantity'],$item['unit_price']]);
                DB::insert('purchase_items',['purchase_id'=>$id]+$item);
                if(!$edit)DB::statement('UPDATE inventory SET quantity=quantity+? WHERE warehouse_id=? AND rice_type_id=?',[$item['quantity'],$warehouse,$item['rice_type_id']]);
                DB::insert('movements',['kind'=>'purchase','warehouse_id'=>$warehouse,'destination_id'=>null,'rice_type_id'=>$item['rice_type_id'],'quantity'=>$item['quantity'],'occurred_on'=>$date,'notes'=>$notes,'purchase_id'=>$id,'user_id'=>$user,'request_key'=>$index===0?$key:hash('sha256',$key.':item:'.$index)]);
            }
            $totals=DB::fetch('SELECT SUM(amount) amount,SUM(quantity) quantity,CASE WHEN COUNT(weight_lb)=COUNT(*) THEN SUM(weight_lb) ELSE NULL END weight_lb FROM purchase_items WHERE purchase_id=?',[$id]);
            if(!(bool)DB::fetchValue('SELECT CAST(? AS DECIMAL(24,2)) <= 999999999999999999.99',[$totals['amount']]))throw new InvalidArgumentException(t('Purchase total must not exceed 999,999,999,999,999,999.99 MMK.'));
            $paid=isset($data['amount_paid'])&&$data['amount_paid']!==''?decimal_value((string)$data['amount_paid'],2,true,18):$totals['amount'];
            if(!(bool)DB::fetchValue('SELECT CAST(? AS DECIMAL(20,2))<=CAST(? AS DECIMAL(20,2))',[$paid,$totals['amount']]))throw new InvalidArgumentException(t('Paid amount cannot exceed the purchase amount.'));
            DB::update('purchases',$id,$totals+['amount_paid'=>$paid]);
            if($edit){Operations::adjust($before,Operations::rows('purchase',$id));Operations::validateCredit([$supplier,$original['supplier_id']]);}
            return $id;
        });
    }
    public static function items(int $purchase): array
    {
        return DB::fetchAll('SELECT i.*,r.name rice FROM purchase_items i JOIN rice_types r ON r.id=i.rice_type_id WHERE i.purchase_id=? ORDER BY i.id',[$purchase]);
    }
}
