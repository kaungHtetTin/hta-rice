<?php
declare(strict_types=1);
namespace App\Services;
use Mini\Database as DB;
use InvalidArgumentException;

final class StockBatch
{
    public const MAX_ITEMS=50;
    public static function record(string $kind,array $data,int $user,int $edit=0,int $version=0):int
    {
        if(!in_array($kind,['transfer','production'],true))throw new InvalidArgumentException(t('Invalid stock operation.'));
        $date=valid_date((string)($data['occurred_on']??''));
        $warehouse=$data['warehouse_id']??'';
        if(filter_var($warehouse,FILTER_VALIDATE_INT)===false||(int)$warehouse<1)throw new InvalidArgumentException(t('Select a valid source warehouse.'));
        $destination=$kind==='transfer'?($data['destination_id']??''):null;
        if($kind==='transfer' && (filter_var($destination,FILTER_VALIDATE_INT)===false||(int)$destination<1))throw new InvalidArgumentException(t('Select a valid destination warehouse.'));
        if($destination!==null && (int)$warehouse===(int)$destination)throw new InvalidArgumentException(t('Choose two different warehouses.'));
        $key=$data['request_key']??'';$notes=$data['notes']??'';
        if(!is_string($key)||!preg_match('/^[a-f0-9]{64}$/D',$key))throw new InvalidArgumentException(t('Invalid form reference. Refresh and try again.'));
        if(!is_string($notes)||strlen($notes)>4000)throw new InvalidArgumentException(t('Notes must be less than 4,000 characters.'));
        $submitted=$data['items']??[$data];
        if(!is_array($submitted)||!count($submitted)||count($submitted)>self::MAX_ITEMS)throw new InvalidArgumentException(t('Add between 1 and {max} rice items.',['max'=>self::MAX_ITEMS]));
        $items=[];
        foreach(array_values($submitted) as $index=>$row){
            try{
                if(!is_array($row)||!is_scalar($row['rice_type_id']??null)||!is_scalar($row['quantity']??null))throw new InvalidArgumentException(t('Select a rice type and enter a quantity.'));
                $rice=$row['rice_type_id'];
                if(filter_var($rice,FILTER_VALIDATE_INT)===false||(int)$rice<1)throw new InvalidArgumentException(t('Select a rice type.'));
                if(in_array((int)$rice,array_column($items,'rice_type_id'),true))throw new InvalidArgumentException(t('This rice type is already selected.'));
                $items[]=['rice_type_id'=>(int)$rice,'quantity'=>decimal_value(trim((string)$row['quantity']),3)];
            }catch(InvalidArgumentException $error){throw new InvalidArgumentException(t('Item ').($index+1).': '.$error->getMessage());}
        }
        return DB::transaction(function()use($kind,$items,$warehouse,$destination,$date,$notes,$key,$user,$edit,$version):int{
            $before=[];$original=null;
            if($edit){$original=Operations::find($kind,$edit,true);Operations::checkVersion($original,$version);$before=Operations::rows($kind,$edit);$key=$original['request_key'];}
            $existing=$edit?null:DB::fetch('SELECT id,kind FROM movements WHERE request_key=?',[$key]);
            if($existing){if($existing['kind']!==$kind)throw new InvalidArgumentException(t('This form reference has already been used.'));return (int)$existing['id'];}
            $warehouses=array_unique(array_map('intval',array_filter([$warehouse,$destination])));sort($warehouses);
            $riceIds=array_column($items,'rice_type_id');sort($riceIds);
            foreach($riceIds as $rice)if(!DB::fetch('SELECT id FROM rice_types WHERE id=?',[$rice]))throw new InvalidArgumentException(t('A selected rice type no longer exists.'));
            foreach($warehouses as $location){
                if(!DB::fetch('SELECT id FROM warehouses WHERE id=?',[$location]))throw new InvalidArgumentException(t('Warehouse does not exist.'));
                foreach($riceIds as $rice){
                    DB::statement('INSERT INTO inventory (warehouse_id,rice_type_id,quantity) VALUES (?,?,0) ON DUPLICATE KEY UPDATE quantity=quantity',[$location,$rice]);
                    DB::fetch('SELECT quantity FROM inventory WHERE warehouse_id=? AND rice_type_id=? FOR UPDATE',[$location,$rice]);
                }
            }
            $first=0;
            if($edit)DB::statement('DELETE FROM movements WHERE operation_id=? AND id<>?',[$edit,$edit]);
            foreach($items as $index=>$item){
                if(!$edit){
                $updated=DB::statement('UPDATE inventory SET quantity=quantity-? WHERE warehouse_id=? AND rice_type_id=? AND quantity>=?',[$item['quantity'],(int)$warehouse,$item['rice_type_id'],$item['quantity']]);
                if($updated->rowCount()!==1)throw new InvalidArgumentException(t('Item ').($index+1).t(': Insufficient stock in the selected warehouse for this rice type.'));
                if($destination!==null){
                    $received=DB::statement('UPDATE inventory SET quantity=quantity+? WHERE warehouse_id=? AND rice_type_id=? AND quantity<=9999999999999.999-?',[$item['quantity'],(int)$destination,$item['rice_type_id'],$item['quantity']]);
                    if($received->rowCount()!==1)throw new InvalidArgumentException(t('Item ').($index+1).t(': Destination stock balance exceeds the supported limit.'));
                }
                }
                $row=['kind'=>$kind,'warehouse_id'=>(int)$warehouse,'destination_id'=>$destination!==null?(int)$destination:null,'rice_type_id'=>$item['rice_type_id'],'quantity'=>$item['quantity'],'occurred_on'=>$date,'notes'=>$notes,'purchase_id'=>null,'user_id'=>$user,'request_key'=>$index===0?$key:hash('sha256',$key.':'.$kind.'-item:'.$index)];
                if($edit && $index===0){unset($row['user_id'],$row['request_key']);$row['version']=$version+1;DB::update('movements',$edit,$row);$id=$edit;}
                else $id=DB::insert('movements',$row);
                if($index===0)$first=$id;
                DB::update('movements',$id,['operation_id'=>$first]);
            }
            if($edit)Operations::adjust($before,Operations::rows($kind,$first));
            return $first;
        });
    }
}
