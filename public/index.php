<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/app.php';

$router = new \Mini\Router();
require BASE_PATH . '/routes/web.php';
$router->dispatch();
