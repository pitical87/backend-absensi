<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'web_absen' => [
        'url' => env('WEB_ABSEN_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Google
    |--------------------------------------------------------------------------
    |
    | Login dengan Google. `client_id`/`secret` dipakai alur redirect browser
    | pada halaman login web (laravel/socialite), sedangkan `mobile_client_ids`
    | dipakai sebagai daftar `aud` yang sah saat aplikasi web terpisah
    | (React) mengirim id_token ke /api/mobile/login/google.
    |
    | `hd` opsional untuk membatasi hanya akun Google Workspace milik satu
    | domain, mis. "rsud-merauke.id" (tanpa titik). Kosongkan = semua akun
    | Google, asalkan email-nya sudah terdaftar di tabel users.
    |
    */

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        // Wajib diisi Socialite: harus sama persis (huruf besar-kecil, protocol,
        // domain, dan PORT) dengan Authorized redirect URI di Google Cloud
        // Console, termasuk tanpa garis miring di akhir.
        'redirect' => rtrim((string) (env('GOOGLE_REDIRECT_URI') ?: rtrim((string) env('APP_URL'), '/').'/auth/google/callback'), '/'),
        'mobile_client_ids' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('GOOGLE_MOBILE_CLIENT_IDS', ''))
        ))),
        'hd' => env('GOOGLE_HD') ?: null,
    ],

];
