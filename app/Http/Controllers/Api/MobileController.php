<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Absensi;
use App\Models\ApiToken;
use App\Models\Izin;
use App\Models\LoginAttempt;
use App\Models\User;
use App\Services\AbsenService;
use App\Services\AlurIzinService;
use App\Services\AnomaliService;
use App\Services\CutiService;
use App\Services\RekapService;
use Carbon\Carbon;
use DateTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;

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
        $total = Izin::dariBawahan($user)->count();
        return response()->json([
            "sukses"=>true,
            "total"=>$total
        ]);
    }

    public function getDetailIzinMenunggu(Request $req):JsonResponse{
        $user = $req->get('user');
        $daftar = Izin::dariBawahan($user)
            ->with(['user:id, nama_lengkap, nip, unit_kerja_id'])
            ->orderByDesc('created_at')
            ->get();
        return response()->json([
            "sukses"=>true,
            "izin"=>$daftar
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
            ] : null,
            "absen_pulang" => $absen?->waktu_pulang ? [
                "waktu" => substr($absen->waktu_pulang, 11, 5),
            ] : null,
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
        ]);
    }

    public function jadwal(Request $req): JsonResponse
    {
        $user = $req->get('user');
        $today = now()->toDateString();

        $jadwal = \Illuminate\Support\Facades\DB::table('jadwal_shift')
            ->where('user_id', $user->id)
            ->where('tanggal_berlaku', '<=', $today)
            ->orderByDesc('tanggal_berlaku')
            ->first();

        $shiftId = $jadwal ? (int) $jadwal->shift_id : $user->shift_id;
        $shift = $shiftId ? \App\Models\Shift::find($shiftId) : null;

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
            foreach (DB::table('hari_libur')->get() as $h) {
                $liburSet[$h->tanggal] = true;
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
