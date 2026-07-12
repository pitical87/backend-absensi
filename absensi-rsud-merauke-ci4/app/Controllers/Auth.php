<?php

namespace App\Controllers;

use App\Libraries\Struktur;

/**
 * Auth — beranda, masuk (dengan pembatas percobaan), daftar, keluar.
 */
class Auth extends BaseController
{
    private const MAKS_GAGAL   = 5;    // percobaan gagal
    private const JENDELA_MNT  = 15;   // dalam N menit → blokir N menit

    public function beranda()
    {
        if (! $this->skemaSiap()) {
            return redirect()->to('install');
        }
        if (session('uid')) {
            return redirect()->to(session('role') === 'admin' ? 'admin' : 'dashboard');
        }
        return redirect()->to('login');
    }

    // ================= MASUK =================

    public function login()
    {
        if (! $this->skemaSiap()) {
            return redirect()->to('install');
        }
        if (session('uid')) {
            return redirect()->to(session('role') === 'admin' ? 'admin' : 'dashboard');
        }
        return view('auth/login');
    }

    public function prosesLogin()
    {
        if (! $this->skemaSiap()) {
            return redirect()->to('install');
        }

        $email = trim((string) $this->request->getPost('email'));
        $pass  = (string) $this->request->getPost('password');
        $ip    = $this->request->getIPAddress();

        // ---- Pembatas percobaan: 5 kegagalan / 15 menit per email ATAU per IP ----
        $sisa = $this->sisaBlokir($email, $ip);
        if ($sisa > 0) {
            return view('auth/login', [
                'galat' => 'Terlalu banyak percobaan masuk yang gagal. Coba lagi dalam '
                         . $sisa . ' menit.',
            ]);
        }

        $u = $this->db->table('users')->where('email', $email)->get()->getRowArray();

        if (! $u || ! password_verify($pass, $u['password_hash'])) {
            $this->catatPercobaan($email, $ip, false);
            $tersisa = $this->sisaPercobaan($email, $ip);
            return view('auth/login', [
                'galat' => 'Email atau password salah.'
                         . ($tersisa <= 2 ? ' Sisa ' . $tersisa . ' percobaan sebelum akses ditunda sementara.' : ''),
            ]);
        }
        if ($u['status'] !== 'aktif') {
            return view('auth/login', ['galat' => 'Akun Anda dinonaktifkan. Hubungi administrator.']);
        }

        // ---- Berhasil ----
        $this->catatPercobaan($email, $ip, true);
        $this->db->table('login_attempts')
            ->where('email', $email)->where('sukses', 0)->delete();

        session()->regenerate(true);
        session()->set([
            'uid'    => (int) $u['id'],
            'role'   => $u['role'],
            'nama'   => $u['nama_lengkap'],
            'posisi' => $u['posisi'] ?? 'Staf',
        ]);
        catat_aktivitas('Masuk', $u['nama_lengkap'] . ' (' . $u['role'] . ') masuk ke sistem');

        return redirect()->to($u['role'] === 'admin' ? 'admin' : 'dashboard');
    }

    // ================= DAFTAR =================

    public function register()
    {
        if (! $this->skemaSiap()) {
            return redirect()->to('install');
        }
        return view('auth/register', $this->dataMaster());
    }

    public function prosesRegister()
    {
        $d = [
            'nama_lengkap'  => trim((string) $this->request->getPost('nama_lengkap')),
            'tempat_lahir'  => trim((string) $this->request->getPost('tempat_lahir')),
            'tanggal_lahir' => $this->request->getPost('tanggal_lahir') ?: null,
            'jenis_kelamin' => (string) $this->request->getPost('jenis_kelamin'),
            'agama'         => (string) $this->request->getPost('agama'),
            'email'         => trim((string) $this->request->getPost('email')),
            'no_hp'         => trim((string) $this->request->getPost('no_hp')),
            'nip'           => trim((string) $this->request->getPost('nip')) ?: null,
            'unit_kerja_id' => (int) $this->request->getPost('unit_kerja_id') ?: null,
            'sub_unit_id'   => (int) $this->request->getPost('sub_unit_id') ?: null,
            'profesi_id'    => (int) $this->request->getPost('profesi_id') ?: null,
        ];
        $pass  = (string) $this->request->getPost('password');
        $pass2 = (string) $this->request->getPost('password2');

        $galat = [];
        if ($d['nama_lengkap'] === '') $galat[] = 'Nama lengkap wajib diisi.';
        if (! filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $galat[] = 'Email tidak valid.';
        if (! in_array($d['jenis_kelamin'], ['Laki-Laki', 'Perempuan'], true)) $galat[] = 'Jenis kelamin wajib dipilih.';
        if (! in_array($d['agama'], ['Katolik', 'Kristen', 'Islam', 'Hindu', 'Budha', 'Lainnya'], true)) $galat[] = 'Agama wajib dipilih.';
        if (strlen($pass) < 6) $galat[] = 'Password minimal 6 karakter.';
        if ($pass !== $pass2) $galat[] = 'Konfirmasi password tidak sama.';

        if ($this->db->table('users')->where('email', $d['email'])->countAllResults() > 0) {
            $galat[] = 'Email sudah terdaftar. Gunakan email lain atau masuk.';
        }

        $unit = $d['unit_kerja_id']
            ? $this->db->table('unit_kerja')->where('id', $d['unit_kerja_id'])->get()->getRowArray()
            : null;
        if (! $unit) {
            $galat[] = 'Tempat kerja wajib dipilih.';
        } elseif ($unit['punya_sub']) {
            $sah = $d['sub_unit_id'] && $this->db->table('sub_unit')
                ->where('id', $d['sub_unit_id'])->where('unit_kerja_id', $d['unit_kerja_id'])
                ->countAllResults() > 0;
            if (! $sah) $galat[] = 'Sub unit wajib dipilih untuk ' . $unit['nama'] . '.';
        } else {
            $d['sub_unit_id'] = null;
        }
        if (! $d['profesi_id'] || $this->db->table('profesi')->where('id', $d['profesi_id'])->countAllResults() === 0) {
            $galat[] = 'Profesi wajib dipilih.';
        }

        // Jabatan struktural (bawaan Staf/Pelaksana)
        [$kategoriJab, $jabatanId, $galatJab] = (new Struktur())->resolusi(
            (string) $this->request->getPost('jabatan_kategori'),
            (int) $this->request->getPost('jabatan_id')
        );
        if ($galatJab !== '') $galat[] = $galatJab;
        $d['jabatan_kategori'] = $kategoriJab;
        $d['jabatan_id']       = $jabatanId;

        // Posisi (peran alur persetujuan izin/cuti) & status kepegawaian
        $statusPegawai = (string) $this->request->getPost('status_pegawai') === 'PNS' ? 'PNS' : 'Non-PNS';
        [$posisi, $seksiPembinaId, $galatPosisi] = (new Struktur())->resolusiPosisi(
            (string) $this->request->getPost('posisi'),
            $kategoriJab,
            $jabatanId,
            (int) $this->request->getPost('seksi_pembina_id') ?: null
        );
        if ($galatPosisi !== '') $galat[] = $galatPosisi;
        $d['posisi']           = $posisi;
        $d['status_pegawai']   = $statusPegawai;
        $d['seksi_pembina_id'] = $seksiPembinaId;

        if ($galat) {
            return view('auth/register', $this->dataMaster() + [
                'galat' => implode(' ', $galat),
                'lama'  => $this->request->getPost(),
            ]);
        }

        $this->db->table('users')->insert($d + [
            'password_hash' => password_hash($pass, PASSWORD_DEFAULT),
            'role'          => 'pegawai',
            'status'        => 'aktif',
            'created_at'    => date('Y-m-d H:i:s'),
        ]);

        return redirect()->to('login')
            ->with('flash_sukses', 'Pendaftaran berhasil. Silakan masuk dengan email dan password Anda.');
    }

    public function logout()
    {
        if (session('uid')) {
            catat_aktivitas('Keluar', session('nama') . ' keluar dari sistem');
        }
        session()->destroy();
        return redirect()->to('login');
    }

    // ================= UTIL =================

    private function skemaSiap(): bool
    {
        try {
            $this->db->table('pengaturan')->limit(1)->get();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function dataMaster(): array
    {
        $sub = [];
        foreach ($this->db->table('sub_unit')->orderBy('unit_kerja_id')->orderBy('id')->get()->getResultArray() as $s) {
            $sub[(int) $s['unit_kerja_id']][] = ['id' => (int) $s['id'], 'nama' => $s['nama']];
        }
        return [
            'unitList'    => $this->db->table('unit_kerja')->orderBy('id')->get()->getResultArray(),
            'profList'    => $this->db->table('profesi')->orderBy('id')->get()->getResultArray(),
            'subPerUnit'  => $sub,
            'jabPilihan'  => (new Struktur())->pilihan(),
            'kategoriJab' => kategori_jabatan_list(),
            'posisiList'  => posisi_list(),
            'seksiPembinaPilihan' => array_merge(
                (new Struktur())->pilihan()['Kepala Seksi'] ?? [],
                (new Struktur())->pilihan()['Kepala Sub Bagian'] ?? []
            ),
        ];
    }

    private function catatPercobaan(string $email, string $ip, bool $sukses): void
    {
        $this->db->table('login_attempts')->insert([
            'email' => mb_substr($email, 0, 150),
            'ip'    => $ip,
            'sukses'=> $sukses ? 1 : 0,
            'waktu' => date('Y-m-d H:i:s'),
        ]);
        // bersihkan jejak lama (> 2 hari) agar tabel tetap ringkas
        $this->db->table('login_attempts')
            ->where('waktu <', date('Y-m-d H:i:s', strtotime('-2 days')))->delete();
    }

    private function jumlahGagal(string $email, string $ip): int
    {
        $sejak = date('Y-m-d H:i:s', strtotime('-' . self::JENDELA_MNT . ' minutes'));
        return (int) $this->db->table('login_attempts')
            ->where('sukses', 0)->where('waktu >=', $sejak)
            ->groupStart()->where('email', $email)->orWhere('ip', $ip)->groupEnd()
            ->countAllResults();
    }

    private function sisaPercobaan(string $email, string $ip): int
    {
        return max(0, self::MAKS_GAGAL - $this->jumlahGagal($email, $ip));
    }

    /** Menit tersisa blokir; 0 bila tidak diblokir. */
    private function sisaBlokir(string $email, string $ip): int
    {
        if ($this->jumlahGagal($email, $ip) < self::MAKS_GAGAL) {
            return 0;
        }
        $sejak    = date('Y-m-d H:i:s', strtotime('-' . self::JENDELA_MNT . ' minutes'));
        $terbaru  = $this->db->table('login_attempts')
            ->selectMax('waktu')->where('sukses', 0)->where('waktu >=', $sejak)
            ->groupStart()->where('email', $email)->orWhere('ip', $ip)->groupEnd()
            ->get()->getRowArray();
        $habis = strtotime((string) ($terbaru['waktu'] ?? 'now')) + self::JENDELA_MNT * 60;
        return max(1, (int) ceil(($habis - time()) / 60));
    }
}
