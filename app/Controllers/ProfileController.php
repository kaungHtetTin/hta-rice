<?php
declare(strict_types=1);
namespace App\Controllers;
use Mini\Database as DB;
use InvalidArgumentException;
use PDOException;

final class ProfileController
{
    public function index(): void
    {
        view('profile',['title'=>'Profile settings','user'=>current_user()]);
    }
    public function save(): void
    {
        try {
            $user=current_user();$name=input('name');$email=strtolower(input('email'));
            if($name==='' || strlen($name)>120)throw new InvalidArgumentException(t('Enter a name of up to 120 characters.'));
            if(!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>190)throw new InvalidArgumentException(t('Enter a valid email address.'));
            if($email!==$user['email'])$this->confirmPassword($user);
            // Only update the signed-in account; posted IDs/roles/permissions are ignored.
            DB::update('users',(int)$user['id'],['name'=>$name,'email'=>$email]);
            redirect('profile',t('Your profile has been updated.'));
        }catch(InvalidArgumentException $error){$this->failed($error->getMessage(),true);}
        catch(PDOException $error){if(($error->errorInfo[1]??0)===1062)$this->failed(t('That email address is already in use.'),true);throw $error;}
    }
    public function password(): void
    {
        try {
            $user=current_user();$new=password_input('new_password');$confirmation=password_input('password_confirmation');
            if(strlen($new)<10 || strlen($new)>72 || trim($new)==='')throw new InvalidArgumentException(t('New password must contain 10–72 characters.'));
            if(!hash_equals($new,$confirmation))throw new InvalidArgumentException(t('New password and confirmation do not match.'));
            $this->confirmPassword($user);
            if(password_verify($new,$user['password']))throw new InvalidArgumentException(t('Choose a different password from your current password.'));
            $hash=password_hash($new,PASSWORD_DEFAULT);
            // Prevent concurrent changes from replacing a password after verification.
            $updated=DB::statement('UPDATE users SET password=? WHERE id=? AND password=? AND active=1',[$hash,$user['id'],$user['password']]);
            if($updated->rowCount()!==1)throw new InvalidArgumentException(t('Your account changed. Please sign in again before changing your password.'));
            session_regenerate_id(true);$_SESSION['auth_hash']=hash('sha256',$hash);unset($_SESSION['_token']);
            redirect('profile',t('Password changed. Other sessions will be signed out.'));
        }catch(InvalidArgumentException $error){$this->failed($error->getMessage());}
    }
    private function confirmPassword(array $user): void
    {
        $identity=hash('sha256','profile|'.$user['id'].'|'.($_SERVER['REMOTE_ADDR']??''));
        $attempt=DB::fetch('SELECT * FROM login_attempts WHERE identity=?',[$identity]);
        if($attempt && (int)$attempt['attempts']>=5 && strtotime($attempt['last_attempt'])>time()-900)throw new InvalidArgumentException(t('Too many incorrect password attempts. Please try again in 15 minutes.'));
        if(!password_verify(password_input('current_password'),$user['password'])){
            DB::statement('INSERT INTO login_attempts(identity,attempts,last_attempt) VALUES (?,1,NOW()) ON DUPLICATE KEY UPDATE attempts=IF(last_attempt < DATE_SUB(NOW(),INTERVAL 15 MINUTE),1,attempts+1),last_attempt=NOW()',[$identity]);
            throw new InvalidArgumentException(t('Your current password is incorrect.'));
        }
        DB::statement('DELETE FROM login_attempts WHERE identity=?',[$identity]);
    }
    private function failed(string $message,bool $profile=false): never
    {
        if($profile)$_SESSION['old']=array_intersect_key(array_filter($_POST,'is_scalar'),array_flip(['name','email']));
        redirect('profile',$message,'error');
    }
}
