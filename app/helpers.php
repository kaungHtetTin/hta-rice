<?php
use Mini\Database as DB;
function current_user(): ?array {
    static $loaded = false, $user = null;
    if (!$loaded) {
        $loaded = true; $user = isset($_SESSION['user_id']) ? DB::fetch('SELECT * FROM users WHERE id = ? AND active = 1', [$_SESSION['user_id']]) : null;
        if($user){
            $hash=hash('sha256',$user['password']);
            if(!isset($_SESSION['auth_hash']) || !hash_equals($_SESSION['auth_hash'],$hash)){$_SESSION=[];$user=null;}
        }
    }
    return $user;
}
function permissions(): array { return ['inventory.view'=>t('View inventory and dashboard'),'purchases.view'=>t('View purchases and print vouchers'),'purchases.create'=>t('Record purchases'),'transfers.create'=>t('Transfer stock'),'production.create'=>t('Submit production'),'reports.view'=>t('View purchase amount reports'),'settings.manage'=>t('Manage warehouses, rice types and suppliers'),'suppliers.credit'=>t('View supplier credit and record payments'),'purchases.edit'=>t('Edit purchases'),'purchases.delete'=>t('Delete purchases'),'transfers.edit'=>t('Edit transfers'),'transfers.delete'=>t('Delete transfers'),'production.edit'=>t('Edit production'),'production.delete'=>t('Delete production'),'users.manage'=>t('Manage staff accounts and permissions')]; }
function can(string $permission): bool { $u = current_user(); return $u && ($u['role'] === 'owner' || ($permission !== 'users.manage' && in_array($permission, json_decode($u['permissions'], true) ?: [], true))); }
function num(mixed $value, int $decimals = 3): string { $formatted = number_format((float)$value, $decimals); return $decimals > 0 ? rtrim(rtrim($formatted, '0'), '.') : $formatted; }
function money(mixed $value): string { return number_format((float)$value, 2); }
function input(string $key, mixed $default = ''): string { $v = $_POST[$key] ?? $default; if (!is_scalar($v)) throw new InvalidArgumentException(t('Invalid input for {field}.',['field'=>t(ucfirst(str_replace('_',' ',$key)))])); return trim((string)$v); }
function password_input(string $key): string { $value=$_POST[$key]??'';if(!is_string($value))throw new InvalidArgumentException(t('Invalid password input.'));return $value; }
function valid_date(string $value): string { $d = DateTimeImmutable::createFromFormat('!Y-m-d', $value); if (!$d || $d->format('Y-m-d') !== $value) throw new InvalidArgumentException(t('Please enter a valid date.')); return $value; }
function decimal_value(string $value, int $places, bool $zero = false, int $digits = 9): string {
    if (!preg_match('/^\d{1,' . $digits . '}(?:\.\d{1,' . $places . '})?$/D', $value) || (!$zero && (float)$value <= 0)) throw new InvalidArgumentException(t($zero?'Enter a non-negative number with up to {places} decimal places.':'Enter a positive number with up to {places} decimal places.',['places'=>$places]));
    return $value;
}
function field(string $name, string $label, string $type = 'text', string $default = '', string $extra = ''): void {
    echo '<label>' . e(t($label)) . '<input name="' . e($name) . '" type="' . e($type) . '" value="' . e($type === 'password' ? '' : old($name, $default)) . '" ' . $extra . '></label>';
}
function select_field(string $name, string $label, array $rows, mixed $selected = ''): void {
    echo '<label>' . e(t($label)) . '<select name="' . e($name) . '" '.(in_array($name,['supplier_id','rice_type_id'],true)?'data-searchable="true" ':'').'required><option value="">' . e(t('Select {label}',['label'=>t($label)])) . '</option>';
    foreach ($rows as $r) echo '<option value="' . e($r['id']) . '" ' . ((string)old($name,$selected)===(string)$r['id']?'selected':'') . '>' . e($r['name']) . '</option>';
    echo '</select></label>';
}

function locale(): string { return \App\Services\Locale::current(); }
function t(string $message,array $parameters=[]): string { return \App\Services\Locale::translate($message,$parameters); }
function locale_scripts(): string {
    return '<script id="locale-data" type="application/json">'.json_encode(['locale'=>locale(),'messages'=>\App\Services\Locale::catalog(locale())],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE).'</script><script src="'.e(asset('js/locale.js')).'"></script>';
}

function locale_date(string $format,?int $timestamp=null): string {
    $value=date($format,$timestamp??time());
    return preg_replace_callback('/[A-Za-z]+/',fn($match)=>t($match[0]),$value);
}
