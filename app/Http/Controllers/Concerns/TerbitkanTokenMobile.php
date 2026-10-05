<?php

namespace App\Http\Controllers\Concerns;

use App\Models\ApiToken;
use App\Models\User;
use App\Services\AtasanLangsungService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * Penerbitan token api beserta cookie auth_token untuk aplikasi web/mobile.
 * Dipakai oleh login email-password maupun login dengan Google agar hanya
 * ada satu implementasi masa berlaku token dan bentuk responsnya.
 */
trait TerbitkanTokenMobile
{
    private const MASA_TOKEN_HARI = 7;

    private function terbitkanTokenMobile(
        Request $req,
        User $user,
        string $ip,
        string $detail
    ): JsonResponse {
        $token = bin2hex(random_bytes(32));

        ApiToken::create([
            'user_id' => $user->id,
            'token' => $token,
            'expires_at' => Carbon::now()->addDays(self::MASA_TOKEN_HARI),
            'perangkat' => substr(trim((string) ($req->input('perangkat') ?: $req->header('X-Device-Name', ''))), 0, 150),
            'ip' => $ip,
            'user_agent' => substr((string) $req->header('User-Agent', ''), 0, 255),
            'last_aktivitas' => now(),
        ]);

        $user->load(['unitKerja', 'subUnit', 'profesi', 'jabatan']);
        $user->append('shift');
        catat_aktivitas('Login Mobile', $detail);

        $menit = self::MASA_TOKEN_HARI * 24 * 60;
        $cookie = Cookie::make('auth_token', $token, $menit, '/', null, false, true, false, 'Lax');

        return response()->json([
            'sukses' => true,
            'user' => $user,
            'lokasi' => [
                'lat' => (float) pengaturan('lokasi_lat', -8.4991120),
                'lng' => (float) pengaturan('lokasi_lng', 140.4049840),
                'radius' => (float) pengaturan('radius_meter', 100),
            ],
            ...$this->hakAksesAtasan($user),
        ])->withCookie($cookie);
    }

    /**
     * Hak akses berbasis relasi atasan langsung beserta daftar bawahan.
     *
     * Dipakai bersama oleh login email-password, login Google, dan /me agar
     * bentuk responsnya tidak berbeda antar endpoint.
     *
     * @return array{hak_akses: array<string, bool>, bawahan: array<int, array{id: int, nama: string, email: string}>}
     */
    private function hakAksesAtasan(User $user): array
    {
        $servis = app(AtasanLangsungService::class);

        return [
            'hak_akses' => $servis->hakAkses($user),
            'bawahan' => $servis->daftarBawahan($user),
        ];
    }
}
