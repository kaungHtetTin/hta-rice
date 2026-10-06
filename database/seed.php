<?php

use App\Services\AdminSeeder;

echo AdminSeeder::seed()
    ? "Owner / super admin account created from ADMIN_NAME, ADMIN_EMAIL and ADMIN_PASSWORD in .env.\n"
    : "An owner account already exists. Its credentials were left unchanged.\n";
