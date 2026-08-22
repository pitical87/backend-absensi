<?php

return [

    'host' => env('SIMRS_DB_HOST', '127.0.0.1'),

    'port' => env('SIMRS_DB_PORT', '3306'),

    'database' => env('SIMRS_DB_DATABASE', 'simrs'),

    'username' => env('SIMRS_DB_USERNAME', ''),

    'password' => env('SIMRS_DB_PASSWORD', ''),

    // Kunci enkripsi AES akun SIMRS (SIMRS Khanza)
    'kunci_user' => env('SIMRS_KUNCI_USER', 'nur'),

    'timeout' => (int) env('SIMRS_DB_TIMEOUT', 5),

];
