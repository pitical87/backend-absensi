<?php

namespace App\Console\Commands;

use App\Services\GoogleLoginService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class CekKonfigurasiGoogle extends Command
{
    protected $signature = 'google:cek';

    protected $description = 'Periksa konfigurasi login Google dan tampilkan redirect_uri yang harus didaftarkan di Google Cloud Console';

    public function handle(GoogleLoginService $google): int
    {
        $redirect = (string) config('services.google.redirect', '');
        $clientId = (string) config('services.google.client_id', '');

        $this->components->twoColumnDetail('Client ID', $clientId !== '' ? $clientId : '<fg=red>kosong</>');
        $this->components->twoColumnDetail(
            'Client secret',
            (string) config('services.google.client_secret', '') !== '' ? 'terisi' : '<fg=red>kosong</>'
        );
        $this->components->twoColumnDetail('Redirect URI', $redirect !== '' ? $redirect : '<fg=red>kosong</>');
        $this->components->twoColumnDetail('Client ID aplikasi (aud)', $this->daftarClientMobile());
        $this->components->twoColumnDetail('Batasi domain (hd)', config('services.google.hd') ?: '-');

        if ($redirect === '') {
            $this->components->error('Isi GOOGLE_REDIRECT_URI atau APP_URL, lalu jalankan ulang.');

            return self::FAILURE;
        }

        $parts = parse_url($redirect);
        $host = $parts['host'] ?? '';
        $port = $parts['port'] ?? null;
        $scheme = $parts['scheme'] ?? '';

        if (str_starts_with($host, 'localhost') || $host === '127.0.0.1') {
            $this->components->warn('Host lokal dipakai Google hanya untuk pengujian; tipe OAuth client "Web application" dengan localhost hanya menerima email akun uji Google.');

            if ($port === null) {
                $this->components->warn('Port tidak dicantumkan (default 80). Bila aplikasi dijalankan lewat "php artisan serve" (port 8000), tambahkan portnya agar sama persis dengan yang di-request browser.');
            }
        }

        if ($scheme !== 'https' && ! str_contains($host, 'localhost') && $host !== '127.0.0.1') {
            $this->components->error('Produksi wajib memakai https, jika tidak Google akan menolak redirect_uri.');
        }

        if (app()->configurationIsCached()) {
            $this->components->warn('Config sedang di-cache. Jalankan php artisan config:clear atau config:cache ulang setelah mengubah .env.');
        }

        $cacheAktif = Cache::get('auth.login.html.v3') !== null;
        if ($cacheAktif) {
            $this->components->info('HTML halaman login masih dalam cache (5 menit); jalankan php artisan cache:clear agar tombol langsung tampil.');
        }

        $this->newLine();
        $this->components->twoColumnDetail('Teks yang wajib disalin persis', $redirect);
        $this->components->bulletList([
            'Google Cloud Console > APIs & Services > Credentials > pilih OAuth client ID > Authorized redirect URIs',
            'Tambahkan nilai di atas tanpa trailing slash, lalu klik Save.',
            'Kesalahan redirect_uri_mismatch juga muncul bila memakai client ID yang berbeda dari yang dipakai untuk mendaftarkan URI.',
            'Setelah mengubah .env: php artisan config:clear',
        ]);

        return self::SUCCESS;
    }

    private function daftarClientMobile(): string
    {
        $ids = array_values(array_filter(array_map('strval', (array) config('services.google.mobile_client_ids', []))));

        if ($ids === []) {
            return '<fg=yellow>kosong - hanya client ID web yang diterima; '
                .'isi bila React memakai OAuth client sendiri</>';
        }

        $web = (string) config('services.google.client_id', '');

        if ($web !== '' && ! in_array($web, $ids, true)) {
            $ids[] = $web.' (web, bawaan)';
        }

        return implode(', ', $ids);
    }
}
