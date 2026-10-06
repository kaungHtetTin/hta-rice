<?php
declare(strict_types=1);
namespace App\Services;
final class Production
{
    public const MAX_ITEMS=StockBatch::MAX_ITEMS;
    public static function record(array $data,int $user):int
    {
        return StockBatch::record('production',$data,$user);
    }
}
