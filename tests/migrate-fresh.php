<?php
declare(strict_types=1);

$testDatabase = 'rice_fresh_test_' . bin2hex(random_bytes(6));
putenv('DB_DATABASE=' . $testDatabase);
putenv('ADMIN_NAME=Fresh test owner');
putenv('ADMIN_EMAIL=fresh@example.test');
putenv('ADMIN_PASSWORD=fresh-test-password');
require dirname(__DIR__) . '/bootstrap/app.php';

function freshCheck(bool $passed, string $message): void {
    if (!$passed) throw new RuntimeException('FAIL: ' . $message);
    echo 'PASS: ' . $message . "\n";
}

function freshConsole(array $arguments, string $input = ''): array {
    $process = proc_open([PHP_BINARY, BASE_PATH . '/bin/console', ...$arguments],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, BASE_PATH);
    if (!is_resource($process)) throw new RuntimeException('Cannot start console.');
    fwrite($pipes[0], $input);
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return [proc_close($process), $output];
}

$server = Mini\Database::connection(true);
try {
    [$code, $output] = freshConsole(['migrate']);
    freshCheck($code === 0, 'Initial migrations succeed: ' . ($code ? $output : ''));
    $db = Mini\Database::connection();
    $db->exec('CREATE TABLE fresh_parent (id INT PRIMARY KEY)');
    $db->exec('CREATE TABLE fresh_child (id INT PRIMARY KEY, parent_id INT, FOREIGN KEY (parent_id) REFERENCES fresh_parent(id))');
    $db->exec('INSERT INTO fresh_parent VALUES (1)');
    $db->exec('INSERT INTO fresh_child VALUES (1, 1)');
    [$code] = freshConsole(['migrate:fresh'], "no\n");
    freshCheck($code !== 0 && (int)$db->query('SELECT COUNT(*) FROM fresh_child')->fetchColumn() === 1, 'Declining confirmation preserves existing data');
    [$code, $output] = freshConsole(['migrate:fresh'], $testDatabase . "\n");
    freshCheck($code === 0, 'Confirmed fresh migrations succeed: ' . ($code ? $output : ''));
    freshCheck($db->query("SHOW TABLES LIKE 'fresh_child'")->fetchColumn() === false && $db->query("SHOW TABLES LIKE 'fresh_parent'")->fetchColumn() === false, 'Fresh removes extra tables with foreign keys');
    freshCheck((int)$db->query('SELECT COUNT(*) FROM migrations')->fetchColumn() === count(glob(BASE_PATH . '/migrations/*.php')), 'All migrations are reapplied');
    freshCheck((int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0, 'Fresh does not seed by default');
    [$code, $output] = freshConsole(['migrate:fresh', '--force', '--seed']);
    freshCheck($code === 0, 'Forced fresh with seed succeeds: ' . ($code ? $output : ''));
    freshCheck($db->query("SELECT email FROM users WHERE role='owner'")->fetchColumn() === 'fresh@example.test', 'Seed recreates the configured owner');
    [$code, $output] = freshConsole(['migrate']);
    freshCheck($code === 0 && str_contains($output, 'Database is up to date.'), 'Normal migrate still recognizes applied migrations');
} finally {
    if (preg_match('/^rice_fresh_test_[a-f0-9]{12}$/D', $testDatabase)) {
        $server->exec("DROP DATABASE IF EXISTS `$testDatabase`");
    }
}
