<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\LoginAttempt;
use App\Models\Profesi;
use App\Models\SubUnit;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\StrukturService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class AuthController extends Controller
{
    private const MAX_FAIL = 5;

    private const WINDOW_MINUTE = 15;

    private const TOKEN_EXP_DAYS = 7;

    public function login(Request $req): JsonResponse
    {

        $email = $req->input('email');
        $password = $req->input('password');
        $ip = $req->ip();

        if ($email === '' || $password === '') {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Email dan password harus diisi.',

            ], 422);
        }

        // rate limiting
        $sisa = $this->sisaBlokir($email, $ip);
        if ($sisa > 0) {
            return response()->json([
                'sukses' => false,
                'pesan' => "Terlalu banyak gagal. Coba lagi dalam {$sisa} menit.",
            ], 429);
        }

        $user = User::where('email', $email)->where('role', '!=', 'admin')->first();
        if (! $user || ! password_verify($password, $user->password_hash)) {
            $this->catatPercobaan($email, $ip, false);
            $tersisa = $this->sisaPercobaan($email, $ip);
            $msg = 'Email atau password tidak valid';
            if ($tersisa <= 2) {
                $msg .= " Sisa {$tersisa} percobaan sebelum akses ditunda.";
            }

            return response()->json(['sukses' => false, 'pesan' => $msg], 401);
        }
        if ($user->status !== 'aktif') {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Akun Anda dinonaktifkan. Hubungi administrator.',
            ], 403);
        }
        $this->catatPercobaan($email, $ip, true);
        LoginAttempt::where('email', $email)->where('sukses', 0)->delete();

        $token = bin2hex(random_bytes(32));
        $expiresAt = Carbon::now()->addDays(self::TOKEN_EXP_DAYS);
        ApiToken::create([
            'user_id' => $user->id,
            'token' => $token,
            'expires_at' => $expiresAt,
        ]);

        $user->load(['unitKerja', 'subUnit', 'profesi', 'jabatan']);
        catat_aktivitas('Login Mobile', $user->nama_lengkap.' masuk dari aplikasi mobile');

        $menit = self::TOKEN_EXP_DAYS * 24 * 60;
        $cookie = Cookie::make('auth_token', $token, $menit, '/', null, false, true, false, 'Lax');

        return response()->json([
            'sukses' => true,
            'user' => $user,
            'lokasi' => [
                'lat' => (float) pengaturan('lokasi_lat', -8.4991120),
                'lng' => (float) pengaturan('lokasi_lng', 140.4049840),
                'radius' => (float) pengaturan('radius_meter', 100),
            ],
        ])->withCookie($cookie);

    }

    public function registerDataMaster(Request $req): JsonResponse
    {
        $struktur = app(StrukturService::class);

        $sub = [];
        foreach (SubUnit::orderBy('unit_kerja_id')->orderBy('id')->get() as $s) {
            $sub[(int) $s->unit_kerja_id][] = ['id' => (int) $s->id, 'nama' => $s->nama];
        }

        $unitList = UnitKerja::orderBy('id')->get()
            ->map(fn ($u) => [
                'id' => (int) $u->id,
                'nama' => $u->nama,
                'punya_sub' => (bool) $u->punya_sub,
            ])
            ->values();

        $profList = Profesi::orderBy('id')->get()
            ->map(fn ($p) => ['id' => (int) $p->id, 'nama' => $p->nama])
            ->values();

        return response()->json([
            'sukses' => true,
            'unit' => $unitList,
            'sub' => $sub,
            'profesi' => $profList,
            'jabatan' => $struktur->pilihan(),
            'kategori_jabatan' => kategori_jabatan_list(),
            'posisi' => posisi_list(),
            'seksi_pembina' => array_merge(
                $struktur->pilihan()['Kepala Seksi'] ?? [],
                $struktur->pilihan()['Kepala Sub Bagian'] ?? []
            ),
        ]);
    }

    public function register(Request $req, StrukturService $struktur): JsonResponse
    {
        $d = [
            'nama_lengkap' => trim((string) $req->input('nama_lengkap')),
            'tempat_lahir' => trim((string) $req->input('tempat_lahir')),
            'tanggal_lahir' => $req->input('tanggal_lahir') ?: null,
            'jenis_kelamin' => (string) $req->input('jenis_kelamin'),
            'agama' => (string) $req->input('agama'),
            'email' => trim((string) $req->input('email')),
            'no_hp' => trim((string) $req->input('no_hp')),
            'nip' => trim((string) $req->input('nip')) ?: null,
            'unit_kerja_id' => (int) $req->input('unit_kerja_id') ?: null,
            'sub_unit_id' => (int) $req->input('sub_unit_id') ?: null,
            'profesi_id' => (int) $req->input('profesi_id') ?: null,
        ];
        $pass = (string) $req->input('password');
        $pass2 = (string) $req->input('password2');

        $galat = [];
        if ($d['nama_lengkap'] === '') {
            $galat[] = 'Nama lengkap wajib diisi.';
        }
        if (! filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            $galat[] = 'Email tidak valid.';
        }
        if (! in_array($d['jenis_kelamin'], ['Laki-Laki', 'Perempuan'], true)) {
            $galat[] = 'Jenis kelamin wajib dipilih.';
        }
        if (! in_array($d['agama'], ['Katolik', 'Kristen', 'Islam', 'Hindu', 'Budha', 'Lainnya'], true)) {
            $galat[] = 'Agama wajib dipilih.';
        }
        $tl = $d['tanggal_lahir'];
        if ($tl !== null && (
            ! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $tl)
            || ! checkdate((int) substr((string) $tl, 5, 2), (int) substr((string) $tl, 8, 2), (int) substr((string) $tl, 0, 4))
        )) {
            $galat[] = 'Format tanggal lahir tidak valid. Gunakan format YYYY-MM-DD.';
        }
        if (strlen($pass) < 6) {
            $galat[] = 'Password minimal 6 karakter.';
        }
        if ($pass !== $pass2) {
            $galat[] = 'Konfirmasi password tidak sama.';
        }

        if (User::where('email', $d['email'])->count() > 0) {
            $galat[] = 'Email sudah terdaftar. Gunakan email lain atau masuk.';
        }

        $unit = $d['unit_kerja_id']
            ? UnitKerja::where('id', $d['unit_kerja_id'])->first()
            : null;
        if (! $unit) {
            $galat[] = 'Tempat kerja wajib dipilih.';
        } elseif ($unit->punya_sub) {
            $sah = $d['sub_unit_id'] && SubUnit::where('id', $d['sub_unit_id'])
                ->where('unit_kerja_id', $d['unit_kerja_id'])
                ->count() > 0;
            if (! $sah) {
                $galat[] = 'Sub unit wajib dipilih untuk '.$unit->nama.'.';
            }
        } else {
            $d['sub_unit_id'] = null;
        }
        if (! $d['profesi_id'] || Profesi::where('id', $d['profesi_id'])->count() === 0) {
            $galat[] = 'Profesi wajib dipilih.';
        }

        [$kategoriJab, $jabatanId, $galatJab] = $struktur->resolusi(
            (string) $req->input('jabatan_kategori'),
            (int) $req->input('jabatan_id')
        );
        if ($galatJab !== '') {
            $galat[] = $galatJab;
        }
        $d['jabatan_kategori'] = $kategoriJab;
        $d['jabatan_id'] = $jabatanId;

        $statusPegawai = (string) $req->input('status_pegawai') === 'PNS' ? 'PNS' : 'Non-PNS';
        [$posisi, $seksiPembinaId, $galatPosisi] = $struktur->resolusiPosisi(
            (string) $req->input('posisi'),
            $kategoriJab,
            $jabatanId,
            (int) $req->input('seksi_pembina_id') ?: null
        );
        if ($galatPosisi !== '') {
            $galat[] = $galatPosisi;
        }
        $d['posisi'] = $posisi;
        $d['status_pegawai'] = $statusPegawai;
        $d['seksi_pembina_id'] = $seksiPembinaId;

        if ($galat) {
            return response()->json([
                'sukses' => false,
                'pesan' => implode(' ', $galat),
            ], 422);
        }

        User::insert($d + [
            'password_hash' => bcrypt($pass),
            'role' => 'pegawai',
            'status' => 'nonaktif',
            'created_at' => now(),
        ]);

        catat_aktivitas('Pendaftaran', $d['nama_lengkap'].' mendaftarkan akun pegawai baru');

        return response()->json([
            'sukses' => true,
            'pesan' => 'Pendaftaran berhasil. Silakan masuk dengan email dan password Anda.',
        ], 201);
    }

    public function me(Request $req): JsonResponse
    {
        $user = $req->get('user');
        $user->load(['unitKerja', 'subUnit', 'profesi', 'jabatan']);
        $user->append('shift');

        return response()->json([
            'sukses' => true,
            'user' => $user,
            'lokasi' => [
                'lat' => (float) pengaturan('lokasi_lat', -8.4991120),
                'lng' => (float) pengaturan('lokasi_lng', 140.4049840),
                'radius' => (float) pengaturan('radius_meter', 100),
            ],
        ]);
    }

    public function logout(Request $req): JsonResponse
    {
        $token = $req->cookie('auth_token');
        ApiToken::where('token', $token)->delete();

        catat_aktivitas('Logout Mobile', $req->get('user')->nama_lengkap.'logout dari aplikasi mobile');

        return response()->json([

            'sukses' => true,
            'pesan' => 'Berhasil logout',
        ])->withCookie((
            Cookie::forget('auth_token', '/', null)
        ));

    }

    private function catatPercobaan(string $email, string $ip, bool $sukses): void
    {
        LoginAttempt::create([
            'email' => mb_substr($email, 0, 150),
            'ip' => $ip,
            'sukses' => $sukses ? 1 : 0,
            'waktu' => now(),
        ]);
        LoginAttempt::where('waktu', '<', now()->subDays(2))->delete();
    }

    private function jumlahGagal(string $email, string $ip): int
    {
        $sejak = now()->subMinutes(self::WINDOW_MINUTE);

        return LoginAttempt::where('sukses', 0)
            ->where('waktu', '>=', $sejak)
            ->where(function ($q) use ($email, $ip) {
                $q->where('email', $email)
                    ->orWhere('ip', $ip);
            })
            ->count();
    }

    private function sisaPercobaan(string $email, string $ip): int
    {
        return max(0, self::MAX_FAIL - $this->jumlahGagal($email, $ip));
    }

    private function sisaBlokir(string $email, string $ip): int
    {
        if ($this->jumlahGagal($email, $ip) < self::MAX_FAIL) {
            return 0;
        }
        $sejak = now()->subMinutes(self::WINDOW_MINUTE);
        $terbaru = LoginAttempt::where('sukses', 0)
            ->where('waktu', '>=', $sejak)
            ->where(function ($q) use ($email, $ip) {
                $q->where('email', $email)->orWhere('ip', $ip);
            })->orderBy('waktu', 'desc')->first();
        $habis = strtotime((string) ($terbaru->waktu ?? 'now')) + self::WINDOW_MINUTE * 60;

        return max(1,(int) ceil(($habis - time()) / 60));
    }
}
