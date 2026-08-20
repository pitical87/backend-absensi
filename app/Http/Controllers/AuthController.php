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
        ]);
        catat_aktivitas('Masuk', $u->nama_lengkap . ' (' . $u->role . ') masuk ke sistem');

        return redirect($u->role === 'admin' ? 'admin' : 'dashboard');
    }

    public function register(StrukturService $struktur)
    {
        if (! $this->skemaSiap()) {
            return redirect('install');
        }
        return view('auth.register', $this->dataMaster($struktur) + ['lebar' => true]);
    }

    public function prosesRegister(Request $request, StrukturService $struktur)
    {
        $d = [
            'nama_lengkap'  => trim((string) $request->input('nama_lengkap')),
            'tempat_lahir'  => trim((string) $request->input('tempat_lahir')),
            'tanggal_lahir' => $request->input('tanggal_lahir') ?: null,
            'jenis_kelamin' => (string) $request->input('jenis_kelamin'),
            'agama'         => (string) $request->input('agama'),
            'email'         => trim((string) $request->input('email')),
            'no_hp'         => trim((string) $request->input('no_hp')),
            'nip'           => trim((string) $request->input('nip')) ?: null,
            'unit_kerja_id' => (int) $request->input('unit_kerja_id') ?: null,
            'sub_unit_id'   => (int) $request->input('sub_unit_id') ?: null,
            'profesi_id'    => (int) $request->input('profesi_id') ?: null,
        ];
        $pass  = (string) $request->input('password');
        $pass2 = (string) $request->input('password2');

        $galat = [];
        if ($d['nama_lengkap'] === '') $galat[] = 'Nama lengkap wajib diisi.';
        if (! filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $galat[] = 'Email tidak valid.';
        if (! in_array($d['jenis_kelamin'], ['Laki-Laki', 'Perempuan'], true)) $galat[] = 'Jenis kelamin wajib dipilih.';
        if (! in_array($d['agama'], ['Katolik', 'Kristen', 'Islam', 'Hindu', 'Budha', 'Lainnya'], true)) $galat[] = 'Agama wajib dipilih.';
        if (strlen($pass) < 6) $galat[] = 'Password minimal 6 karakter.';
        if ($pass !== $pass2) $galat[] = 'Konfirmasi password tidak sama.';

        if (User::where('email', $d['email'])->count() > 0) {
            $galat[] = 'Email sudah terdaftar. Gunakan email lain atau masuk.';
        }

        $unit = $d['unit_kerja_id']
            ? UnitKerja::find($d['unit_kerja_id'])
            : null;
        if (! $unit) {
            $galat[] = 'Tempat kerja wajib dipilih.';
        } elseif ($unit->punya_sub) {
            $sah = $d['sub_unit_id'] && SubUnit::where('id', $d['sub_unit_id'])
                ->where('unit_kerja_id', $d['unit_kerja_id'])
                ->count() > 0;
            if (! $sah) $galat[] = 'Sub unit wajib dipilih untuk ' . $unit->nama . '.';
        } else {
            $d['sub_unit_id'] = null;
        }
        if (! $d['profesi_id'] || \App\Models\Profesi::where('id', $d['profesi_id'])->count() === 0) {
            $galat[] = 'Profesi wajib dipilih.';
        }

        [$kategoriJab, $jabatanId, $galatJab] = $struktur->resolusi(
            (string) $request->input('jabatan_kategori'),
            (int) $request->input('jabatan_id')
        );
        if ($galatJab !== '') $galat[] = $galatJab;
        $d['jabatan_kategori'] = $kategoriJab;
        $d['jabatan_id']       = $jabatanId;

        $statusPegawai = (string) $request->input('status_pegawai') === 'PNS' ? 'PNS' : 'Non-PNS';
        [$posisi, $seksiPembinaId, $galatPosisi] = $struktur->resolusiPosisi(
            (string) $request->input('posisi'),
            $kategoriJab,
            $jabatanId,
            (int) $request->input('seksi_pembina_id') ?: null
        );
        if ($galatPosisi !== '') $galat[] = $galatPosisi;
        $d['posisi']           = $posisi;
        $d['status_pegawai']   = $statusPegawai;
        $d['seksi_pembina_id'] = $seksiPembinaId;

        if ($galat) {
            return view('auth.register', $this->dataMaster($struktur) + [
                'galat' => implode(' ', $galat),
                'lama'  => $request->all(),
                'lebar' => true,
            ]);
        }

        User::create($d + [
            'password_hash' => bcrypt($pass),
            'role'          => 'pegawai',
            'status'        => 'aktif',
            'created_at'    => now(),
        ]);

        return redirect('login')
            ->with('success', 'Pendaftaran berhasil. Silakan masuk dengan email dan password Anda.');
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
