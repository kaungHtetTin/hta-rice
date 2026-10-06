<?php
namespace App\Controllers;
use Mini\Database as DB;
final class AuthController {
    public function login(): void { if (current_user()) redirect(''); view('auth',['title'=>'Sign in','setup'=>false],null); }
    public function setup(): void { if ((int)DB::fetchValue('SELECT COUNT(*) FROM users') > 0) redirect('login'); view('auth',['title'=>'Create owner account','setup'=>true],null); }
    public function createOwner(): void {
        try {
            $name = input('name'); $email = strtolower(input('email')); $password = password_input('password');
            if (!$name || strlen($name)>120 || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>190 || strlen($password)<10 || strlen($password)>72) throw new \InvalidArgumentException(t('Enter a name, valid email and a password of 10–72 characters.'));
            DB::transaction(function() use($name,$email,$password) {
                // Serialize first-owner setup even when two requests arrive together.
                DB::fetch('SELECT id FROM migrations ORDER BY id LIMIT 1 FOR UPDATE');
                if ((int)DB::fetchValue('SELECT COUNT(*) FROM users')) throw new \InvalidArgumentException(t('The owner account already exists. Please sign in.'));
                $hash=password_hash($password,PASSWORD_DEFAULT);
                $_SESSION['user_id'] = DB::insert('users',['name'=>$name,'email'=>$email,'password'=>$hash,'role'=>'owner','permissions'=>'[]']);
                $_SESSION['auth_hash']=hash('sha256',$hash);
            });
            session_regenerate_id(true); unset($_SESSION['_token']); redirect('',t('Your owner account is ready. Add your warehouses, rice types and suppliers to get started.'));
        } catch (\InvalidArgumentException $e) { redirect('setup',$e->getMessage(),'error'); }
    }
    public function authenticate(): void {
        $email = strtolower(input('email')); $password = password_input('password');
        $identity = hash('sha256',($_SERVER['REMOTE_ADDR'] ?? '') . '|' . $email);
        $attempt = DB::fetch('SELECT * FROM login_attempts WHERE identity=?',[$identity]);
        if ($attempt && (int)$attempt['attempts']>=5 && strtotime($attempt['last_attempt'])>time()-900) redirect('login',t('Too many attempts. Please try again in 15 minutes.'),'error');
        $u = DB::fetch('SELECT * FROM users WHERE email=? AND active=1',[$email]);
        if (!$u || !password_verify($password,$u['password'])) {
            DB::statement('INSERT INTO login_attempts(identity,attempts,last_attempt) VALUES (?,1,NOW()) ON DUPLICATE KEY UPDATE attempts=IF(last_attempt < DATE_SUB(NOW(),INTERVAL 15 MINUTE),1,attempts+1),last_attempt=NOW()',[$identity]);
            redirect('login',t('Email or password is incorrect.'),'error');
        }
        DB::statement('DELETE FROM login_attempts WHERE identity=?',[$identity]);
        session_regenerate_id(true); $_SESSION['user_id']=$u['id']; $_SESSION['auth_hash']=hash('sha256',$u['password']); unset($_SESSION['_token']); redirect('');
    }
    public function logout(): void { $language=locale(); $_SESSION=['locale'=>$language]; session_regenerate_id(true); redirect('login'); }
}
