<?php

namespace App\Services;

use App\Mail\PeringatanLoginMencurigakanMail;
use App\Models\ApiToken;
use App\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Peringatan email ketika sebuah akun yang masih aktif dikenai percobaan login
 * gagal melebihi batas, yang dihitung tracker "Login Gagal" di panel admin.
 *
 * Email hanya dikirim ke pemilik akun, berisi IP dan perangkat yang dipakai.
 * Syaratnya akun masih aktif dan emailnya sudah terverifikasi: alamat yang
 * belum diverifikasi belum tentu mailbox pemilik, jadi memperingatkan ke sana
 * tidak berguna dan bisa dipakai menipu. Akun yang sudah dinonaktifkan juga
 * tidak diberi peringatan karena aksesnya sudah ditolak di semua jalur login,
 * dan penanganannya ada di tombol Blokir pada halaman tracker.
 *
 * Jendelanya 24 jam, bukan jendela blokir 10 menit, karena blokir sudah aktif
 * tepat pada lima kegagalan sehingga satu jendela blokir mustahil melewati
 * ambang ini. Satu email per akun per jendela tersebut; pendinginan dilepas
 * begitu login berhasil supaya serangan berikutnya langsung memberi tahu lagi.
 */
class PeringatanLoginService
{
    /** Batas gagal; email dikirim bila jumlahnya lebih besar dari ini. */
    public const AMBANG_GAGAL = 5;

    /** Jendela menghitungnya sama dengan default tracker "Login Gagal". */
    public const JENDELA_JAM = 24;

    /** Lama pendinginan: satu email per jendela hitung. */
    private const COOLDOWN_JAM = 24;

    private const MAKS_IP = 8;
    private const MAKS_PERANGKAT = 5;
    private const MAKS_PERCOBAAN = 10;

    /**
     * Periksa satu email yang baru saja gagal login lalu kirim peringatan bila
     * ambangnya terlampaui. Peringatan hanya untuk akun aktif yang emailnya
     * sudah terverifikasi. Tidak pernah melempar error: pengiriman email tidak
     * boleh menggagalkan respons login.
     */
    public function periksa(?string $email): void
    {
        $email = trim((string) $email);
        if ($email === '') {
            return;
        }

        $sejak = now()->subHours(self::JENDELA_JAM);

        $percobaan = LoginAttempt::where('email', $email)
            ->where('sukses', 0)->where('waktu', '>=', $sejak);

        $gagal = (clone $percobaan)->count();
        if ($gagal <= self::AMBANG_GAGAL) {
            return;
        }

        $user = User::where('email', $email)->first();
        if (! $user || ! $user->isActive() || is_null($user->email_verified_at)) {
            return;
        }

        // Pendinginan dipasang sebelum mengirim, agar percobaan berikutnya dalam
        // jendela yang sama tidak ikut mengirim email.
        $kunci = $this->kunciCooldown($email);
        if (! Cache::add($kunci, now()->timestamp, now()->addHours(self::COOLDOWN_JAM))) {
            return;
        }

        $daftarIp = (clone $percobaan)
            ->select('ip', DB::raw('COUNT(*) as jumlah'))
            ->groupBy('ip')->orderByDesc('jumlah')
            ->limit(self::MAKS_IP)->get()
            ->map(fn ($r) => ['ip' => (string) $r->ip, 'jumlah' => (int) $r->jumlah])
            ->values()->all();

        $daftarPerangkat = (clone $percobaan)
            ->select('user_agent', DB::raw('COUNT(*) as jumlah'))
            ->whereNotNull('user_agent')->where('user_agent', '<>', '')
            ->groupBy('user_agent')->orderByDesc('jumlah')
            ->limit(self::MAKS_PERANGKAT)->get()
            ->map(fn ($r) => [
                'nama' => ApiToken::namaPerangkatDariUserAgent($r->user_agent),
                'jumlah' => (int) $r->jumlah,
                'contoh' => mb_substr((string) $r->user_agent, 0, 120),
            ])->values()->all();

        $percobaanTerbaru = (clone $percobaan)
            ->orderByDesc('waktu')->orderByDesc('id')
            ->limit(self::MAKS_PERCOBAAN)->get()
            ->map(fn ($r) => [
                'ip' => (string) $r->ip,
                'perangkat' => ApiToken::namaPerangkatDariUserAgent($r->user_agent),
                'sumber' => $this->labelSumber($r->sumber),
                'waktu' => tgl_id($r->waktu, false).' '.jam_id($r->waktu),
            ])->values()->all();

        $nomor = 'ANC/'.now()->format('Ymd').'/'.strtoupper(substr(sha1($email.now()->timestamp), 0, 6));

        try {
            Mail::to($user->email)->send(new PeringatanLoginMencurigakanMail(
                nama: (string) $user->nama_lengkap,
                email: (string) $user->email,
                jumlahGagal: $gagal,
                jumlahIp: count($daftarIp),
                jumlahPerangkat: count($daftarPerangkat),
                daftarIp: $daftarIp,
                daftarPerangkat: $daftarPerangkat,
                percobaan: $percobaanTerbaru,
                jendelaJam: self::JENDELA_JAM,
                nomor: $nomor,
            ));

            catat_aktivitas('Login Mencurigakan',
                'Percobaan login gagal '.$gagal.'× pada akun '.$user->email
                .' dari '.count($daftarIp).' IP / '.count($daftarPerangkat).' perangkat. '
                .'Peringatan email dikirim ke pemilik akun.');
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Bersihkan pendinginan, dipanggil setelah login berhasil supaya serangan
     * berikutnya pada episode baru langsung memberi peringatan lagi.
     */
    public function bersihkanCooldown(?string $email): void
    {
        $email = trim((string) $email);
        if ($email !== '') {
            Cache::forget($this->kunciCooldown($email));
        }
    }

    private function kunciCooldown(string $email): string
    {
        return 'peringatan-login:'.sha1(mb_strtolower($email));
    }

    private function labelSumber(?string $sumber): string
    {
        return match ($sumber) {
            'web' => 'Web',
            'api' => 'Aplikasi Mobile',
            'google' => 'Google',
            default => 'Tidak diketahui',
        };
    }
}