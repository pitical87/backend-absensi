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
    private const MIN_ISI_DETIK = 3;

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
        session(['login_form_started' => time()]);
        return view('auth.login');
    }

    public function captcha()
    {
        $kode = $this->kodeCaptcha();
        $token = bin2hex(random_bytes(16));
        // Simpan kode (huruf besar) di session dgn token acak. Antarmuka gambar memakai kode ini.
        session()->put('captcha_'.$token, $kode);
        // Batasi jumlah token aktif agar tidak menumpuk.
        $simpan = [];
        foreach ($this->tokenCaptcha() as $t) {
            $simpan[] = $t;
        }
        if (count($simpan) > 10) {
            foreach (array_slice($simpan, 0, count($simpan) - 10) as $t) {
                session()->forget('captcha_'.$t);
            }
        }

        return response()->json([
            'token'    => $token,
            'url'      => url('captcha/gambar/'.$token),
            'panjang'  => strlen($kode),
            'keterangan' => 'Ketik huruf/angka pada gambar di atas (tanpa spasi).',
        ]);
    }

    public function gambarCaptcha(string $token)
    {
        $kode = session()->get('captcha_'.$token);
        // Hanya render gambar; verifikasi (& pemakaian sekali pakai) terjadi saat submit login.
        if (! is_string($kode) || $kode === '') {
            abort(404);
        }
        return response($this->gambarSvg($kode), 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }

    public function prosesLogin(Request $request)
    {
        if (! $this->skemaSiap()) {
            return redirect('install');
        }

        $email = trim((string) $request->input('email'));
        $pass  = (string) $request->input('password');
        $ip    = $request->ip();

        // Validasi CAPTCHA + pertahanan anti-bot (honeypot & kecepatan isi form).
        $token = (string) $request->input('captcha_token');
        $jawab = trim((string) $request->input('captcha'));
        $honey = (string) $request->input('website');
        $mulai = (int) session('login_form_started', 0);
        session()->forget('login_form_started');

        // Bot biasanya (a) mengisi kolom tersembunyi honeypot, (b) submit sangat cepat,
        // atau (c) salah menjawab gambar CAPTCHA.
        if ($honey !== '' || (time() - $mulai) < self::MIN_ISI_DETIK || ! $this->verifikasiCaptcha($token, $jawab)) {
            $tersisa = $this->sisaPercobaan($email, $ip);
            return view('auth.login', [
                'galat' => 'Verifikasi CAPTCHA gagal. Coba lagi.'
                         . ($tersisa <= 2 ? ' Sisa '.$tersisa.' percobaan sebelum akses ditunda sementara.' : ''),
            ]);
        }

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

    private function tokenCaptcha(): array
    {
        $keys = [];
        foreach ((array) session()->all() as $k => $v) {
            if (is_string($k) && str_starts_with($k, 'captcha_')) {
                $keys[] = substr($k, strlen('captcha_'));
            }
        }
        return $keys;
    }

    private function verifikasiCaptcha(string $token, string $jawab): bool
    {
        $kunci = 'captcha_'.$token;
        $kode = session()->get($kunci);
        // Token selalu sekali pakai, apapun hasil verifikasinya.
        session()->forget($kunci);
        if ($token === '' || ! is_string($kode)) {
            return false;
        }
        $bersih = strtoupper(preg_replace('/\s+/', '', trim($jawab)));
        return $bersih !== '' && hash_equals($kode, $bersih);
    }

    private function kodeCaptcha(int $panjang = 5): string
    {
        // Tanpa karakter ambigu (0/O, 1/I/L) agar mudah dibaca manusia.
        $alfabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $kode = '';
        for ($i = 0; $i < $panjang; $i++) {
            $kode .= $alfabet[random_int(0, strlen($alfabet) - 1)];
        }

        return $kode;
    }

    private function warnaAcak(int $min, int $max): string
    {
        return 'rgb('
            .random_int($min, $max).','
            .random_int($min, $max).','
            .random_int($min, $max).')';
    }

    /** Render kode menjadi gambar SVG ber-noise (tanpa perlu library gambar). */
    private function gambarSvg(string $kode): string
    {
        $lebar = 176;
        $tinggi = 58;

        $bg = sprintf('#%02X%02X%02X',
            random_int(240, 247), random_int(244, 250), random_int(250, 253));

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$lebar.'" height="'.$tinggi
            .'" viewBox="0 0 '.$lebar.' '.$tinggi.'">';
        $svg .= '<rect width="100%" height="100%" fill="'.$bg.'" rx="10"/>';

        // Noise titik.
        for ($i = 0; $i < 30; $i++) {
            $svg .= '<circle cx="'.random_int(0, $lebar).'" cy="'.random_int(0, $tinggi)
                .'" r="'.(random_int(0, 1) ? '1' : '1.6').'" fill="'. $this->warnaAcak(160, 225).'" opacity="0.7"/>';
        }

        // Garis acak.
        for ($i = 0; $i < 6; $i++) {
            $svg .= '<line x1="'.random_int(0, $lebar).'" y1="'.random_int(0, $tinggi)
                .'" x2="'.random_int(0, $lebar).'" y2="'.random_int(0, $tinggi)
                .'" stroke="'.$this->warnaAcak(170, 230).'" stroke-width="'.(random_int(8, 20) / 10)
                .'" opacity="0.55"/>';
        }

        // Huruf per karakter, dirotasi acak.
        $x = 14;
        foreach (str_split($kode) as $ch) {
            $fs = random_int(30, 38);
            $y = random_int($tinggi - 12, $tinggi - 8);
            $rot = random_int(-26, 26);
            $fam = ['monospace', 'Arial, sans-serif', 'Georgia, serif', 'Courier New, monospace']
                [random_int(0, 3)];
            $bold = random_int(0, 1) ? ' font-weight="bold"' : '';
            $tebal = random_int(1, 2) === 1 ? ' stroke="'.$this->warnaAcak(200, 250).'" stroke-width="0.8"' : '';
            $svg .= '<text x="'.$x.'" y="'.$y.'" font-size="'.$fs.'"'.$bold.' font-family="'.$fam.'"'
                .' fill="'.$this->warnaAcak(20, 120).'"'.$tebal
                .' transform="rotate('.$rot.' '.$x.' '.($y - 6).')">'.$ch.'</text>';
            $x += random_int(27, 34);
        }

        $svg .= '</svg>';

        return $svg;
    }
}
