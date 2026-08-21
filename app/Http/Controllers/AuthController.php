<?php

namespace App\Http\Controllers;

use App\Models\Pengaturan;
use App\Models\SubUnit;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\StrukturService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    private const MAKS_GAGAL = 5;
    private const JENDELA_MNT = 15;

    public function beranda()
    {
        if (! $this->skemaSiap()) {
            return redirect('install');
        }
        if (session('uid')) {
            return redirect(session('role') === 'admin' ? 'admin' : 'dashboard');
        }
        return redirect('login');
    }

    public function login()
    {
        if (! $this->skemaSiap()) {
            return redirect('install');
        }
        if (session('uid')) {
            return redirect(session('role') === 'admin' ? 'admin' : 'dashboard');
        }
        return view('auth.login');
    }

    public function prosesLogin(Request $request)
    {
        if (! $this->skemaSiap()) {
            return redirect('install');
        }

        $email = trim((string) $request->input('email'));
        $pass  = (string) $request->input('password');
        $ip    = $request->ip();

        $sisa = $this->sisaBlokir($email, $ip);
        if ($sisa > 0) {
            return view('auth.login', [
                'galat' => 'Terlalu banyak percobaan masuk yang gagal. Coba lagi dalam '
                         . $sisa . ' menit.',
            ]);
        }

        $u = User::where('email', $email)->first();

        if (! $u || ! password_verify($pass, $u->password_hash)) {
            $this->catatPercobaan($email, $ip, false);
            $tersisa = $this->sisaPercobaan($email, $ip);
            return view('auth.login', [
                'galat' => 'Email atau password salah.'
                         . ($tersisa <= 2 ? ' Sisa ' . $tersisa . ' percobaan sebelum akses ditunda sementara.' : ''),
            ]);
        }
        if ($u->status !== 'aktif') {
            return view('auth.login', ['galat' => 'Akun Anda dinonaktifkan. Hubungi administrator.']);
        }

        $this->catatPercobaan($email, $ip, true);
        \App\Models\LoginAttempt::where('email', $email)->where('sukses', 0)->delete();

        session()->regenerate(true);
        session()->put([
            'uid'    => (int) $u->id,
            'role'   => $u->role,
            'nama'   => $u->nama_lengkap,
            'posisi' => $u->posisi ?? 'Staf',
            'email'  => $u->email,
        ]);
        catat_aktivitas('Masuk', $u->nama_lengkap . ' (' . $u->role . ') masuk ke sistem');

        return redirect($u->role === 'admin' ? 'admin' : 'dashboard');
    }
   
    public function logout()
    {
        if (session('uid')) {
            catat_aktivitas('Keluar', session('nama') . ' keluar dari sistem');
        }
        session()->flush();
        return redirect('login');
    }

    private function skemaSiap(): bool
    {
        try {
            Pengaturan::limit(1)->get();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function dataMaster(StrukturService $struktur): array
    {
        $subPerUnit = [];
        foreach (SubUnit::orderBy('unit_kerja_id')->orderBy('id')->get() as $s) {
            $subPerUnit[(int) $s->unit_kerja_id][] = ['id' => (int) $s->id, 'nama' => $s->nama];
        }
        return [
            'unitList'    => UnitKerja::orderBy('id')->get()->all(),
            'profList'    => \App\Models\Profesi::orderBy('id')->get()->all(),
            'subPerUnit'  => $subPerUnit,
            'jabPilihan'  => $struktur->pilihan(),
            'kategoriJab' => kategori_jabatan_list(),
            'posisiList'  => posisi_list(),
            'seksiPembinaPilihan' => array_merge(
                $struktur->pilihan()['Kepala Seksi'] ?? [],
                $struktur->pilihan()['Kepala Sub Bagian'] ?? []
            ),
        ];
    }

    private function catatPercobaan(string $email, string $ip, bool $sukses): void
    {
        \App\Models\LoginAttempt::create([
            'email' => mb_substr($email, 0, 150),
            'ip'    => $ip,
            'sukses'=> $sukses ? 1 : 0,
            'waktu' => now(),
        ]);
        \App\Models\LoginAttempt::where('waktu', '<', now()->subDays(2))->delete();
    }

    private function jumlahGagal(string $email, string $ip): int
    {
        $sejak = now()->subMinutes(self::JENDELA_MNT);
        return (int) \App\Models\LoginAttempt::where('sukses', 0)
            ->where('waktu', '>=', $sejak)
            ->where(function ($q) use ($email, $ip) {
                $q->where('email', $email)->orWhere('ip', $ip);
            })
            ->count();
    }

    private function sisaPercobaan(string $email, string $ip): int
    {
        return max(0, self::MAKS_GAGAL - $this->jumlahGagal($email, $ip));
    }

    private function sisaBlokir(string $email, string $ip): int
    {
        if ($this->jumlahGagal($email, $ip) < self::MAKS_GAGAL) {
            return 0;
        }
        $sejak   = now()->subMinutes(self::JENDELA_MNT);
        $terbaru = \App\Models\LoginAttempt::where('sukses', 0)
            ->where('waktu', '>=', $sejak)
            ->where(function ($q) use ($email, $ip) {
                $q->where('email', $email)->orWhere('ip', $ip);
            })
            ->orderBy('waktu', 'desc')->first();
        $habis = strtotime((string) ($terbaru->waktu ?? 'now')) + self::JENDELA_MNT * 60;
        return max(1, (int) ceil(($habis - time()) / 60));
    }
}
