<?php
declare(strict_types=1);

$testDatabase = 'rice_test_' . bin2hex(random_bytes(6));
$_ENV['DB_DATABASE'] = $testDatabase;
require dirname(__DIR__) . '/bootstrap/app.php';

use Mini\Database as DB;
use App\Services\AdminSeeder;

function seedCheck(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
    echo 'PASS: ' . $message . "\n";
}

$pdo = DB::connection(true);
try {
    $pdo->exec("CREATE DATABASE `$testDatabase` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    DB::connection()->exec('CREATE TABLE migrations (id INT PRIMARY KEY) ENGINE=InnoDB');
    DB::statement('INSERT INTO migrations VALUES (1)');
    foreach(glob(BASE_PATH.'/migrations/*.php') as $file){$migration=require $file;foreach($migration['up'] as $sql) DB::connection()->exec($sql);}

    $_ENV['ADMIN_NAME'] = 'Test Admin';
    $_ENV['ADMIN_EMAIL'] = 'ADMIN@example.test';
    $_ENV['ADMIN_PASSWORD'] = '';
    try {
        AdminSeeder::seed();
        throw new RuntimeException('Empty admin password should be rejected.');
    } catch (InvalidArgumentException $error) {
        seedCheck((int) DB::fetchValue('SELECT COUNT(*) FROM users') === 0, 'Missing password creates no account');
    }

    DB::insert('users', ['name'=>'Staff', 'email'=>'admin@example.test', 'password'=>password_hash('staff-test-password', PASSWORD_DEFAULT), 'role'=>'staff', 'permissions'=>'[]']);
    $_ENV['ADMIN_PASSWORD'] = 'test-admin-password';
    try {
        AdminSeeder::seed();
        throw new RuntimeException('Staff email should not be promoted.');
    } catch (InvalidArgumentException $error) {
        seedCheck(DB::fetchValue('SELECT role FROM users LIMIT 1') === 'staff', 'Existing staff email is never promoted');
    }

    $_ENV['ADMIN_EMAIL'] = 'OWNER@example.test';
    seedCheck(AdminSeeder::seed(), 'Seed creates owner from environment');
    $owner = DB::fetch('SELECT * FROM users WHERE role = ?', ['owner']);
    seedCheck($owner['name'] === 'Test Admin' && $owner['email'] === 'owner@example.test' && (int)$owner['active'] === 1, 'Owner identity and active status match configuration');
    seedCheck($owner['password'] !== $_ENV['ADMIN_PASSWORD'] && password_verify($_ENV['ADMIN_PASSWORD'], $owner['password']), 'Admin password is stored as a valid hash');

    $_ENV['ADMIN_PASSWORD'] = 'another-test-password';
    seedCheck(!AdminSeeder::seed() && (int)DB::fetchValue("SELECT COUNT(*) FROM users WHERE role = 'owner'") === 1, 'Repeated seeding creates no duplicate owner');
    seedCheck(DB::fetchValue("SELECT password FROM users WHERE role = 'owner'") === $owner['password'], 'Repeated seeding preserves existing credentials');
} finally {
    if (preg_match('/^rice_test_[a-f0-9]{12}$/D', $testDatabase)) $pdo->exec("DROP DATABASE IF EXISTS `$testDatabase`");
}
