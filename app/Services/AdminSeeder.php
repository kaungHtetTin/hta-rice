<?php
declare(strict_types=1);

namespace App\Services;

use Mini\Database as DB;
use InvalidArgumentException;

final class AdminSeeder
{
    public static function seed(): bool
    {
        return DB::transaction(function (): bool {
            // Use the same lock as browser setup to prevent competing owner creation.
            DB::fetch('SELECT id FROM migrations ORDER BY id LIMIT 1 FOR UPDATE');
            if (DB::fetch('SELECT id FROM users WHERE role = ? LIMIT 1', ['owner'])) {
                return false;
            }

            $name = trim((string) env('ADMIN_NAME', t('Super Admin')));
            $email = strtolower(trim((string) env('ADMIN_EMAIL', '')));
            $password = (string) env('ADMIN_PASSWORD', '');

            if ($name === '' || strlen($name) > 120) {
                throw new InvalidArgumentException(t('Set ADMIN_NAME to a name of up to 120 characters.'));
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
                throw new InvalidArgumentException(t('Set ADMIN_EMAIL to a valid email address in .env.'));
            }
            if (strlen($password) < 10 || strlen($password) > 72) {
                throw new InvalidArgumentException(t('Set ADMIN_PASSWORD to a password of 10–72 characters in .env.'));
            }
            if (DB::fetch('SELECT id FROM users WHERE email = ?', [$email])) {
                throw new InvalidArgumentException(t('ADMIN_EMAIL is already used by a staff account. Choose a different email.'));
            }

            DB::insert('users', [
                'name' => $name,
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'role' => 'owner',
                'permissions' => '[]',
                'active' => 1,
            ]);

            return true;
        });
    }
}
