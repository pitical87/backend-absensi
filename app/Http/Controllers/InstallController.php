<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Throwable;

class InstallController extends Controller
{
    public function index()
    {
        [$tahap, $galat] = $this->siapkanSkema();

        return view('install.index', [
            'tahap' => $tahap,
            'galat' => $galat,
        ]);
    }

    public function buatAdmin(Request $request)
    {
        [$tahap, $galat] = $this->siapkanSkema();
        if ($tahap !== 'admin') {
            return redirect('install');
        }

        $nama  = trim((string) $request->input('nama'));
        $email = trim((string) $request->input('email'));
        $pass  = (string) $request->input('password');
        $pass2 = (string) $request->input('password2');

        if ($nama === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $galat = 'Nama dan email admin wajib diisi dengan benar.';
        } elseif (strlen($pass) < 6) {
            $galat = 'Password minimal 6 karakter.';
        } elseif ($pass !== $pass2) {
            $galat = 'Konfirmasi password tidak sama.';
        } else {
            DB::table('users')->insert([
                'nama_lengkap'  => $nama,
                'email'         => $email,
                'password_hash' => bcrypt($pass),
                'role'          => 'admin',
                'status'        => 'aktif',
                'created_at'    => now(),
            ]);
            catat_aktivitas('Pemasangan', 'Aplikasi terpasang; akun admin pertama dibuat: ' . $email);

            return redirect('login')
                ->with('flash_sukses', 'Pemasangan selesai. Silakan masuk menggunakan akun admin Anda.');
        }

        return view('install.index', ['tahap' => 'admin', 'galat' => $galat]);
    }

    private function siapkanSkema(): array
    {
        try {
            $ada = DB::table('pengaturan')->count() > 0;

            $adaAdmin = DB::table('users')->where('role', 'admin')->count() > 0;

            return [$adaAdmin ? 'selesai' : 'admin', ''];
        } catch (Throwable $e) {
            return ['gagal',
                'Pemasangan gagal: ' . $e->getMessage()
                . ' — Periksa pengaturan database pada .env dan pastikan layanan MySQL sudah berjalan.'];
        }
    }
}
