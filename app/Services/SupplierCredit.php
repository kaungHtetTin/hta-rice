<?php
declare(strict_types=1);
namespace App\Services;
use Mini\Database as DB;
use InvalidArgumentException;

final class SupplierCredit
{
    public static function summaries(): array
    {
        return DB::fetchAll(self::summarySql().' ORDER BY s.name');
    }

    public static function summarySql(): string
    {
        return 'SELECT s.*, COALESCE(p.total,0) purchase_total, COALESCE(p.paid,0) purchase_paid, COALESCE(t.paid,0) payments_total, COALESCE(p.total,0)-COALESCE(p.paid,0)-COALESCE(t.paid,0) balance FROM suppliers s LEFT JOIN (SELECT supplier_id,SUM(amount) total,SUM(amount_paid) paid FROM purchases GROUP BY supplier_id) p ON p.supplier_id=s.id LEFT JOIN (SELECT supplier_id,SUM(amount) paid FROM supplier_payments GROUP BY supplier_id) t ON t.supplier_id=s.id';
    }

    public static function balance(int $supplier): string
    {
        return (string) DB::fetchValue('SELECT COALESCE((SELECT SUM(amount-amount_paid) FROM purchases WHERE supplier_id=?),0)-COALESCE((SELECT SUM(amount) FROM supplier_payments WHERE supplier_id=?),0)', [$supplier,$supplier]);
    }

    public static function pay(int $supplier, string $amount, string $date, string $notes, string $key, int $user): int
    {
        $amount = decimal_value($amount,2,false,18);
        $date = valid_date($date);
        if(strlen($notes)>4000) throw new InvalidArgumentException(t('Notes must be less than 4,000 characters.'));
        if(!preg_match('/^[a-f0-9]{64}$/D',$key)) throw new InvalidArgumentException(t('Invalid form reference. Refresh and try again.'));
        return DB::transaction(function() use($supplier,$amount,$date,$notes,$key,$user): int {
            if(!DB::fetch('SELECT id FROM suppliers WHERE id=? FOR UPDATE',[$supplier])) throw new InvalidArgumentException(t('Supplier does not exist.'));
            $existing=DB::fetch('SELECT id,supplier_id FROM supplier_payments WHERE request_key=?',[$key]);
            if($existing) {
                if((int)$existing['supplier_id']!==$supplier) throw new InvalidArgumentException(t('This payment reference belongs to another supplier.'));
                return (int)$existing['id'];
            }
            $balance=self::balance($supplier);
            if(!(bool)DB::fetchValue('SELECT CAST(? AS DECIMAL(20,2)) <= CAST(? AS DECIMAL(20,2))',[$amount,$balance])) throw new InvalidArgumentException(t('Payment exceeds the supplier outstanding balance.'));
            return DB::insert('supplier_payments',['supplier_id'=>$supplier,'amount'=>$amount,'paid_on'=>$date,'notes'=>$notes,'user_id'=>$user,'request_key'=>$key]);
        });
    }
}
