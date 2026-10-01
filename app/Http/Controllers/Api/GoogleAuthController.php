<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\BatasiPercobaanLogin;
use App\Http\Controllers\Concerns\TerbitkanTokenMobile;
use App\Http\Controllers\Controller;
use App\Services\GoogleLoginService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Login dengan Google untuk aplikasi web terpisah (React): aplikasi memperoleh
 * id_token dari Google Identity Services lalu mengirimkannya ke endpoint ini.
 * Server memverifikasi tanda tangan, issuer, audience, masa berlaku, dan status
 * email sebelum menerbitkan token aplikasi.
 */
class GoogleAuthController extends Controller
{
    use BatasiPercobaanLogin;
    use TerbitkanTokenMobile;

    public function __construct(private readonly GoogleLoginService $google) {}

    public function login(Request $req): JsonResponse
    {
        $validator = Validator::make($req->all(), [
            'id_token' => ['required', 'string', 'max:4096'],
        ]);

        if ($validator->fails()) {
            return $this->gagal('ID token Google wajib diisi.', 422);
        }

        $ip = $req->ip();

        // Pembatasan hanya berdasarkan IP: alamat email pada request berasal dari
        // klien dan tidak boleh menjadi kunci pemblokir.
        $sisa = $this->sisaBlokir(null, $ip);
        if ($sisa > 0) {
            return $this->gagal("Terlalu banyak percobaan gagal. Coba lagi dalam {$sisa} menit.", 429);
        }

        $hasil = $this->google->masukDenganIdToken((string) $req->input('id_token'));
        $user = $hasil['user'];

        if (! $user) {
            $this->catatPercobaan(null, $ip, false);

            return $this->gagal((string) $hasil['pesan'], (int) $hasil['kode']);
        }

        $this->hapusPercobaanGagal((string) $user->email);
        $this->catatPercobaan((string) $user->email, $ip, true);

        return $this->terbitkanTokenMobile(
            $req,
            $user,
            $ip,
            $user->nama_lengkap.' masuk dari aplikasi mobile dengan akun Google'
        );
    }

    private function gagal(string $pesan, int $kode): JsonResponse
    {
        return response()->json(['sukses' => false, 'pesan' => $pesan], $kode);
    }
}
