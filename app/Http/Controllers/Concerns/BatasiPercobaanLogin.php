<?php

namespace App\Http\Controllers\Concerns;

use App\Models\LoginAttempt;

/**
 * Pembatasan percobaan login berbasis tabel login_attempts.
 *
 * $email dipakai sebagai kunci bersama bila sudah diketahui dari kredensial
 * (login email-password). Bila $email null, hanya IP yang dihitung; dipakai
 * login dengan Google karena alamat email pada request berasal dari klien dan
 * tidak boleh dijadikan kunci (bisa dipakai mengunci akun orang lain).
 */
trait BatasiPercobaanLogin
{
    private const MAX_FAIL = 5;

    /**
     * Lama penundaan setelah batas gagal tercapai. Percobaan tidak dicatat
     * selama sudah diblokir, sehingga blokir selalu habis tepat
     * WINDOW_MINUTE menit setelah kegagalan terakhir; percobaan ulang saat
     * diblokir tidak menggeser jendela.
     */
    private const WINDOW_MINUTE = 10;

    /** Berapa hari riwayat percobaan login disimpan untuk tracker admin. */
    private const RETENSI_HARI = 30;

    /** Sisa menit penundaan, 0 bila tidak sedang diblokir. */
    protected function sisaBlokir(?string $email, string $ip): int
    {
        if ($this->jumlahGagal($email, $ip) < self::MAX_FAIL) {
            return 0;
        }

        $sejak = now()->subMinutes(self::WINDOW_MINUTE);
        $terbaru = $this->queryGagal($email, $ip)->where('waktu', '>=', $sejak)
            ->orderBy('waktu', 'desc')->first();
        $habis = strtotime((string) ($terbaru->waktu ?? 'now')) + self::WINDOW_MINUTE * 60;

        return max(1, (int) ceil(($habis - time()) / 60));
    }

    /**
     * Catat satu percobaan login beserta asal jalurnya (web/api/google) dan
     * user agent, dipakai tracker ancaman di panel admin.
     */
    protected function catatPercobaan(?string $email, string $ip, bool $sukses, string $sumber = 'api'): void
    {
        LoginAttempt::create([
            'email' => mb_substr((string) $email, 0, 150),
            'ip' => $ip,
            'sumber' => $sumber,
            'user_agent' => mb_substr((string) request()->userAgent(), 0, 255),
            'sukses' => $sukses ? 1 : 0,
            'waktu' => now(),
        ]);
        LoginAttempt::where('waktu', '<', now()->subDays(self::RETENSI_HARI))->delete();
    }

    protected function jumlahGagal(?string $email, string $ip): int
    {
        return $this->queryGagal($email, $ip)
            ->where('waktu', '>=', now()->subMinutes(self::WINDOW_MINUTE))
            ->count();
    }

    /** Bersihkan riwayat gagal seorang pengguna agar tidak ikut terblokir. */
    protected function hapusPercobaanGagal(string $email): void
    {
        LoginAttempt::where('email', $email)->where('sukses', 0)->delete();
    }

    private function queryGagal(?string $email, string $ip)
    {
        $query = LoginAttempt::where('sukses', 0);

        return $email !== null && $email !== ''
            ? $query->where(function ($q) use ($email, $ip) {
                $q->where('email', $email)->orWhere('ip', $ip);
            })
            : $query->where('ip', $ip);
    }
}
