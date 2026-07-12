<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Absensi;
use App\Models\ApiToken;
use App\Models\Izin;
use App\Models\LoginAttempt;
use App\Models\User;
use App\Services\AbsenService;
use App\Services\AnomaliService;
use App\Services\RekapService;
use Carbon\Carbon;
use DateTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

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

        $user = User::where('email',$email)->first();
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
