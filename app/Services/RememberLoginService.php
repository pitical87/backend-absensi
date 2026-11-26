<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Throwable;

/**
 * Manajemen fitur "Ingat saya" pada login web (admin & pegawai).
 *
 * Sesi Laravel bawaan (SESSION_LIFETIME=120 menit) otomatis kedaluwarsa di
 * tabel `sessions` atau terkena garbage-collection setelah 2 jam tidak aktif,
 * sehingga mengubah config('session.lifetime') di middleware saja tidak cukup.
 *
 * Layanan ini menerbitkan cookie persisten `remember_login` (HttpOnly, terenkripsi
 * oleh EncryptCookies Laravel, dan ditandatangani HMAC-SHA256 yang terikat ke
 * `password_hash` user + APP_KEY) agar sesi otomatis dipulihkan dengan aman selama
 * masa aktif "Ingat saya" (24 jam), dan langsung tidak berlaku saat logout,
 * ganti password, atau akun dinonaktifkan.
 */
class RememberLoginService
{
    public const COOKIE_NAME = 'remember_login';

    public const DURASI_DETIK = 86400; // 24 jam

    /**
     * Aktifkan sesi "Ingat saya" saat login berhasil.
     */
    public function aktifkan(Request $request, User $user): int
    {
        $sekarang = time();
        $berakhir = $sekarang + self::DURASI_DETIK;
        $jti = bin2hex(random_bytes(16));

        session()->put('remember_login_until', $berakhir);
        $this->terapkanUmurSesi($berakhir - $sekarang);

        $payload = [
            'uid' => (int) $user->id,
            'iat' => $sekarang,
            'exp' => $berakhir,
            'jti' => $jti,
            'sig' => $this->tandaTangan((int) $user->id, $sekarang, $berakhir, $jti, (string) $user->password_hash),
        ];

        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if (is_string($encoded)) {
            $menit = max(1, (int) ceil(self::DURASI_DETIK / 60));
            Cookie::queue($this->buatCookie($request, $encoded, $menit));
        }

        return $berakhir;
    }

    /**
     * Pertahankan umur sesi "Ingat saya" bila masih aktif, atau pulihkan sesi
     * otomatis dari cookie `remember_login` apabila sesi Laravel sudah kedaluwarsa
     * di server / browser baru dibuka kembali.
     */
    public function sinkronkan(Request $request): void
    {
        $rememberUntil = session('remember_login_until');

        if (session('uid') && $rememberUntil !== null) {
            $sisaDetik = (int) $rememberUntil - time();
            if ($sisaDetik <= 0) {
                $this->cabut($request);
                session()->flush();

                return;
            }

            $this->terapkanUmurSesi($sisaDetik);

            return;
        }

        if (! session('uid')) {
            $this->pulihkanDariCookie($request);
        }
    }

    /**
     * Pulihkan sesi web dari cookie `remember_login` jika valid.
     */
    public function pulihkanDariCookie(Request $request): bool
    {
        $raw = $request->cookie(self::COOKIE_NAME);
        if (! is_string($raw) || $raw === '') {
            return false;
        }

        $data = json_decode($raw, true);
        if (! is_array($data)) {
            $this->hapusCookie($request);

            return false;
        }

        $uid = (int) ($data['uid'] ?? 0);
        $iat = (int) ($data['iat'] ?? 0);
        $exp = (int) ($data['exp'] ?? 0);
        $jti = (string) ($data['jti'] ?? '');
        $sig = (string) ($data['sig'] ?? '');

        $sekarang = time();
        if ($uid <= 0 || $iat <= 0 || $exp <= $sekarang || $jti === '' || $sig === '') {
            $this->hapusCookie($request);

            return false;
        }

        try {
            if (Cache::get($this->kunciCabutToken($jti))) {
                $this->hapusCookie($request);

                return false;
            }

            $cabutSebelum = (int) Cache::get($this->kunciCabutUser($uid), 0);
            if ($cabutSebelum > 0 && $iat <= $cabutSebelum) {
                $this->hapusCookie($request);

                return false;
            }

            $user = User::find($uid);
        } catch (Throwable) {
            // Skema DB belum siap (mis. halaman /install) — jangan gagalkan request.
            return false;
        }

        if (! $user || $user->status !== 'aktif') {
            $this->hapusCookie($request);

            return false;
        }

        $expected = $this->tandaTangan($uid, $iat, $exp, $jti, (string) $user->password_hash);
        if (! hash_equals($expected, $sig)) {
            $this->hapusCookie($request);

            return false;
        }

        session()->regenerate(true);
        session()->put([
            'uid' => (int) $user->id,
            'role' => $user->role,
            'nama' => $user->nama_lengkap,
            'posisi' => $user->posisi ?? 'Staf',
            'email' => $user->email,
            'remember_login_until' => $exp,
        ]);

        $this->terapkanUmurSesi($exp - $sekarang);

        return true;
    }

    /**
     * Cabut cookie "Ingat saya" saat logout atau login ulang tanpa mencentang "Ingat saya".
     */
    public function cabut(Request $request): void
    {
        $raw = $request->cookie(self::COOKIE_NAME);
        if (is_string($raw) && $raw !== '') {
            $data = json_decode($raw, true);
            $jti = is_array($data) ? (string) ($data['jti'] ?? '') : '';
            $exp = is_array($data) ? (int) ($data['exp'] ?? 0) : 0;
            $sisa = $exp - time();
            if ($jti !== '' && $sisa > 0) {
                try {
                    Cache::put($this->kunciCabutToken($jti), true, $sisa);
                } catch (Throwable) {
                    // abaikan bila store cache belum siap
                }
            }
        }

        session()->forget('remember_login_until');
        $this->hapusCookie($request);
    }

    /**
     * Cabut seluruh cookie "Ingat saya" milik seorang user (mis. saat ganti password atau blokir akun).
     */
    public function cabutSemuaUntukUser(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        try {
            Cache::put($this->kunciCabutUser($userId), time(), self::DURASI_DETIK + 60);
        } catch (Throwable) {
            // abaikan bila store cache belum siap
        }
    }

    private function terapkanUmurSesi(int $sisaDetik): void
    {
        config([
            'session.lifetime' => max(1, (int) ceil($sisaDetik / 60)),
            'session.expire_on_close' => false,
        ]);
    }

    private function tandaTangan(int $uid, int $iat, int $exp, string $jti, string $passwordHash): string
    {
        return hash_hmac(
            'sha256',
            "{$uid}|{$iat}|{$exp}|{$jti}|{$passwordHash}",
            (string) config('app.key')
        );
    }

    private function kunciCabutToken(string $jti): string
    {
        return 'remember_login_revoked:'.$jti;
    }

    private function kunciCabutUser(int $userId): string
    {
        return 'remember_login_revoked_before:'.$userId;
    }

    private function buatCookie(Request $request, string $value, int $minutes): \Symfony\Component\HttpFoundation\Cookie
    {
        $path = (string) config('session.path', '/');
        $domain = config('session.domain');
        $secure = config('session.secure');
        if ($secure === null) {
            $secure = $request->isSecure();
        }
        $sameSite = (string) (config('session.same_site') ?: 'lax');

        return Cookie::make(
            self::COOKIE_NAME,
            $value,
            $minutes,
            $path,
            is_string($domain) && $domain !== '' ? $domain : null,
            (bool) $secure,
            true,
            false,
            $sameSite
        );
    }

    private function hapusCookie(Request $request): void
    {
        $path = (string) config('session.path', '/');
        $domain = config('session.domain');

        Cookie::queue(Cookie::forget(
            self::COOKIE_NAME,
            $path,
            is_string($domain) && $domain !== '' ? $domain : null
        ));
    }
}
