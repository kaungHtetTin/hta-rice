<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap/app.php';
use App\Services\Locale;
function localeCheck(bool $passed,string $message):void{if(!$passed)throw new RuntimeException($message);echo 'PASS: '.$message."\n";}
$en=Locale::catalog('en');$my=Locale::catalog('my');
localeCheck(array_keys($en)===array_keys($my),'English and Myanmar catalogs contain the same message keys');
foreach($en as $key=>$message) {
    preg_match_all('/\{([a-z_]+)\}/',$message,$english);preg_match_all('/\{([a-z_]+)\}/',$my[$key],$myanmar);sort($english[1]);sort($myanmar[1]);
    if($english[1]!==$myanmar[1])throw new RuntimeException('Placeholder mismatch: '.$key);
    if(!preg_match('/[\x{1000}-\x{109f}]/u',$my[$key]))throw new RuntimeException('Myanmar translation is missing: '.$key);
}
localeCheck(true,'Every Myanmar message is translated and preserves its interpolation placeholders');
$missing=[];
foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(BASE_PATH.'/app')) as $file) {
    if($file->getExtension()!=='php')continue;
    $tokens=array_values(array_filter(token_get_all(file_get_contents($file->getPathname())),fn($token)=>!is_array($token)||!in_array($token[0],[T_WHITESPACE,T_COMMENT,T_DOC_COMMENT],true)));
    foreach($tokens as $index=>$token) {
        if(!is_array($token)||$token[0]!==T_STRING||$token[1]!=='t'||($tokens[$index+1]??null)!=='(')continue;
        $literal=$tokens[$index+2]??null;
        if(is_array($literal)&&$literal[0]===T_CONSTANT_ENCAPSED_STRING){$key=eval('return '.$literal[1].';');if(!isset($en[$key]))$missing[]=$file->getFilename().': '.$key;}
    }
}
foreach(['app','purchase-wizard','locale'] as $script) {
    $source=file_get_contents(BASE_PATH.'/public/assets/js/'.$script.'.js');
    preg_match_all('/\bt\(\s*\x27([^\x27]*)\x27/u',$source,$matches);
    foreach($matches[1] as $key)if(!isset($en[$key]))$missing[]=$script.'.js: '.$key;
}
localeCheck(!$missing,'Every literal PHP and JavaScript translation call has a catalog entry'.($missing?': '.implode(', ',$missing):''));
$_SESSION['locale']='my';localeCheck(t('Page {number}',['number'=>3])==='စာမျက်နှာ 3','Myanmar interpolation works');
$_SESSION['locale']='en';localeCheck(t('Quantity (တင်း)')==='Quantity (tin)' && t('Weight (ပေါင်)')==='Weight (lb)','English unit labels are localized');
$_SESSION['locale']='unsupported';localeCheck(locale()==='en','Unsupported stored locale uses a safe default');
unset($_SESSION['locale']);
echo "All catalog checks passed.\n";
