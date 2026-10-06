<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Services\VoucherSettings;
use InvalidArgumentException;

final class VoucherSettingsController
{
    public function index(): void
    {
        view('voucher-settings',['title'=>'Voucher settings','settings'=>VoucherSettings::get(),'papers'=>VoucherSettings::PAPERS]);
    }
    public function save(): void
    {
        try { VoucherSettings::save($_POST,$_FILES['company_logo']??null);redirect('voucher-settings',t('Company and voucher settings saved.')); }
        catch(InvalidArgumentException $error){
            $_SESSION['old']=array_filter($_POST,'is_scalar');unset($_SESSION['old']['_token']);
            foreach(VoucherSettings::FLAGS as $flag)$_SESSION['old'][$flag]=isset($_POST[$flag])&&$_POST[$flag]==='1';
            redirect('voucher-settings',$error->getMessage(),'error');
        }
    }
    public function preview(): void
    {
        $p=['reference'=>'PREVIEW-ONLY','purchased_on'=>date('Y-m-d'),'supplier'=>t('Sample supplier'),'phone'=>'09 123 456 789','address'=>t('Sample supplier address'),'warehouse'=>t('Sample warehouse'),'location'=>t('Sample location'),'rice'=>'ပေါ်ဆန်း','quantity'=>'100.125','weight_lb'=>'5000.125','unit_price'=>'25000.25','amount'=>'2503150.03','amount_paid'=>'1000000.00','notes'=>t('Sample purchase notes. This preview does not create any business records.'),'recorded_by'=>current_user()['name'],'created_at'=>date('Y-m-d H:i:s')];
        view('voucher',['title'=>'Voucher preview','p'=>$p,'preview'=>true]);
    }
    public function logo(): void
    {
        $file=VoucherSettings::get()['logo_file']??'';
        $path=VoucherSettings::logoPath($file);
        if(!$path || !is_file($path)){http_response_code(404);return;}
        $mime=['png'=>'image/png','jpg'=>'image/jpeg','webp'=>'image/webp'][pathinfo($path,PATHINFO_EXTENSION)];
        header('Content-Type: '.$mime);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-cache');
        header('Content-Length: '.filesize($path));
        readfile($path);
    }
}
