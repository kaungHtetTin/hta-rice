<?php
declare(strict_types=1);
namespace App\Services;
use Mini\Database as DB;
use InvalidArgumentException;

final class Operations
{
    public static function find(string $kind,int $id,bool $lock=false): ?array
    {
        if($kind==='purchase') return DB::fetch('SELECT * FROM purchases WHERE id=?'.($lock?' FOR UPDATE':''),[$id]);
        return DB::fetch('SELECT * FROM movements WHERE id=? AND operation_id=id AND kind=?'.($lock?' FOR UPDATE':''),[$id,$kind]);
    }
    public static function rows(string $kind,int $id): array
    {
        return $kind==='purchase'?DB::fetchAll('SELECT * FROM movements WHERE purchase_id=? ORDER BY id',[$id]):DB::fetchAll('SELECT * FROM movements WHERE operation_id=? ORDER BY id',[$id]);
    }
    public static function checkVersion(?array $record,int $version): void
    {
        if(!$record) throw new InvalidArgumentException(t('This record no longer exists.'));
        if((int)$record['version']!==$version) throw new InvalidArgumentException(t('This record changed after you opened it. Reload the form before making changes.'));
    }
    /** Apply the net stock difference, allowing edits even after some rice was consumed. */
    public static function adjust(array $before,array $after): void
    {
        $changes=[];
        foreach([[$before,-1],[$after,1]] as [$rows,$direction]) foreach($rows as $row) {
            $effect=$row['kind']==='purchase'?1:-1;
            foreach([[(int)$row['warehouse_id'],$effect],...($row['kind']==='transfer'?[[(int)$row['destination_id'],1]]:[])] as [$warehouse,$sign]) {
                $key=$warehouse.':'.$row['rice_type_id'];
                $changes[$key]??=['warehouse'=>$warehouse,'rice'=>(int)$row['rice_type_id'],'delta'=>'0'];
                $changes[$key]['delta']=(string)DB::fetchValue('SELECT CAST(? AS DECIMAL(24,3)) + CAST(? AS DECIMAL(24,3))*?',[$changes[$key]['delta'],$row['quantity'],$direction*$sign]);
            }
        }
        uasort($changes,fn($a,$b)=>[$a['warehouse'],$a['rice']]<=>[$b['warehouse'],$b['rice']]);
        foreach($changes as $change) {
            ['warehouse'=>$warehouse,'rice'=>$rice,'delta'=>$delta]=$change;
            DB::statement('INSERT INTO inventory (warehouse_id,rice_type_id,quantity) VALUES (?,?,0) ON DUPLICATE KEY UPDATE quantity=quantity',[$warehouse,$rice]);
            $quantity=DB::fetchValue('SELECT quantity FROM inventory WHERE warehouse_id=? AND rice_type_id=? FOR UPDATE',[$warehouse,$rice]);
            $valid=DB::fetchValue('SELECT CAST(? AS DECIMAL(24,3))+CAST(? AS DECIMAL(24,3)) BETWEEN 0 AND 9999999999999.999',[$quantity,$delta]);
            if(!$valid) throw new InvalidArgumentException(t('This change would leave insufficient stock or exceed the stock limit. Check the affected warehouses first.'));
            DB::statement('UPDATE inventory SET quantity=quantity+? WHERE warehouse_id=? AND rice_type_id=?',[$delta,$warehouse,$rice]);
        }
    }
    public static function lockSuppliers(array $ids): void
    {
        $ids=array_unique(array_map('intval',$ids));sort($ids);
        foreach($ids as $id) if(!DB::fetch('SELECT id FROM suppliers WHERE id=? FOR UPDATE',[$id]))throw new InvalidArgumentException(t('Supplier does not exist.'));
    }
    public static function validateCredit(array $ids): void
    {
        foreach(array_unique($ids) as $id) if(DB::fetchValue('SELECT CAST(? AS DECIMAL(24,2))<0',[SupplierCredit::balance((int)$id)])) throw new InvalidArgumentException(t('This change would reduce supplier credit below payments already recorded. Review the supplier payments first.'));
    }
    public static function delete(string $kind,int $id,int $version): void
    {
        DB::transaction(function()use($kind,$id,$version):void {
            $record=self::find($kind,$id,true);self::checkVersion($record,$version);
            if($kind==='purchase')self::lockSuppliers([$record['supplier_id']]);
            self::adjust(self::rows($kind,$id),[]);
            if($kind==='purchase') {
                DB::statement('DELETE FROM movements WHERE purchase_id=?',[$id]);
                DB::statement('DELETE FROM purchase_items WHERE purchase_id=?',[$id]);
                DB::statement('DELETE FROM purchases WHERE id=?',[$id]);
                self::validateCredit([$record['supplier_id']]);
            } else DB::statement('DELETE FROM movements WHERE operation_id=?',[$id]);
        });
    }
}
