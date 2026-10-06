<?php
declare(strict_types=1);
namespace App\Services;

final class Locale
{
    public const SUPPORTED=['en'=>'English','my'=>'မြန်မာ'];
    public static function current(): string
    {
        $locale=$_SESSION['locale']??$_COOKIE['rice_locale']??env('APP_LOCALE','en');
        return is_string($locale)&&isset(self::SUPPORTED[$locale])?$locale:'en';
    }
    public static function catalog(string $locale): array
    {
        static $catalogs=[];
        if(!isset(self::SUPPORTED[$locale]))$locale='en';
        return $catalogs[$locale]??=json_decode(file_get_contents(BASE_PATH.'/lang/'.$locale.'.json'),true,512,JSON_THROW_ON_ERROR);
    }
    public static function translate(string $message,array $parameters=[]): string
    {
        $translated=self::catalog(self::current())[$message]??$message;
        foreach($parameters as $key=>$value)$translated=str_replace('{'.$key.'}',(string)$value,$translated);
        return $translated;
    }
}
