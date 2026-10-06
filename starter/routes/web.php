<?php

use App\Controllers\HomeController;

$router->get('/', [HomeController::class, 'index']);
$router->post('/welcome', [HomeController::class, 'welcome']);
