<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\LaporResetDitolak;
use App\Mail\ResetPasswordMail;
use App\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class LupaPasswordController extends Controller
{
    use LaporResetDitolak;

    private const MASA_BERLAKU_MENIT = 60;

    public function form()
    {
        if (session('uid')) {
            return redirect(session('role') === 'admin' ? 'admin' : 'dashboard');
        }
        return view('auth.lupa-password');
    }

    public function kirim(Request $request)
    {
        $email = strtolower(trim((string) $request->input('email')));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return back()->withInput()->with('galat', 'Masukkan alamat email yang valid.');
        }

        $user = User::where('email', $email)->where('status', 'aktif')->first();

        if (! $user) {
            catat_aktivitas('Lupa Password', 'Percobaan reset untuk email yang tidak terdaftar/aktif: '.$email);
            return back()->withInput()->with('galat', 'Email tersebut tidak terdaftar pada sistem.');
        }

        // Email belum diverifikasi: tautan reset password tidak dibuat/dikirim sama sekali.
        if (is_null($user->email_verified_at)) {
            $adaTokenLama = $this->cabutTokenReset($email);

            $this->laporkanResetDitolak($user, $adaTokenLama);

            return back()->withInput()->with('galat',
                'Email ini belum diverifikasi, sehingga tautan reset password tidak dapat dikirim. '
                .'Keadaan ini telah dilaporkan kepada administrator. '
                .'Silakan hubungi administrator untuk memverifikasi email Anda terlebih dahulu.');
        }

        $token = Str::random(64);
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            ['token' => bcrypt($token), 'created_at' => now()]
        );

        $url   = route('reset-password', ['token' => $token, 'email' => $email]);
        $nomor = 'ABS/' . now()->format('Ymd') . '/' . strtoupper(substr($token, 0, 6));

        try {
            Mail::to($email)->send(new ResetPasswordMail(
                (string) $user->nama_lengkap,
                $url,
                self::MASA_BERLAKU_MENIT,
                $nomor,
            ));
        } catch (\Throwable $e) {
            report($e);
        }

        catat_aktivitas('Lupa Password', 'Tautan reset password dikirim untuk '.$email);

        return back()->withInput()->with('sukses', 'Tautan reset password telah dikirim ke email Anda.');
    }

    public function formReset(Request $request)
    {
        if (session('uid')) {
            return redirect(session('role') === 'admin' ? 'admin' : 'dashboard');
        }

        $token = trim((string) $request->get('token'));
        $email = strtolower(trim((string) $request->get('email')));

        if (! $this->tokenSah($token, $email)) {
            return redirect(route('lupa-password'))
                ->with('galat', 'Tautan reset password tidak valid atau sudah kedaluwarsa. Silakan ulangi permintaan.');
        }

        // Email belum terverifikasi: tautan tidak berlaku meskipun tokennya masih sah.
        if ($this->emailBelumTerverifikasi($email)) {
            $this->cabutTokenReset($email);

            return redirect(route('lupa-password'))
                ->with('galat', 'Email ini belum diverifikasi sehingga tautan reset password tidak berlaku. '
                    .'Keadaan ini telah dilaporkan kepada administrator.');
        }

        return view('auth.reset-password', [
            'token' => $token,
            'email' => $email,
        ]);
    }

    public function prosesReset(Request $request)
    {
        $token = trim((string) $request->input('token'));
        $email = strtolower(trim((string) $request->input('email')));
        $passBaru = (string) $request->input('password');
        $passKonf = (string) $request->input('password_konfirmasi');

        if (! $this->tokenSah($token, $email)) {
            return redirect(route('lupa-password'))
                ->with('galat', 'Tautan reset password tidak valid atau sudah kedaluwarsa. Silakan ulangi permintaan.');
        }
        if (strlen($passBaru) < 6) {
            return back()->withInput()->with('galat', 'Password baru minimal 6 karakter.');
        }
        if ($passBaru !== $passKonf) {
            return back()->withInput()->with('galat', 'Konfirmasi password baru tidak cocok.');
        }

        $user = User::where('email', $email)->where('status', 'aktif')->first();
        if (! $user) {
            return redirect(route('lupa-password'))
                ->with('galat', 'Akun tidak ditemukan atau telah dinonaktifkan.');
        }

        // Tautan yang terbit sebelum email diverifikasi (mis. email lalu diganti) tidak berlaku.
        if ($this->emailBelumTerverifikasi($email)) {
            $this->cabutTokenReset($email);

            catat_aktivitas('Lupa Password',
                'Reset password untuk '.$email.' ditolak: email belum diverifikasi. Token dicabut.');

            return redirect(route('lupa-password'))
                ->with('galat', 'Email ini belum diverifikasi sehingga reset password tidak dapat diproses. '
                    .'Keadaan ini telah dilaporkan kepada administrator.');
        }

        $user->update(['password_hash' => bcrypt($passBaru)]);

        DB::table('password_reset_tokens')->where('email', $email)->delete();
        LoginAttempt::where('email', $email)->where('sukses', 0)->delete();

        catat_aktivitas('Reset Password', 'Password akun '.$email.' direset melalui tautan lupa password');

        return redirect(route('login'))->with('success', 'Password berhasil direset. Silakan masuk dengan password baru.');
    }

    private function emailBelumTerverifikasi(string $email): bool
    {
        return User::where('email', $email)->where('status', 'aktif')
            ->whereNull('email_verified_at')->exists();
    }

    private function tokenSah(string $token, string $email): bool
    {
        if ($token === '' || $email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $row = DB::table('password_reset_tokens')->where('email', $email)->first();
        if (! $row) {
            return false;
        }

        return password_verify($token, (string) $row->token)
            && strtotime((string) $row->created_at) >= now()->subMinutes(self::MASA_BERLAKU_MENIT)->getTimestamp();
    }
}