<?php
namespace App\Services;
use InvalidArgumentException;
final class Stock
{
    public static function record(string $kind,array $data,int $user):int
    {
        if($kind==='purchase')return Purchase::record($data,$user);
        if(in_array($kind,['transfer','production'],true))return StockBatch::record($kind,$data,$user);
        throw new InvalidArgumentException(t('Invalid operation.'));
    }
}
