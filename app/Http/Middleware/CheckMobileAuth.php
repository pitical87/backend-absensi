<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class CheckMobileAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->cookie('auth_token');
        if (! is_string($token) || $token === '') {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Token tidak ditemukan',
            ], 401);
        }

        // Proteksi CSRF untuk request mutasi yang diautentikasi lewat cookie
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            if (! $this->csrfValid($request)) {
                return response()->json([
                    'sukses' => false,
                    'pesan' => 'Permintaan ditolak demi keamanan (verifikasi asal/CSRF gagal). Muat ulang halaman lalu coba lagi.',
                ], 403);
            }
        }

        $apitoken = ApiToken::cariDenganToken($token);
        if (! $apitoken) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Token tidak valid atau kadaluarsa. Silahkan login kembali',
            ], 401);
        }
        if (now()->greaterThan($apitoken->expires_at)) {
            ApiToken::where('id', $apitoken->id)
                ->delete();

            return response()->json([
                'sukses' => false,
                'pesan' => 'Token tidak valid atau kadaluarsa. Silahkan login kembali',
            ], 401);
        }
        if (! $apitoken->last_aktivitas || $apitoken->last_aktivitas->lte(now()->subMinute())) {
            $ubah = ['last_aktivitas' => now()];
            if (! $apitoken->ip) {
                $ubah['ip'] = $request->ip();
            }
            if (! $apitoken->perangkat) {
                $ubah['perangkat'] = substr(trim((string) ($request->header('X-Device-Name', ''))), 0, 150);
            }
            if (empty($apitoken->user_agent)) {
                $ubah['user_agent'] = substr((string) $request->header('User-Agent', ''), 0, 255);
            }
            $apitoken->forceFill($ubah)->save();
        }
        $user = User::where('id', $apitoken->user_id)
            ->first();
        if (! $user || $user->status !== 'aktif') {
            ApiToken::where('id', $apitoken->id)
                ->delete();

            return response()->json([
                'sukses' => false,
                'pesan' => 'Akun tidak aktif',
            ], 401);
        }

        $request->attributes->set('user', $user);

        return $next($request);
    }

    private function csrfValid(Request $request): bool
    {
        if (strcasecmp((string) $request->header('X-Requested-With'), 'XMLHttpRequest') !== 0) {
            return false;
        }

        // Fetch Metadata header dikirim otomatis oleh browser modern & tidak bisa dipalsukan JS.
        // same-origin selalu sah (termasuk saat frontend memakai Nginx/Vite reverse proxy).
        $fetchSite = strtolower(trim((string) $request->header('Sec-Fetch-Site', '')));
        if ($fetchSite === 'same-origin') {
            return true;
        }
        if ($fetchSite === 'cross-site') {
            return false;
        }

        $sumber = (string) ($request->header('Origin') ?: $request->header('Referer') ?: '');
        if ($sumber === '') {
            return true;
        }

        $hostSumber = $this->ekstrakHost($sumber);
        if ($hostSumber === '') {
            return false;
        }

        $hostDiizinkan = array_filter([
            strtolower((string) $request->getHost()),
            $this->ekstrakHost((string) $request->header('X-Forwarded-Host', '')),
            $this->ekstrakHost((string) config('app.url', '')),
            $this->ekstrakHost((string) config('services.web_absen.url', '')),
        ]);

        foreach ((array) config('cors.allowed_origins', []) as $origin) {
            foreach (explode(',', (string) $origin) as $bagian) {
                $h = $this->ekstrakHost($bagian);
                if ($h !== '') {
                    $hostDiizinkan[] = $h;
                }
            }
        }

        return in_array($hostSumber, array_unique($hostDiizinkan), true);
    }

    private function ekstrakHost(string $nilai): string
    {
        $bersih = trim($nilai);
        if ($bersih === '') {
            return '';
        }

        // Tangani format dengan skema (https://a.id/x) maupun host polos (absen.rsud-merauke.id)
        if (! str_contains($bersih, '://')) {
            $bersih = 'https://'.$bersih;
        }

        return strtolower((string) (parse_url($bersih, PHP_URL_HOST) ?? ''));
    }
}