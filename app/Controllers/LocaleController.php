<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Services\Locale;

final class LocaleController
{
    public function change(): void
    {
        $locale=is_string($_POST['locale']??null)?trim($_POST['locale']):'';
        if(!isset(Locale::SUPPORTED[$locale])){http_response_code(422);echo e(t('Choose a supported language.'));return;}
        $_SESSION['locale']=$locale;
        setcookie('rice_locale',$locale,['expires'=>time()+31536000,'path'=>'/','secure'=>!empty($_SERVER['HTTPS'])&&strtolower((string)$_SERVER['HTTPS'])!=='off','httponly'=>true,'samesite'=>'Lax']);
        // Return only to a path inside this application, never to a submitted origin.
        $return=is_string($_POST['return']??null)?trim($_POST['return']):'';$base=rtrim(parse_url(url(),PHP_URL_PATH)??'','/');
        $path=rawurldecode(parse_url($return,PHP_URL_PATH)??'');
        if(preg_match('/[\x00-\x1f\\\\]/',$return.$path)||!str_starts_with($return,'/')||str_starts_with($return,'//')||str_starts_with($path,'//')||preg_match('#(?:^|/)\.{1,2}(?:/|$)#',$path)||($base!==''&&$path!==$base&&!str_starts_with($path,$base.'/')))$return=url(current_user()?'':'login');
        header('Location: '.$return);exit;
    }
}
