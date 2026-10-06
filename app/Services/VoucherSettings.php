<?php
declare(strict_types=1);
namespace App\Services;
use Mini\Database as DB;
use InvalidArgumentException;

final class VoucherSettings
{
    public const PAPERS = ['A4'=>'A4 (210 × 297 mm)','A5'=>'A5 (148 × 210 mm)','88mm'=>'88 mm receipt','80mm'=>'80 mm receipt','58mm'=>'58 mm receipt'];
    public const TEXT_FIELDS = ['business_name'=>120,'voucher_title'=>120,'header_text'=>2000,'address'=>1000,'phone'=>80,'email'=>190,'website'=>255,'registration_no'=>120,'footer_text'=>2000];
    public const FLAGS = ['show_weight','show_signatures','show_recorded_by'];

    public static function defaults(): array
    {
        return ['business_name'=>config('app.name'),'voucher_title'=>t('Purchase voucher'),'header_text'=>t('RICE PURCHASE RECORD'),'address'=>'','phone'=>'','email'=>'','website'=>'','registration_no'=>'','footer_text'=>'','paper_size'=>'A4','show_weight'=>true,'show_signatures'=>true,'show_recorded_by'=>true];
    }
    public static function get(): array
    {
        $row=DB::fetch('SELECT settings FROM voucher_settings WHERE id=1');
        return array_replace(self::defaults(),$row?(json_decode($row['settings'],true)?:[]):[]);
    }
    public static function logoUrl(array $settings): ?string
    {
        return !empty($settings['logo_file']) ? url('company-logo').'?v='.rawurlencode($settings['logo_file']) : null;
    }
    public static function logoPath(string $file): ?string
    {
        return preg_match('/\A[a-f0-9]{32}\.(png|jpg|webp)\z/D',$file) ? BASE_PATH.'/storage/branding/'.$file : null;
    }
    public static function save(array $data, ?array $logo = null): void
    {
        $settings=self::defaults();
        foreach(self::TEXT_FIELDS as $field=>$limit){
            $value=$data[$field]??'';
            if(!is_string($value)) throw new InvalidArgumentException(t('Invalid voucher setting.'));
            $value=trim($value);
            if(strlen($value)>$limit) throw new InvalidArgumentException(t('{field} must be no more than {limit} characters.',['field'=>t(ucwords(str_replace('_',' ',$field))),'limit'=>$limit]));
            if(in_array($field,['business_name','voucher_title'],true) && $value==='') throw new InvalidArgumentException(t('Business name and voucher title are required.'));
            $settings[$field]=$value;
        }
        $paper=$data['paper_size']??'';
        if(!is_string($paper)||!isset(self::PAPERS[$paper]))throw new InvalidArgumentException(t('Choose A4, A5, 88 mm, 80 mm or 58 mm paper.'));
        $settings['paper_size']=$paper;
        foreach(self::FLAGS as $flag)$settings[$flag]=isset($data[$flag])&&$data[$flag]==='1';
        $previous=self::get()['logo_file']??'';
        $settings['logo_file']=($data['remove_logo']??'')==='1'?'':$previous;
        $uploaded=null;
        if($logo !== null && ($logo['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){
            if(($logo['error']??null)!==UPLOAD_ERR_OK)throw new InvalidArgumentException(t('Logo upload failed. Choose an image smaller than 2 MB.'));
            if(($logo['size']??0)>2*1024*1024 || ($logo['size']??0)<1)throw new InvalidArgumentException(t('Logo must be smaller than 2 MB.'));
            $temporary=$logo['tmp_name']??'';
            if(!is_string($temporary)||!is_uploaded_file($temporary))throw new InvalidArgumentException(t('Invalid logo upload.'));
            $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($temporary);
            $extensions=['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'];
            $dimensions=@getimagesize($temporary);
            if(!isset($extensions[$mime]) || !$dimensions || ($dimensions['mime']??'')!==$mime || $dimensions[0]>4096 || $dimensions[1]>4096)throw new InvalidArgumentException(t('Choose a PNG, JPG or WebP logo, up to 4096 × 4096 pixels.'));
            $directory=BASE_PATH.'/storage/branding';
            if(!is_dir($directory)&&!mkdir($directory,0755,true)&&!is_dir($directory))throw new \RuntimeException(t('Cannot create logo storage.'));
            $settings['logo_file']=bin2hex(random_bytes(16)).'.'.$extensions[$mime];
            $uploaded=self::logoPath($settings['logo_file']);
            if(!move_uploaded_file($temporary,$uploaded))throw new \RuntimeException(t('Cannot store logo.'));
        }
        try {
            DB::statement('INSERT INTO voucher_settings(id,settings) VALUES (1,?) ON DUPLICATE KEY UPDATE settings=VALUES(settings)',[json_encode($settings,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
        } catch(\Throwable $error) {
            if($uploaded && is_file($uploaded))unlink($uploaded);
            throw $error;
        }
        if($previous!==$settings['logo_file'] && ($path=self::logoPath($previous)) && is_file($path))unlink($path);
    }
}
