<?php

namespace App\Services;

use App\Mail\VerifikasiEmailMail;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class VerifikasiEmailService
{
    public const MASA_BERLAKU_MENIT = 60;

    public const COOLDOWN_DETIK = 60;

    /**
     * Status verifikasi email milik pengguna.
     *
     * @return array{terverifikasi: bool, email: string, email_terverifikasi_at: ?string,
     *               menunggu: bool, sisa_detik: int}
     */
    public function status(User $user): array
    {
        $pending = $this->pendingToken((int) $user->id);

        return [
            'terverifikasi'         => ! is_null($user->email_verified_at),
            'email'                 => (string) $user->email,
            'email_terverifikasi_at' => $user->email_verified_at?->toIso8601String(),
            'menunggu'               => $pending !== null && ! $this->kedaluwarsa($pending),
            'sisa_detik'             => $this->sisaCooldown($pending),
        ];
    }

    /**
     * Buat tautan verifikasi dan kirim ke email akun pengguna.
     *
     * @return array{sukses: bool, pesan: string, galat: array<string, string>, terverifikasi: bool,
     *               mail_gagal: bool, cooldown: bool, sisa_detik: int, kirim_ulang_pada: ?string}
     */
    public function kirim(User $user, string $email): array
    {
        $email = strtolower(trim($email));

        $sudah = ! is_null($user->email_verified_at);

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->gagalKirim(['email' => 'Masukkan alamat email yang valid.'], $sudah);
        }

        if ($email !== strtolower((string) $user->email)) {
            return $this->gagalKirim(
                ['email' => 'Email tidak sesuai dengan akun Anda. Perbarui email di halaman Update Data terlebih dahulu.'],
                $sudah
            );
        }

        if ($sudah) {
            return [
                'sukses'           => true,
                'pesan'            => 'Email Anda sudah terverifikasi.',
                'galat'            => [],
                'terverifikasi'    => true,
                'mail_gagal'       => false,
                'cooldown'         => false,
                'sisa_detik'       => 0,
                'kirim_ulang_pada' => null,
            ];
        }

        $pending = $this->pendingToken((int) $user->id);
        $sisa = $this->sisaCooldown($pending);
        if ($sisa > 0) {
            return [
                'sukses'           => false,
                'pesan'            => "Tunggu {$sisa} detik sebelum meminta tautan verifikasi ulang.",
                'galat'            => [],
                'terverifikasi'    => false,
                'mail_gagal'       => false,
                'cooldown'         => true,
                'sisa_detik'       => $sisa,
                'kirim_ulang_pada' => null,
            ];
        }

        $token = Str::random(64);
        $kirimPada = now();

        // `verified_at => null` wajib ikut ditulis: baris yang pernah terverifikasi
        // (pengguna mengganti email lalu kembali lagi) harus kembali jadi pending.
        DB::table('email_verification_tokens')->updateOrInsert(
            ['user_id' => $user->id, 'email' => $email],
            [
                'token'       => bcrypt($token),
                'created_at'  => $kirimPada,
                'verified_at' => null,
            ]
        );

        $url = route('verifikasi-email.konfirmasi', ['id' => $this->idToken($user->id, $email), 'token' => $token]);
        $nomor = 'VER/' . now()->format('Ymd') . '/' . strtoupper(substr($token, 0, 6));

        $mailGagal = false;
        try {
            Mail::to($email)->send(new VerifikasiEmailMail(
                (string) $user->nama_lengkap,
                $email,
                $url,
                self::MASA_BERLAKU_MENIT,
                $nomor,
            ));
        } catch (\Throwable $e) {
            report($e);
            $mailGagal = true;
        }

        catat_aktivitas(
            'Verifikasi Email',
            $mailGagal
                ? 'Pengiriman tautan verifikasi untuk ' . $email . ' gagal, coba lagi nanti.'
                : 'Tautan verifikasi dikirim untuk ' . $email,
            (int) $user->id
        );

        return [
            'sukses'           => ! $mailGagal,
            'pesan'            => $mailGagal
                ? 'Terjadi kendala saat mengirim email. Coba lagi nanti.'
                : 'Tautan verifikasi telah dikirim ke email Anda.',
            'galat'            => [],
            'terverifikasi'    => false,
            'mail_gagal'       => $mailGagal,
            'cooldown'         => false,
            'sisa_detik'       => self::COOLDOWN_DETIK,
            'kirim_ulang_pada' => $kirimPada->copy()->addSeconds(self::COOLDOWN_DETIK)->toIso8601String(),
        ];
    }

    /**
     * Verifikasi email dari tautan pada email. Dipanggil hanya dari halaman web
     * sehingga tautan tetap bisa dibuka dari perangkat lain tanpa login.
     *
     * Token yang sudah pernah dipakai tidak dihapus melainkan ditandai
     * `verified_at`, sehingga klik kedua (pratinjau email) tetap idempoten.
     *
     * @return array{sukses: bool, pesan: string, nama: ?string, email: ?string,
     *               waktu: ?Carbon}
     */
    public function konfirmasi(int $id, string $token): array
    {
        $row = DB::table('email_verification_tokens')->where('id', $id)->first();

        if (! $row) {
            return $this->gagalKonfirmasi('Tautan verifikasi tidak dikenal atau tidak ditemukan.');
        }

        if (! password_verify($token, (string) $row->token)) {
            return $this->gagalKonfirmasi('Tautan verifikasi tidak valid.');
        }

        $user = User::find($row->user_id);

        // Sudah pernah dipakai → tetap sukses agar klik kedua/pratinjau email
        // tidak menampilkan pesan gagal.
        if ($row->verified_at) {
            return [
                'sukses' => true,
                'pesan'  => 'Email berhasil diverifikasi.',
                'nama'   => $user?->nama_lengkap,
                'email'  => (string) $row->email,
                'waktu'  => Carbon::parse($row->verified_at),
            ];
        }

        if ($this->kedaluwarsa($row)) {
            return $this->gagalKonfirmasi('Tautan verifikasi sudah kedaluwarsa. Silakan minta tautan baru dari aplikasi.');
        }

        if (! $user || ! $user->isActive()) {
            return $this->gagalKonfirmasi('Akun tidak ditemukan atau telah dinonaktifkan.');
        }

        $waktu = now();
        $user->update(['email_verified_at' => $waktu]);
        DB::table('email_verification_tokens')->where('id', $id)->update(['verified_at' => $waktu]);

        catat_aktivitas(
            'Verifikasi Email',
            $user->nama_lengkap . ' memverifikasi email ' . $row->email,
            (int) $user->id
        );

        return [
            'sukses' => true,
            'pesan'  => 'Email berhasil diverifikasi.',
            'nama'   => (string) $user->nama_lengkap,
            'email'  => (string) $row->email,
            'waktu'  => $waktu,
        ];
    }

    /**
     * @return array{sukses: bool, pesan: string, galat: array<string, string>, terverifikasi: bool, mail_gagal: bool, cooldown: bool, sisa_detik: int, kirim_ulang_pada: ?string}
     */
    private function gagalKirim(array $galat, bool $terverifikasi): array
    {
        return [
            'sukses'           => false,
            'pesan'            => implode(' ', $galat),
            'galat'            => $galat,
            'terverifikasi'    => $terverifikasi,
            'mail_gagal'       => false,
            'cooldown'         => false,
            'sisa_detik'       => 0,
            'kirim_ulang_pada' => null,
        ];
    }

    /**
     * @return array{sukses: bool, pesan: string, nama: null, email: null, waktu: null}
     */
    private function gagalKonfirmasi(string $pesan): array
    {
        return ['sukses' => false, 'pesan' => $pesan, 'nama' => null, 'email' => null, 'waktu' => null];
    }

    private function pendingToken(int $userId)
    {
        return DB::table('email_verification_tokens')
            ->where('user_id', $userId)
            ->whereNull('verified_at')
            ->orderByDesc('id')
            ->first();
    }

    private function idToken(int $userId, string $email): int
    {
        return (int) DB::table('email_verification_tokens')
            ->where('user_id', $userId)
            ->where('email', $email)
            ->value('id');
    }

    private function kedaluwarsa(object $row): bool
    {
        return strtotime((string) $row->created_at)
            < now()->subMinutes(self::MASA_BERLAKU_MENIT)->getTimestamp();
    }

    private function sisaCooldown(?object $row): int
    {
        if (! $row) {
            return 0;
        }

        $sisa = strtotime((string) $row->created_at) + self::COOLDOWN_DETIK - now()->getTimestamp();

        return $sisa > 0 ? (int) ceil($sisa) : 0;
    }
}
