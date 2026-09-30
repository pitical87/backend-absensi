<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Notifikasi;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Pelaporan permintaan reset password yang ditolak karena email belum diverifikasi.
 *
 * Dipakai bersama oleh alur web (LupaPasswordController) dan mobile (Api\AuthController).
 */
trait LaporResetDitolak
{
    /**
     * Catat laporan ke Log Aktivitas dan kirim notifikasi ke administrator.
     *
     * Notifikasi dibuat maksimal sekali per email per jam agar administrator tidak dibanjiri
     * bila formulir lupa-password diisi berulang kali. Catatan lengkap tetap masuk Log Aktivitas.
     *
     * @param  bool  $adaTokenLama  true bila ada token lama yang dicabut pada proses ini
     */
    private function laporkanResetDitolak(User $user, bool $adaTokenLama = false): void
    {
        $isi = 'Permintaan reset password untuk '.$user->email.' ditolak karena email belum diverifikasi. '
            .'Verifikasi email '.($user->nama_lengkap !== '' ? $user->nama_lengkap.' ' : '')
            .'sebelum mengizinkan reset password.';

        // Dicek sebelum pencatatan agar laporan pada percobaan pertama tetap terkirim.
        $sudahDilaporkan = DB::table('aktivitas_log')
            ->where('aksi', 'Lupa Password')
            ->where('detail', 'like', '%ditolak karena email belum diverifikasi%')
            ->where('detail', 'like', '%'.$user->email.'%')
            ->where('waktu', '>=', now()->subHour())
            ->exists();

        catat_aktivitas('Lupa Password', $isi.($adaTokenLama ? ' Token reset lama dicabut.' : ''));

        if ($sudahDilaporkan) {
            return;
        }

        foreach (User::where('role', 'admin')->where('status', 'aktif')->get() as $admin) {
            Notifikasi::create([
                'user_id' => $admin->id,
                'isi'     => $isi,
                'url'     => 'admin/aktivitas',
                'tipe'    => 'warning',
            ]);
        }
    }

    /**
     * Cabut token reset yang sudah terbit untuk email tersebut.
     *
     * Dipanggil sebelum melapor agar tautan lama tidak bisa dipakai walau email belum terverifikasi.
     */
    private function cabutTokenReset(string $email): bool
    {
        return DB::table('password_reset_tokens')->where('email', $email)->delete() > 0;
    }
}
