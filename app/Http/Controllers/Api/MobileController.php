<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Absensi;
use App\Models\ApiToken;
use App\Models\HariLibur;
use App\Models\Izin;
use App\Models\IzinPersetujuan;
use App\Models\JadwalShift;
use App\Models\LoginAttempt;
use App\Models\Logbook;
use App\Models\MappingSIMRSAccount;
use App\Models\Profesi;
use App\Models\Shift;
use App\Models\SubUnit;
use App\Models\TemplateLogbook;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\AbsenService;
use App\Services\AlurIzinService;
use App\Services\AnomaliService;
use App\Services\BintangService;
use App\Services\CutiService;
use App\Services\RekapService;
use App\Services\SimrsService;
use App\Services\StrukturService;
use Carbon\Carbon;
use DateTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;

/**
 * @deprecated Controller lama yang dipertahankan sebagai referensi.
 *             Endpoint kini ditangani AuthController, AbsenController,
 *             JadwalController, RekapController, IzinController, dan LogbookController.
 */
class MobileController extends Controller
{
    private const MAX_FAIL = 5;
    private const WINDOW_MINUTE = 15;
    private const TOKEN_EXP_DAYS = 7;
    private const JENIS = ['Izin', 'Sakit', 'Cuti', 'Dinas Luar'];
    private const BERJENJANG = ['Izin', 'Cuti'];
    public function __construct(
        private AbsenService $absenService
    ){}
    public function login(Request $req):JsonResponse{

       
        
        $email = $req->input('email');
        $password = $req->input('password');
        $ip = $req->ip();

        if($email === '' || $password === ''){
            return response()->json([
                "sukses"=>false,
                "pesan"=>"Email dan password harus diisi."

            ],422);
        }

        // rate limiting
        $sisa = $this->sisaBlokir($email,$ip);
        if($sisa>0){
             return response()->json([
                "sukses"=>false,
                "pesan"=>"Terlalu banyak gagal. Coba lagi dalam {$sisa} menit."
            ],429);
        }

        $user = User::where('email',$email)->where('role','!=','admin')->first();
        if(!$user || !password_verify($password,$user->password_hash)){
            $this->catatPercobaan($email, $ip, false);
            $tersisa = $this->sisaPercobaan($email,$ip);
            $msg = "Email atau password tidak valid";
            if($tersisa <= 2)$msg .= " Sisa {$tersisa} percobaan sebelum akses ditunda.";
            return response()->json(['sukses' => false, 'pesan' => $msg], 401);
        }
        if($user->status !=='aktif'){
            return response()->json([
                "sukses"=>false,
                "pesan"=>"Akun Anda dinonaktifkan. Hubungi administrator."
            ],403);
        }
        $this->catatPercobaan($email,$ip,true);
        LoginAttempt::where('email', $email)->where('sukses', 0)->delete();

        $token = bin2hex(random_bytes(32));
        $expiresAt = Carbon::now()->addDays(self::TOKEN_EXP_DAYS);
        ApiToken::create([
            "user_id"=>$user->id,
            "token"=>$token,
            "expires_at"=>$expiresAt
        ]);

        $user->load(['unitKerja', 'subUnit', 'profesi', 'shift', 'jabatan']);
        catat_aktivitas('Login Mobile', $user->nama_lengkap . ' masuk dari aplikasi mobile');

        $menit =  self::TOKEN_EXP_DAYS * 24 * 60;
        $cookie = Cookie::make("auth_token",$token,$menit,"/",null,false,true,false,'Lax');

        return response()->json([
            'sukses'     => true,
            'user'       => $user,
            'lokasi' => [
                        'lat'    => (float) pengaturan('lokasi_lat', -8.4991120),
                        'lng'    => (float) pengaturan('lokasi_lng', 140.4049840),
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
                'id'        => (int) $u->id,
                'nama'      => $u->nama,
                'punya_sub' => (bool) $u->punya_sub,
            ])
            ->values();

        $profList = Profesi::orderBy('id')->get()
            ->map(fn ($p) => ['id' => (int) $p->id, 'nama' => $p->nama])
            ->values();

        return response()->json([
            'sukses'           => true,
            'unit'             => $unitList,
            'sub'              => $sub,
            'profesi'          => $profList,
            'jabatan'          => $struktur->pilihan(),
            'kategori_jabatan' => kategori_jabatan_list(),
            'posisi'           => posisi_list(),
            'seksi_pembina'    => array_merge(
                $struktur->pilihan()['Kepala Seksi'] ?? [],
                $struktur->pilihan()['Kepala Sub Bagian'] ?? []
            ),
        ]);
    }

    public function register(Request $req, StrukturService $struktur): JsonResponse
    {
        $d = [
            'nama_lengkap'  => trim((string) $req->input('nama_lengkap')),
            'tempat_lahir'  => trim((string) $req->input('tempat_lahir')),
            'tanggal_lahir' => $req->input('tanggal_lahir') ?: null,
            'jenis_kelamin' => (string) $req->input('jenis_kelamin'),
            'agama'         => (string) $req->input('agama'),
            'email'         => trim((string) $req->input('email')),
            'no_hp'         => trim((string) $req->input('no_hp')),
            'nip'           => trim((string) $req->input('nip')) ?: null,
            'unit_kerja_id' => (int) $req->input('unit_kerja_id') ?: null,
            'sub_unit_id'   => (int) $req->input('sub_unit_id') ?: null,
            'profesi_id'    => (int) $req->input('profesi_id') ?: null,
        ];
        $pass  = (string) $req->input('password');
        $pass2 = (string) $req->input('password2');

        $galat = [];
        if ($d['nama_lengkap'] === '') $galat[] = 'Nama lengkap wajib diisi.';
        if (! filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $galat[] = 'Email tidak valid.';
        if (! in_array($d['jenis_kelamin'], ['Laki-Laki', 'Perempuan'], true)) $galat[] = 'Jenis kelamin wajib dipilih.';
        if (! in_array($d['agama'], ['Katolik', 'Kristen', 'Islam', 'Hindu', 'Budha', 'Lainnya'], true)) $galat[] = 'Agama wajib dipilih.';
        $tl = $d["tanggal_lahir"];
        if($tl !== null && (
            ! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $tl)
            || ! checkdate((int) substr((string) $tl, 5, 2), (int) substr((string) $tl, 8, 2), (int) substr((string) $tl, 0, 4))
        )){
            $galat[] = 'Format tanggal lahir tidak valid. Gunakan format YYYY-MM-DD.';
        }
        if (strlen($pass) < 6) $galat[] = 'Password minimal 6 karakter.';
        if ($pass !== $pass2) $galat[] = 'Konfirmasi password tidak sama.';

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
            if (! $sah) $galat[] = 'Sub unit wajib dipilih untuk ' . $unit->nama . '.';
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
        if ($galatJab !== '') $galat[] = $galatJab;
        $d['jabatan_kategori'] = $kategoriJab;
        $d['jabatan_id']       = $jabatanId;

        $statusPegawai = (string) $req->input('status_pegawai') === 'PNS' ? 'PNS' : 'Non-PNS';
        [$posisi, $seksiPembinaId, $galatPosisi] = $struktur->resolusiPosisi(
            (string) $req->input('posisi'),
            $kategoriJab,
            $jabatanId,
            (int) $req->input('seksi_pembina_id') ?: null
        );
        if ($galatPosisi !== '') $galat[] = $galatPosisi;
        $d['posisi']           = $posisi;
        $d['status_pegawai']   = $statusPegawai;
        $d['seksi_pembina_id'] = $seksiPembinaId;

        if ($galat) {
            return response()->json([
                'sukses' => false,
                'pesan'  => implode(' ', $galat),
            ], 422);
        }

        User::insert($d + [
            'password_hash' => bcrypt($pass),
            'role'          => 'pegawai',
            'status'        => 'nonaktif',
            'created_at'    => now(),
        ]);

        catat_aktivitas('Pendaftaran', $d['nama_lengkap'] . ' mendaftarkan akun pegawai baru');

        return response()->json([
            'sukses' => true,
            'pesan'  => 'Pendaftaran berhasil. Silakan masuk dengan email dan password Anda.',
        ], 201);
    }

    public function me(Request $req):JsonResponse{
        $user = $req->get('user');
        $user->load(['unitKerja','subUnit','profesi','shift','jabatan']);
        return response()->json([
            "sukses"=>true,
            "user"=>$user,
            "lokasi"=>[
                'lat'    => (float) pengaturan('lokasi_lat', -8.4991120),
                'lng'    => (float) pengaturan('lokasi_lng', 140.4049840),
                'radius' => (float) pengaturan('radius_meter', 100),
            ]
        ]);
    }

    public function getIzinMenungguTotal(Request $req):JsonResponse{
        $user = $req->get('user');
        $total = $this->tugasSaya($user)->count();
        return response()->json([
            "sukses"=>true,
            "total"=>$total
        ]);
    }

    public function getDetailIzinMenunggu(Request $req):JsonResponse{
        $user = $req->get('user');
        $daftar = $this->tugasSaya($user);
        return response()->json([
            "sukses"=>true,
            "izin"=>$daftar
        ]);
    }

    private function tugasSaya(User $user): \Illuminate\Support\Collection
    {
        $lib = app(AlurIzinService::class);

        $kandidat = Izin::with([
                'user:id,nama_lengkap,nip,unit_kerja_id,sub_unit_id,jabatan_id,seksi_pembina_id,posisi',
                'user.unitKerja:id,nama',
                'user.subUnit:id,nama',
            ])
            ->where('status', 'Menunggu')
            ->where('tahap_aktif', '>', 0)
            ->orderBy('tahap_aktif')
            ->orderBy('id')
            ->get();

        return $kandidat->filter(function ($r) use ($lib, $user) {
            $pemohon = [
                'id' => $r->user_id, 'posisi' => $r->user->posisi,
                'jabatan_id' => $r->user->jabatan_id, 'seksi_pembina_id' => $r->user->seksi_pembina_id,
                'unit_kerja_id' => $r->user->unit_kerja_id, 'sub_unit_id' => $r->user->sub_unit_id,
            ];
            return $lib->bolehBertindak(
                ['id' => $r->id, 'tahap_aktif' => $r->tahap_aktif],
                $pemohon, $user
            );
        })->values();
    }

    public function getRiwayatPersetujuan(Request $req): JsonResponse
    {
        $user = $req->get('user');
        $riwayat = IzinPersetujuan::with(['pengajuan' => function ($q) {
                $q->select('id', 'user_id', 'jenis', 'jenis_cuti', 'tanggal_mulai', 'tanggal_selesai')
                  ->with(['user' => function ($q2) {
                      $q2->select('id', 'nama_lengkap');
                  }]);
            }])
            ->where('oleh_user_id', $user->id)
            ->orderBy('waktu', 'DESC')
            ->limit(30)
            ->get()
            ->map(fn ($r) => [
                'id'            => $r->id,
                'waktu'         => $r->waktu?->toISOString(),
                'catatan'       => $r->catatan,
                'status'        => $r->status,
                'pengajuan'     => [
                    'id'            => $r->pengajuan->id,
                    'jenis'         => $r->pengajuan->jenis,
                    'jenis_cuti'    => $r->pengajuan->jenis_cuti,
                    'tanggal_mulai' => $r->pengajuan->tanggal_mulai->format('Y-m-d'),
                    'tanggal_selesai'=> $r->pengajuan->tanggal_selesai->format('Y-m-d'),
                    'nama_pemohon'  => $r->pengajuan->user->nama_lengkap,
                ],
            ]);

        return response()->json([
            'sukses'   => true,
            'riwayat'  => $riwayat,
        ]);
    }

    public function logout(Request $req):JsonResponse{
        $token = $req->cookie('auth_token');
        ApiToken::where('token',$token)->delete();

        catat_aktivitas('Logout Mobile', $req->get('user')->nama_lengkap.'logout dari aplikasi mobile');
        return response()->json([

            "sukses"=>true,
            "pesan"=>"Berhasil logout"
        ])->withCookie((
            Cookie::forget('auth_token','/',null)
        ));
        
    }

    // absensi
    public function absen(Request $req):JsonResponse{
        $user = $req->get('user');
        $tipe = $req->input('tipe');
        $lat = (float) $req->input('lat');
        $lng = (float) $req->input('lng');
        $akurasi = $req->has('akurasi')?round((float) $req->input(
            'akurasi'
        ),2):null;
        $foto = $req->input('foto');
        if(!in_array($tipe, ['datang', 'pulang']) || !$lat || !$lng){
            return response()->json([
                'sukses'=>false,
                'pesan'=>'data tidak lengkap!!!'
            ],422);
        }

        $user->load(['unitKerja','subUnit','profesi','shift','jabatan']);
        
        $u = [
            'id' => $user->id,
            'shift_id' => $user->shift_id,
            'shift_kategori' => $user->shift->kategori ?? null,
            'shift_jam_masuk' => $user->shift->jam_masuk?->format('H:i'),
            'shift_jam_pulang' => $user->shift->jam_pulang->format('H:i'),
            'role' => $user->role,
        ];

        $wajibSelfie = pengaturan('wajib_selfie','1')==='1';
        $fileFoto = null;
        if($foto){
            $fileFoto = $this->absenService->simpanSelfie($user->id, $tipe, $foto);
            if($fileFoto === null && $wajibSelfie){
                return response()->json([
                    "sukses"=>false,
                    "pesan"=>"Foto selfie tidak valid!!!"
                ]);
            }
        }elseif($wajibSelfie){
            return response()->json([
                'sukses' => false, 
                'pesan' => 'Foto selfie wajib disertakan'
            ]);
        }

        $rsLat = (float) pengaturan('lokasi_lat',0);
        $rsLng = (float) pengaturan('lokasi_lng',0);
        $radius = (float) pengaturan('radius_meter',100);

        if($rsLat === 0.0 && $rsLng === 0.0){
            return response()->json([
                "sukses"=>false,
                "pesan"=>"Titik lokasi RSUD blum diatur"
            ]);
        }

        $jarak = hitung_jarak($lat,$lng, $rsLat, $rsLng);
        $now = new DateTime();

        if($jarak > $radius){
            $this->absenService->catatLog($user->id,null,$tipe,$lat,$lng,$akurasi,$jarak, $now,true);
            return response()->json([
                'sukses' => false,
                'pesan' => 'Absensi ditolak. Anda berada di luar area RSUD Merauke.',
                'keterangan' => 'Jarak ' . number_format($jarak, 0, ',', '.') . ' m (maks ' . number_format($radius, 0, ',', '.') . ' m)',
            ]);
        }

        [$flagAnomali, $alasanAnomali] = app(AnomaliService::class)->periksa($user->id,$lat,$lng,$akurasi);
        return $tipe === 'datang' ?
            $this->absenService->absenDatang($u,$lat,$lng,$akurasi,$jarak,$now,$fileFoto,$flagAnomali, $alasanAnomali)
            :$this->absenService->absenPulang($u, $lat, $lng, $akurasi, $jarak, $now, $fileFoto, $flagAnomali, $alasanAnomali);
    }
    
    public function getTodayIzin(Request $req):JsonResponse{
        $u = $req->get('user');
        $izin = Izin::where('user_id',$u->id)->where('status','Disetujui')->whereDate('tanggal_mulai',Carbon::today())->first();
        if(!$izin){
            return response()->json([
                "sukses"=> true,
                "hasLeave"=> false,
                "izin"=> null
            ]);
        }
        return response()->json([
                "sukses"=> true,
                "hasLeave"=> true,
                "izin"=> $izin
        ]);
    }

    public function status(Request $req):JsonResponse{
        $user = $req->get('user');
        $absen = Absensi::where('user_id',$user->id)
            ->where('tanggal',now()->toDateString())
            ->first();
        return response()->json([
            "sukses" => true,
            "absen_masuk" => $absen ? [
                "waktu" => substr($absen->waktu_masuk, 11, 5),
                "status" => $absen->status_masuk,
                "menit_terlambat" => (int) $absen->menit_terlambat,
                "bintang" => $absen->bintang_masuk,
            ] : null,
            "absen_pulang" => $absen?->waktu_pulang ? [
                "waktu" => substr($absen->waktu_pulang, 11, 5),
                "status" => $absen->status_pulang,
                "menit_awal" => (int) $absen->menit_awal_pulang,
                "bintang" => $absen->bintang_pulang,
            ] : null,
            "bintang_harian" => $absen?->bintang_harian,
        ]);
    }

    public function riwayatAbsensi(Request $req):JsonResponse{
        $user= $req->get('user');
        $records = Absensi::where('user_id',$user->id)
            ->orderByDesc('tanggal')->limit(7)->get()
            ->map(fn($a)=>[
                "tanggal"=>$a->tanggal->format('Y-m-d'),
                'hari'        => $a->tanggal->translatedFormat('l'),
                'tanggal_label' => $a->tanggal->format('j M Y'),
                'jam_masuk'   => substr($a->waktu_masuk, 11, 5),  // "08:05"
                'jam_pulang'  => $a->waktu_pulang ? substr($a->waktu_pulang, 11, 5) : null,
                'status'      => $a->status_masuk,                 // "Tepat Waktu" / "Terlambat"
                'status_pulang' => $a->status_pulang,
                'menit_terlambat' => (int) $a->menit_terlambat,
                'menit_awal_pulang' => (int) $a->menit_awal_pulang,
                'bintang_masuk' => $a->bintang_masuk,
                'bintang_pulang' => $a->bintang_pulang,
                'bintang_harian' => $a->bintang_harian,
            ]);
        return response()->json([
            "sukses"=>true,
            "riwayat"=>$records
        ]);
    }

    public function statistik(Request $req, RekapService $rekap):JsonResponse{
        $user = $req->get('user');
        $data = $rekap->hitung($user->id, (int) now()->month, (int) now()->year);

        return response()->json([
            "sukses"=>true,
            "kehadiran"=>[
                'persen' => $data['persen'],
                'hadir' => $data['hadir'] + $data['dinas_luar'],
                'target' => $data['hari_efektif'],
            ],
            'jam_kerja' => [
                'total_jam' => round($data['total_menit'] / 60, 1),
                'target_jam' => (float) pengaturan('target_jam_kerja_bulanan', 160),
            ],
            'ketepatan' => [
                'tepat_masuk'  => $data['persen_tepat_masuk'],
                'tepat_pulang' => $data['persen_tepat_pulang'],
            ],
            'bintang_bulanan' => $data['bintang_bulanan'],
        ]);
    }

    public function performaBulan(Request $req, RekapService $rekap): JsonResponse
    {
        $user  = $req->get('user');
        $bulan = min(12, max(1, (int) ($req->query('bulan') ?: now()->subMonth()->month)));
        $tahun = (int) ($req->query('tahun') ?: now()->subMonth()->year);

        if ($bulan > now()->month && $tahun === (int) now()->year) {
            $bulan = now()->month;
        }

        $data    = $rekap->hitung($user->id, $bulan, $tahun);
        $bintang = $data['bintang_bulanan'];
        $servis  = app(BintangService::class);

        return response()->json([
            'sukses'  => true,
            'bulan'   => $bulan,
            'tahun'   => $tahun,
            'nama_bulan' => BULAN_ID[$bulan] ?? $bulan,
            'bintang' => $bintang,
            'pesan'   => $bintang === null ? null : $servis->pesanBulanan((float) $bintang),
        ]);
    }

    public function rekapKeterlambatan(Request $req): JsonResponse
    {
        $user  = $req->get('user');
        $bulan = (int) ($req->query('bulan') ?: now()->month);
        $tahun = (int) ($req->query('tahun') ?: now()->year);

        if ($bulan < 1 || $bulan > 12 || $tahun < 2000 || $tahun > (int) now()->year + 1) {
            return response()->json(['sukses' => false, 'pesan' => 'Parameter bulan/tahun tidak valid.'], 422);
        }

        $rows = DB::table('keterlambatan as k')
            ->join('absensi as a', 'a.id', '=', 'k.absensi_id')
            ->where('a.user_id', $user->id)
            ->whereYear('a.tanggal', $tahun)
            ->whereMonth('a.tanggal', $bulan)
            ->orderBy('a.tanggal')
            ->get([
                'a.tanggal',
                'a.waktu_masuk',
                'a.waktu_pulang',
                'k.menit_telat',
                'k.bintang_masuk',
                'k.menit_awal_pulang',
                'k.bintang_pulang',
                'k.total_bintang',
            ]);

        $detail = $rows->map(fn ($r) => [
            'tanggal'    => substr((string) $r->tanggal, 0, 10),
            'hari'       => Carbon::parse($r->tanggal)->locale('id')->translatedFormat('l'),
            'jam_masuk'  => $r->waktu_masuk ? substr((string) $r->waktu_masuk, 11, 5) : null,
            'jam_pulang' => $r->waktu_pulang ? substr((string) $r->waktu_pulang, 11, 5) : null,
            'menit_telat' => (int) $r->menit_telat,
            'bintang_masuk' => $r->bintang_masuk !== null ? (int) $r->bintang_masuk : null,
            'menit_pulang_awal' => (int) $r->menit_awal_pulang,
            'bintang_pulang' => $r->bintang_pulang !== null ? (int) $r->bintang_pulang : null,
            'total_bintang' => $r->total_bintang !== null ? (float) $r->total_bintang : null,
        ]);

        $terlambat = $detail->where('menit_telat', '>', 0);
        $pulangAwal = $detail->where('menit_pulang_awal', '>', 0);

        return response()->json([
            'sukses' => true,
            'periode' => [
                'bulan' => $bulan,
                'tahun' => $tahun,
                'label' => (BULAN_ID[$bulan] ?? $bulan).' '.$tahun,
            ],
            'ringkasan' => [
                'tercatat'   => $detail->count(),
                'terlambat'  => $terlambat->count(),
                'total_menit_telat' => $detail->sum('menit_telat'),
                'rata_menit_telat'  => $terlambat->isNotEmpty() ? round($terlambat->avg('menit_telat'), 1) : 0.0,
                'terlama_menit_telat' => $terlambat->max('menit_telat') ?? 0,
                'pulang_awal' => $pulangAwal->count(),
                'total_menit_pulang_awal' => $detail->sum('menit_pulang_awal'),
                'rata_bintang_masuk'  => $this->rataKolom($detail, 'bintang_masuk'),
                'rata_bintang_pulang' => $this->rataKolom($detail, 'bintang_pulang'),
                'rata_bintang_total'  => $this->rataKolom($detail, 'total_bintang'),
            ],
            'detail' => $detail->values(),
        ]);
    }

    private function rataKolom($detail, string $kolom): ?float
    {
        $isi = $detail->filter(fn ($d) => $d[$kolom] !== null);

        return $isi->isNotEmpty() ? round($isi->avg($kolom), 1) : null;
    }

    public function pegawaiTeladan(Request $req): JsonResponse
    {
        $bulan = (int) ($req->query('bulan') ?: now()->month);
        $tahun = (int) ($req->query('tahun') ?: now()->year);

        if ($bulan < 1 || $bulan > 12 || $tahun < 2000 || $tahun > (int) now()->year + 1) {
            return response()->json(['sukses' => false, 'pesan' => 'Parameter bulan/tahun tidak valid.'], 422);
        }

        $daftar = DB::table('keterlambatan as k')
            ->join('absensi as a', 'a.id', '=', 'k.absensi_id')
            ->join('users as u', 'u.id', '=', 'a.user_id')
            ->leftJoin('unit_kerja as uk', 'uk.id', '=', 'u.unit_kerja_id')
            ->leftJoin('sub_unit as su', 'su.id', '=', 'u.sub_unit_id')
            ->whereYear('a.tanggal', $tahun)
            ->whereMonth('a.tanggal', $bulan)
            ->where('u.role', '!=', 'admin')
            ->groupBy('u.id', 'u.nama_lengkap', 'uk.nama', 'su.nama')
            ->orderByDesc(DB::raw('COALESCE(SUM(k.total_bintang), 0)'))
            ->orderByDesc(DB::raw('AVG(k.total_bintang)'))
            ->orderBy('u.nama_lengkap')
            ->limit(10)
            ->get([
                'u.id AS pegawai_id',
                'u.nama_lengkap',
                DB::raw('COALESCE(su.nama, uk.nama) AS unit'),
                DB::raw('COUNT(*) AS hari_tercatat'),
                DB::raw('ROUND(COALESCE(SUM(k.total_bintang), 0), 1) AS total_bintang'),
                DB::raw('ROUND(AVG(k.total_bintang), 2) AS rata_bintang'),
                DB::raw('SUM(CASE WHEN k.menit_telat > 0 THEN 1 ELSE 0 END) AS jumlah_telat'),
                DB::raw('SUM(CASE WHEN k.bintang_masuk = 5 OR k.bintang_pulang = 5 THEN 1 ELSE 0 END) AS hari_bintang_lima'),
            ])
            ->values()
            ->map(fn ($r, $i) => [
                'peringkat'   => $i + 1,
                'pegawai_id'  => (int) $r->pegawai_id,
                'nama'        => $r->nama_lengkap,
                'unit'        => $r->unit,
                'hari_tercatat' => (int) $r->hari_tercatat,
                'total_bintang' => (float) $r->total_bintang,
                'rata_bintang'  => (float) $r->rata_bintang,
                'jumlah_telat'  => (int) $r->jumlah_telat,
                'hari_bintang_lima' => (int) $r->hari_bintang_lima,
            ]);

        return response()->json([
            'sukses' => true,
            'periode' => [
                'bulan' => $bulan,
                'tahun' => $tahun,
                'label' => (BULAN_ID[$bulan] ?? $bulan).' '.$tahun,
            ],
            'daftar' => $daftar,
        ]);
    }

    public function jadwal(Request $req): JsonResponse
    {
        $user = $req->get('user');
        $today = now()->toDateString();

        $jadwal = JadwalShift::where('user_id', $user->id)
            ->where('tanggal_berlaku', '<=', $today)
            ->orderByDesc('tanggal_berlaku')
            ->first();

        $shiftId = $jadwal ? (int) $jadwal->shift_id : $user->shift_id;
        $shift = $shiftId ? Shift::find($shiftId) : null;

        return response()->json([
            'sukses' => true,
            'shift' => $shift ? [
                'id'        => $shift->id,
                'kategori'  => $shift->kategori,
                'jam_masuk' => Carbon::parse($shift->jam_masuk)->format('H:i'),
                'jam_pulang'=> Carbon::parse($shift->jam_pulang)->format('H:i'),
            ] : null,
            'izinkan_pilih' => pengaturan('izinkan_pilih_shift', '1') === '1',
        ]);
    }

    public function riwayatIzin(Request $req):JsonResponse{
        $u = $req->get('user');
        $izin = Izin::with(['diprosesOleh:id,nama_lengkap'])
            ->where('user_id',$u->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn ($i) => [
                'id'            => $i->id,
                'jenis'         => $i->jenis,
                'jenis_cuti'    => $i->jenis_cuti,
                'tanggal_mulai' => $i->tanggal_mulai->format('Y-m-d'),
                'tanggal_selesai'=> $i->tanggal_selesai->format('Y-m-d'),
                'lama_hari'     => $i->lama_hari,
                'keterangan'    => $i->keterangan,
                'alamat_izin'   => $i->alamat_izin,
                'lampiran'      => $i->lampiran,
                'status'        => $i->status,
                'tahap_aktif'   => $i->tahap_aktif,
                'nomor_surat'   => $i->nomor_surat,
                'ttd_digital'   => $i->ttd_digital,
                'created_at'    => $i->created_at?->toISOString(),
                'persetujuan'   => $i->persetujuan->map(fn ($p) => [
                    'tahap'       => $p->tahap,
                    'posisi_tahap'=> $p->posisi_tahap,
                    'status'      => $p->status,
                    'oleh_nama'   => $p->user?->nama_lengkap,
                    'waktu'       => $p->waktu?->toISOString(),
                ]),
            ]);
        if($izin->isEmpty()){
            return response()->json([
                "sukses"=>true,
                "message"=>"belum ada pengajuan izin"
            ]);
        }

        return response()->json([
            "sukses"=>true,
            "izin"=>$izin,
        ]);
    }

    public function pengajuanIzin(Request $req) : JsonResponse{
        $u = $req->get('user');
        $jenis     = (string) $req->get('jenis_pengajuan');
        $jenisCuti = trim((string) $req->get('jenis_cuti')) ?: null;
        $mulai     = (string) $req->get('tanggal_mulai');
        $selesai   = (string) ($req->get('tanggal_selesai') ?: '') ?: $mulai;
        $alamat    = trim((string) $req->input('alamat')) ?: null;
        $alasan    = trim((string) $req->get('alasan'));
        $berjenjang = in_array($jenis, ['Izin', 'Cuti'], true);

        $galat = [];
        $validJenis = ['Izin', 'Sakit', 'Cuti', 'Dinas Luar'];
        if (! in_array($jenis, $validJenis, true)) $galat[] = 'Jenis pengajuan tidak valid.';
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $mulai)) $galat[] = 'Tanggal mulai wajib diisi.';
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $selesai)) $selesai = $mulai;
        if ($selesai < $mulai) $galat[] = 'Tanggal selesai tidak boleh sebelum tanggal mulai.';
        if ($alasan === '') $galat[] = 'Alasan/keperluan wajib diisi.';
        if (! $galat && (strtotime($selesai) - strtotime($mulai)) / 86400 > 60) {
            $galat[] = 'Rentang pengajuan maksimal 60 hari.';
        }

        if ($jenis === 'Cuti') {
            if (! is_pns($u)) {
                $galat[] = 'Cuti hanya dapat diajukan oleh pegawai berstatus PNS.';
            }
            if (! in_array($jenisCuti, jenis_cuti_list(), true)) {
                $galat[] = 'Jenis cuti wajib dipilih.';
            }
            if ($alamat === null) {
                $galat[] = 'Alamat selama cuti wajib diisi.';
            }
        }
        if ($jenis === 'Izin' && $alamat === null) {
            $galat[] = 'Alamat selama izin wajib diisi.';
        }

        if (! $galat) {
            $tindih = Izin::where('user_id', $u->id)
                ->whereIn('status', ['Menunggu', 'Disetujui'])
                ->where('tanggal_mulai', '<=', $selesai)->where('tanggal_selesai', '>=', $mulai)
                ->count() > 0;
            if ($tindih) {
                $galat[] = 'Rentang tanggal tersebut bertumpang-tindih dengan pengajuan lain yang masih Menunggu/Disetujui.';
            }
        }

        $lamaHari = null;
        if (! $galat && $berjenjang) {
            pastikan_libur_tetap((int) date('Y', strtotime($mulai)));
            pastikan_libur_tetap((int) date('Y', strtotime($selesai)));
            $liburSet = [];
            foreach (HariLibur::all() as $h) {
                $liburSet[$h->tanggal->format('Y-m-d')] = true;
            }
            $mingguLibur = pengaturan('minggu_libur', '0') === '1';
            $lamaHari = hari_kerja_antara($mulai, $selesai, $liburSet, $mingguLibur);
            if ($lamaHari < 1) $lamaHari = 1;

            $motongKuota = $jenis === 'Izin' || ($jenis === 'Cuti' && $jenisCuti === 'Cuti Tahunan');
            if ($motongKuota && is_pns($u)) {
                $sisa = app(CutiService::class)->rekap($u->id, (int) date('Y', strtotime($mulai)))['sisa'];
                if ($lamaHari > $sisa) {
                    $galat[] = "Sisa hak cuti tahun ini hanya {$sisa} hari kerja, "
                        . "sedangkan pengajuan ini memerlukan {$lamaHari} hari kerja.";
                }
            }
        }

        $lampiran = null;
        $allowedExt = ['jpeg', 'jpg', 'png', 'pdf'];
        if ($req->hasFile('lampiran')) {
            $berkas = $req->file('lampiran');
            $eks = strtolower($berkas->getClientOriginalExtension() ?: '');
            if (! in_array($eks, $allowedExt, true)) {
                $galat[] = 'Lampiran hanya boleh berupa JPG, PNG, atau PDF.';
            } elseif ($berkas->getSize() > 3 * 1024 * 1024) {
                $galat[] = 'Ukuran lampiran maksimal 3 MB.';
            } else {
                $dir = 'izin/' . now()->format('Ym');
                $nama = $u->id . '_' . now()->format('Ymd_His') . '_' . bin2hex(random_bytes(3))
                    . '.' . ($eks === 'jpeg' ? 'jpg' : $eks);
                $lampiran = $berkas->storeAs($dir, $nama, 'public');
            }
        }

        if ($galat) {
            return response()->json(["sukses" => false, "pesan" => implode(' ', $galat)]);
        }

        

        if ($berjenjang) {
            $tahapAktif = 0;
            $statusAwal = 'Menunggu';
            $nomorSurat = null;
            $kodeVerifikasi = null;
            $processedAt = null;

            $izinId=null;
            DB::transaction(function () use ($u, $jenis, $jenisCuti, $mulai, $selesai, $lamaHari, $alamat, $alasan, $lampiran, &$tahapAktif, &$statusAwal, &$nomorSurat, &$kodeVerifikasi, &$processedAt, &$izinId) {
                $izin = Izin::create([
                    'user_id'         => $u->id,
                    'jenis'           => $jenis,
                    'jenis_cuti'      => $jenis === 'Cuti' ? $jenisCuti : null,
                    'tanggal_mulai'   => $mulai,
                    'tanggal_selesai' => $selesai,
                    'lama_hari'       => $lamaHari,
                    'alamat_izin'     => $alamat,
                    'keterangan'      => $alasan,
                    'lampiran'        => $lampiran,
                    'status'          => 'Menunggu',
                    'tahap_aktif'     => 0,
                    'created_at'      => now(),
                ]);
                $izinId = $izin->id;
                [$tahapAktif, $statusAwal] = app(AlurIzinService::class)->mulai($izinId, $u->toArray());
                $update = ['tahap_aktif' => $tahapAktif, 'status' => $statusAwal];
                if ($statusAwal === 'Disetujui') {
                    $processedAt = now();
                    $nomorSurat = sprintf('800/%03d/RSUD-MRK/%02d/%d',
                        Izin::whereNotNull('nomor_surat')
                            ->whereMonth('created_at', now()->format('n'))
                            ->whereYear('created_at', now()->format('Y'))
                            ->count() + 1, now()->format('n'), now()->format('Y'));
                    $kodeVerifikasi = strtoupper(bin2hex(random_bytes(5)));
                    $update['processed_at'] = $processedAt;
                    $update['nomor_surat'] = $nomorSurat;
                    $update['kode_verifikasi'] = $kodeVerifikasi;
                }
                $izin->update($update);
                catat_aktivitas('Pengajuan ' . $jenis, $u->nama_lengkap . ' — ' . ($jenisCuti ?: $jenis)
                    . ' (' . $mulai . ' s.d. ' . $selesai . ", {$lamaHari} hr kerja)");
            });

            $pesan = $statusAwal === 'Disetujui'
                ? "Pengajuan {$jenis} langsung disetujui (posisi Anda berada di puncak alur persetujuan)."
                : 'Pengajuan ' . $jenis . ' terkirim dan menunggu persetujuan '
                    . label_tahap_izin($tahapAktif) . '.';
        }else{

            $izin = Izin::create([
                'user_id'         => $u->id,
                'jenis'           => $jenis,
                'jenis_cuti'      => null,
                'tanggal_mulai'   => $mulai,
                'tanggal_selesai' => $selesai,
                'lama_hari'       => null,
                'alamat_izin'     => $alamat,
                'keterangan'      => $alasan,
                'lampiran'        => $lampiran,
                'status'          => 'Menunggu',
                'tahap_aktif'     => 0,
                'created_at'      => now(),
                ]);
                catat_aktivitas('Pengajuan ' . $jenis, $u->nama_lengkap . ' mengajukan ' . $jenis
                . ' (' . $mulai . ' s.d. ' . $selesai . ')');
                $pesan = 'Pengajuan ' . $jenis . ' terkirim dan menunggu persetujuan admin.';
        } 

        return response()->json([
            "sukses"  => true,
            "pesan"   => $pesan,
            "izin_id" => $izinId
        ]);
        
    }

    public function deleteIzin(Request $req, int $id) : JsonResponse{
        $user = $req->get('user');
        $izin = Izin::where('id', $id)
            ->where('user_id', $user->id)
            ->where('status', 'Menunggu')
            ->first();

        if (!$izin) {
            return response()->json([
                "sukses" => false,
                "pesan" => "Izin tidak ditemukan atau sudah diproses."
            ], 404);
        }

        $izin->delete();

        return response()->json([
            "sukses" => true,
            "pesan" => "Pengajuan izin berhasil dibatalkan."
        ]);
    }

    public function prosesIzinMenunggu(Request $req): JsonResponse
    {
        $user = $req->get('user');
        $id = (int) $req->input('id');
        $putusan = (string) $req->input('putusan');
        $catatan = trim((string) $req->input('catatan')) ?: null;

        if (! in_array($putusan, ['setuju', 'tolak'], true)) {
            return response()->json([
                'sukses' => false, 'pesan' => 'Putusan tidak valid.',
            ], 422);
        }

        $iz = Izin::with('user:id,id,nama_lengkap,unit_kerja_id,sub_unit_id,jabatan_id,seksi_pembina_id,posisi')->find($id);
        if (! $iz || $iz->status !== 'Menunggu' || (int) $iz->tahap_aktif === 0) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Pengajuan tidak ditemukan atau sudah diproses.',
            ], 404);
        }

        $lib = app(AlurIzinService::class);
        $pemohonArr = $iz->user->toArray();
        $izArr = $iz->toArray();

        if (! $lib->bolehBertindak($izArr, $pemohonArr, $user)) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Anda tidak berwenang memutus pengajuan ini.',
            ], 403);
        }

        $hasil = $lib->proses($izArr, $pemohonArr, $user->id, $putusan, $catatan);
        catat_aktivitas('Persetujuan ' . $iz->jenis, $iz->user->nama_lengkap
            . ' — tahap ' . label_tahap_izin((int) $iz->tahap_aktif)
            . ' oleh ' . $user->nama_lengkap . ' → ' . $hasil);

        $pesan = match ($hasil) {
            'Ditolak'   => 'Pengajuan ditolak.',
            'Disetujui' => 'Pengajuan disetujui penuh.',
            default     => 'Persetujuan tercatat, pengajuan diteruskan ke tahap berikutnya.',
        };

        return response()->json(['sukses' => true, 'pesan' => $pesan, 'hasil' => $hasil]);
    }

    // ── Logbook: ambil data tindakan & lab dari SIMRS ──
    public function logbookSimrs(Request $req, SimrsService $simrs, ?string $jenis = null): JsonResponse
    {
        $user   = $req->get('user');
        $dari   = trim((string) $req->query('dari', ''));
        $sampai = trim((string) $req->query('sampai', ''));
        $jenis  = strtolower(trim((string) $jenis));

        if ($jenis !== '' && ! in_array($jenis, ['tindakan', 'lab'], true)) {
            return response()->json([
                'sukses' => false,
                'pesan'  => 'Parameter jenis hanya boleh tindakan atau lab.',
            ], 422);
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dari)) {
            return response()->json([
                'sukses' => false,
                'pesan'  => 'Parameter dari wajib diisi dengan format YYYY-MM-DD.',
            ], 422);
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $sampai)) {
            return response()->json([
                'sukses' => false,
                'pesan'  => 'Parameter sampai wajib diisi dengan format YYYY-MM-DD.',
            ], 422);
        }
        if ($sampai < $dari) {
            return response()->json([
                'sukses' => false,
                'pesan'  => 'Tanggal akhir tidak boleh sebelum tanggal awal.',
            ], 422);
        }

        $mapping = MappingSIMRSAccount::where('user_id', (int) $user->id)->first();
        if (! $mapping) {
            return response()->json([
                'sukses' => false,
                'pesan'  => 'Akun Anda belum terMapping ke SIMRS. Lakukan mapping akun SIMRS terlebih dahulu.',
            ]);
        }

        $ids = [$mapping->simrs_user_id];

        $ambilTindakan = $jenis === '' || $jenis === 'tindakan';
        $ambilLab      = $jenis === '' || $jenis === 'lab';

        $ht = $ambilTindakan ? $simrs->cariTindakan($ids, $dari, $sampai) : null;
        $hl = $ambilLab ? $simrs->cariLab($ids, $dari, $sampai) : null;

        $tindakan = ($ht !== null && ($ht['sukses'] ?? false)) ? ($ht['data'] ?? []) : [];
        $lab      = ($hl !== null && ($hl['sukses'] ?? false)) ? ($hl['data'] ?? []) : [];

        $gabungan = collect($tindakan)->merge($lab)
            ->sort(function ($a, $b) {
                if ($a['tanggal'] !== $b['tanggal']) {
                    return $a['tanggal'] <=> $b['tanggal'];
                }
                return $a['jam'] <=> $b['jam'];
            })
            ->values()
            ->all();

        $sukses = ($ambilTindakan ? (bool) ($ht['sukses'] ?? false) : true)
               && ($ambilLab ? (bool) ($hl['sukses'] ?? false) : true);

        $pesan = null;
        if (! $sukses) {
            if ($ambilTindakan && ! ($ht['sukses'] ?? false)) {
                $pesan = $ht['pesan'] ?? 'Gagal mengambil data tindakan.';
            } elseif ($ambilLab) {
                $pesan = $hl['pesan'] ?? 'Gagal mengambil data lab.';
            }
        }

        $peringatan = array_filter([
            $ambilTindakan && ! ($ht['sukses'] ?? false) ? ($ht['pesan'] ?? 'Data tindakan gagal diambil.') : null,
            $ambilLab && ! ($hl['sukses'] ?? false) ? ($hl['pesan'] ?? 'Data lab gagal diambil.') : null,
        ]);

        return response()->json([
            'sukses'         => $sukses,
            'pesan'          => $pesan,
            'peringatan'     => array_values($peringatan),
            'jenis'          => $jenis === '' ? 'gabungan' : $jenis,
            'total_tindakan' => count($tindakan),
            'total_lab'      => count($lab),
            'data'           => $gabungan,
        ]);
    }

    // ── Logbook: kelola entri milik sendiri ──
    public function logbookData(Request $req): JsonResponse
    {
        $user = $req->get('user');

        $f = $req->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'bulan' => ['nullable', 'integer', 'between:1,12'],
            'tahun' => ['nullable', 'integer', 'between:2000,2100'],
            'hal' => ['nullable', 'integer', 'min:1'],
        ]);

        $per = 20;
        $hal = max(1, (int) ($f['hal'] ?? 1));

        $query = Logbook::query()->where('user_id', $user->id);
        if (! empty($f['q'])) {
            $query->where('isi', 'like', '%'.trim($f['q']).'%');
        }
        if (! empty($f['bulan'])) {
            $query->whereMonth('tanggal', (int) $f['bulan']);
        }
        if (! empty($f['tahun'])) {
            $query->whereYear('tanggal', (int) $f['tahun']);
        }

        $total = (clone $query)->count();
        $rows = $query->orderByDesc('tanggal')
            ->orderByDesc('jam')
            ->skip(($hal - 1) * $per)
            ->take($per)
            ->get();

        return response()->json([
            'sukses' => true,
            'total' => $total,
            'halaman' => $hal,
            'per' => $per,
            'totalHal' => max(1, (int) ceil($total / $per)),
            'data' => $rows->map(fn ($r) => [
                'id' => $r->id,
                'tanggal' => $r->tanggal->format('Y-m-d'),
                'jam' => substr((string) $r->jam, 0, 5),
                'isi' => (string) $r->isi,
                'is_verified' => $r->is_verified,
                'verified_at' => $r->verified_at?->format('Y-m-d H:i'),
            ])->all(),
        ]);
    }

    public function logbookSimpan(Request $req): JsonResponse
    {
        $user = $req->get('user');

        $d = $req->validate([
            'tanggal' => ['required', 'date'],
            'jam' => ['required', 'date_format:H:i'],
            'isi' => ['required', 'string', 'max:1000'],
        ], [
            'tanggal.required' => 'Tanggal wajib diisi.',
            'jam.required' => 'Jam wajib diisi.',
            'jam.date_format' => 'Format jam harus HH:MM.',
            'isi.required' => 'Isi aktivitas wajib diisi.',
            'isi.max' => 'Isi aktivitas maksimal 1000 karakter.',
        ]);

        $logbook = Logbook::create([
            'user_id' => $user->id,
            'tanggal' => $d['tanggal'],
            'jam' => $d['jam'],
            'isi' => trim($d['isi']),
        ]);

        catat_aktivitas('Logbook Mobile', $user->nama_lengkap.' menyimpan entri logbook');

        return response()->json([
            'sukses' => true,
            'pesan' => '1 entri logbook tersimpan.',
            'id' => $logbook->id,
        ], 201);
    }

    public function logbookSimpanBulk(Request $req): JsonResponse
    {
        $user = $req->get('user');

        $d = $req->validate([
            'entri' => ['required', 'array', 'min:1', 'max:100'],
            'entri.*.tanggal' => ['required', 'date'],
            'entri.*.jam' => ['required', 'date_format:H:i'],
            'entri.*.isi' => ['required', 'string', 'max:1000'],
        ], [
            'entri.required' => 'Daftar entri wajib dikirim.',
            'entri.min' => 'Minimal satu entri logbook.',
            'entri.max' => 'Maksimal 100 entri per request.',
            'entri.*.tanggal.required' => 'Tanggal wajib diisi pada setiap entri.',
            'entri.*.jam.required' => 'Jam wajib diisi pada setiap entri.',
            'entri.*.jam.date_format' => 'Format jam harus HH:MM.',
            'entri.*.isi.required' => 'Isi aktivitas wajib diisi pada setiap entri.',
            'entri.*.isi.max' => 'Isi aktivitas maksimal 1000 karakter.',
        ]);

        $sekarang = now();
        $baris = array_map(fn ($e) => [
            'user_id' => $user->id,
            'tanggal' => $e['tanggal'],
            'jam' => $e['jam'],
            'isi' => trim($e['isi']),
            'created_at' => $sekarang,
            'updated_at' => $sekarang,
        ], $d['entri']);

        Logbook::insert($baris);

        catat_aktivitas('Logbook Mobile', $user->nama_lengkap.' menyimpan '.count($baris).' entri logbook');

        return response()->json([
            'sukses' => true,
            'pesan' => count($baris).' entri logbook tersimpan.',
            'total' => count($baris),
        ], 201);
    }

    public function logbookUbah(Request $req): JsonResponse
    {
        $user = $req->get('user');

        $d = $req->validate([
            'id' => ['required', 'integer'],
            'tanggal' => ['required', 'date'],
            'jam' => ['required', 'date_format:H:i'],
            'isi' => ['required', 'string', 'max:1000'],
        ], [
            'id.required' => 'ID entri wajib dikirim.',
            'tanggal.required' => 'Tanggal wajib diisi.',
            'jam.required' => 'Jam wajib diisi.',
            'jam.date_format' => 'Format jam harus HH:MM.',
            'isi.required' => 'Isi aktivitas wajib diisi.',
            'isi.max' => 'Isi aktivitas maksimal 1000 karakter.',
        ]);

        $terubah = Logbook::where('id', (int) $d['id'])
            ->where('user_id', $user->id)
            ->where('is_verified', false)
            ->update([
                'tanggal' => $d['tanggal'],
                'jam' => $d['jam'],
                'isi' => trim($d['isi']),
            ]);

        if (! $terubah) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Entri tidak ditemukan, bukan milik Anda, atau sudah diverifikasi.',
            ], 404);
        }

        catat_aktivitas('Logbook Mobile', $user->nama_lengkap.' mengubah entri logbook');

        return response()->json(['sukses' => true, 'pesan' => 'Entri logbook diperbarui.']);
    }

    public function logbookHapus(Request $req, int $id): JsonResponse
    {
        $user = $req->get('user');

        $terhapus = Logbook::where('id', $id)
            ->where('user_id', $user->id)
            ->where('is_verified', false)
            ->delete();

        if (! $terhapus) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Entri tidak ditemukan, bukan milik Anda, atau sudah diverifikasi.',
            ], 404);
        }

        catat_aktivitas('Logbook Mobile', $user->nama_lengkap.' menghapus entri logbook');

        return response()->json(['sukses' => true, 'pesan' => '1 entri logbook dihapus.']);
    }

    // ── Logbook: template pribadi ──
    public function templateSimpan(Request $req): JsonResponse
    {
        $user = $req->get('user');

        $d = $req->validate([
            'isi' => ['required', 'string', 'max:1000'],
        ], [
            'isi.required' => 'Isi template wajib diisi.',
            'isi.max' => 'Isi template maksimal 1000 karakter.',
        ]);

        $template = TemplateLogbook::create([
            'user_id' => $user->id,
            'type' => 'user',
            'isi' => trim($d['isi']),
        ]);

        catat_aktivitas('Template Logbook Mobile', $user->nama_lengkap.' menambah template logbook');

        return response()->json([
            'sukses' => true,
            'pesan' => 'Template logbook disimpan.',
            'id' => $template->id,
        ], 201);
    }

    public function templateUbah(Request $req): JsonResponse
    {
        $user = $req->get('user');

        $d = $req->validate([
            'id' => ['required', 'integer'],
            'isi' => ['required', 'string', 'max:1000'],
        ], [
            'id.required' => 'ID template wajib dikirim.',
            'isi.required' => 'Isi template wajib diisi.',
            'isi.max' => 'Isi template maksimal 1000 karakter.',
        ]);

        $terubah = TemplateLogbook::where('id', (int) $d['id'])
            ->where('user_id', $user->id)
            ->update(['isi' => trim($d['isi'])]);

        if (! $terubah) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Template tidak ditemukan atau bukan milik Anda.',
            ], 404);
        }

        catat_aktivitas('Template Logbook Mobile', $user->nama_lengkap.' mengubah template logbook');

        return response()->json(['sukses' => true, 'pesan' => 'Template logbook diperbarui.']);
    }

    public function templateHapus(Request $req, int $id): JsonResponse
    {
        $user = $req->get('user');

        $terhapus = TemplateLogbook::where('id', $id)
            ->where('user_id', $user->id)
            ->delete();

        if (! $terhapus) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Template tidak ditemukan atau bukan milik Anda.',
            ], 404);
        }

        catat_aktivitas('Template Logbook Mobile', $user->nama_lengkap.' menghapus template logbook');

        return response()->json(['sukses' => true, 'pesan' => 'Template logbook dihapus.']);
    }

    private function catatPercobaan(string $email, string $ip, bool $sukses): void
    {
        LoginAttempt::create([
            'email'  => mb_substr($email, 0, 150),
            'ip'     => $ip,
            'sukses' => $sukses ? 1 : 0,
            'waktu'  => now(),
        ]);
        LoginAttempt::where('waktu', '<', now()->subDays(2))->delete();
    }
    

    private function jumlahGagal(string $email, string $ip):int{
        $sejak = now()->subMinutes(self::WINDOW_MINUTE);
         return LoginAttempt::where('sukses', 0)
            ->where('waktu', '>=', $sejak)
            ->where(function ($q) use ($email, $ip) {
                $q->where('email', $email)
                ->orWhere('ip', $ip);
            })
            ->count();
    }

    private function sisaPercobaan(string $email, string $ip):int{
        return max(0, self::MAX_FAIL - $this->jumlahGagal($email,$ip));
    }

    private function sisaBlokir(string $email, string $ip):int{
        if($this->jumlahGagal($email,$ip) < self::MAX_FAIL)return 0;
        $sejak = now()->subMinutes(self::WINDOW_MINUTE);
        $terbaru = LoginAttempt::where('sukses',0)
            ->where('waktu', '>=', $sejak)
            ->where(function ($q)use ($email, $ip){
                $q->where('email',$email)->orWhere('ip',$ip);
            })->orderBy('waktu','desc')->first();
        $habis = strtotime((string) ($terbaru->waktu ?? 'now')) + self::WINDOW_MINUTE * 60;
        return max(1,(int)ceil(($habis-time())/60));
    }
}
