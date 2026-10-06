<?php

namespace App\Controllers;

use Mini\Controller;

final class HomeController extends Controller
{
    public function index(): void
    {
        view('home', ['title' => config('app.name')]);
    }

    public function welcome(): void
    {
        $data = $this->validate(['name' => 'required']);
        view('home', ['title' => config('app.name'), 'name' => $data['name']]);
    }
}
