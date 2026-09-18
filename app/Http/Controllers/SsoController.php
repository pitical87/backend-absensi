<?php

namespace App\Http\Controllers;

use App\Models\ApiToken;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;

class SsoController extends Controller
{
    private const TOKEN_EXP_DAYS = 7;

    public function masuk(): RedirectResponse
    {
        $url = rtrim((string) config('services.web_absen.url', ''), '/');

        if ($url === '') {
            return back()->with('error', 'URL web absen belum dikonfigurasi. Hubungi administrator.');
        }

        $user = User::find(session('uid'));
        if (! $user || $user->status !== 'aktif') {
            return redirect('login');
        }
        if ($user->role === 'admin') {
            return back()->with('error', 'Login ke web absen hanya tersedia untuk pengguna non-admin.');
        }

        $token     = bin2hex(random_bytes(32));
        $expiresAt = Carbon::now()->addDays(self::TOKEN_EXP_DAYS);

        ApiToken::create([
            'user_id'        => $user->id,
            'token'          => $token,
            'expires_at'     => $expiresAt,
            'perangkat'      => 'SSO Web Absen',
            'ip'             => request()->ip(),
            'user_agent'     => substr((string) request()->header('User-Agent', ''), 0, 255),
            'last_aktivitas' => now(),
        ]);

        catat_aktivitas('SSO Web Absen', $user->nama_lengkap.' membuka web absen (token '.substr($token, 0, 8).'…)');

        return redirect($url.'?token='.$token.'&email='.urlencode($user->email));
    }
}