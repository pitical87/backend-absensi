<?php

namespace App\Services;

use App\Models\User;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Throwable;

/**
 * Login dengan Google, dipakai bersama oleh halaman login web (alur redirect
 * laravel/socialite) dan aplikasi web terpisah yang mengirim id_token ke
 * /api/mobile/login/google.
 *
 * Kebijakan yang berlaku sama untuk kedua jalur:
 * - email dari Google harus sudah terdaftar di tabel users, tidak ada akun baru;
 * - hanya role pegawai, admin tidak bisa masuk lewat Google;
 * - akun harus berstatus aktif;
 * - Google harus menyatakan email terverifikasi, baru email_verified_at diisi
 *   otomatis (menghapus banner verifikasi dan membuka kembali reset password);
 * - subjek akun Google dicatat pada users.google_sub. Bila suatu saat subjeknya
 *   berubah, login ditolak karena indikasi email dialihkan di sisi Google.
 *
 * Data master (nama, NIP, unit kerja, jabatan) tidak pernah ditimpa dari Google.
 */
class GoogleLoginService
{
    private const JWKS_URL = 'https://www.googleapis.com/oauth2/v3/certs';

    private const ISSUER = ['accounts.google.com', 'https://accounts.google.com'];

    /** Toleransi detik sebelum id_token dianggap kedaluwarsa. */
    private const TOLERANSI_DETIK = 60;

    /** Batas panjang id_token agar request raksasa tidak diproses. */
    private const PANJANG_ID_TOKEN_MAKS = 4096;

    private const KODE_SUKSES = 200;

    private const KODE_TOKEN = 401;

    private const KODE_AKUN = 403;

    /**
     * Client id yang sah untuk id_token. Falls back ke client id web bila
     * daftar mobile kosong, sehingga satu credential bisa dipakai untuk
     * halaman login web dan aplikasi sekaligus.
     *
     * @return array<int, string>
     */
    public function clientIds(): array
    {
        $ids = array_values(array_filter(
            array_map('strval', (array) config('services.google.mobile_client_ids', [])),
            fn ($v) => $v !== ''
        ));

        $web = (string) config('services.google.client_id', '');
        if ($web !== '' && ! in_array($web, $ids, true)) {
            $ids[] = $web;
        }

        return $ids;
    }

    /**
     * Login Google tersedia bila credential web sudah terisi.
     */
    public function tersedia(): bool
    {
        return (string) config('services.google.client_id', '') !== '';
    }

    /**
     * Verifikasi id_token yang dikirim aplikasi, lalu terapkan kebijakan akun.
     *
     * @return array{user: ?User, pesan: ?string, kode: int}
     */
    public function masukDenganIdToken(string $idToken): array
    {
        $idToken = trim($idToken);

        if ($idToken === '') {
            return $this->hasilToken('ID token Google wajib diisi.');
        }

        if (strlen($idToken) > self::PANJANG_ID_TOKEN_MAKS) {
            return $this->hasilToken('ID token Google tidak valid.');
        }

        $clientIds = $this->clientIds();
        if ($clientIds === []) {
            return $this->hasilToken('Login dengan Google belum dikonfigurasi di server ini.', 503);
        }

        $jwks = $this->jwks();
        if ($jwks === null) {
            return $this->hasilToken('Kunci publik Google tidak dapat dimuat. Coba lagi sebentar lagi.', 503);
        }

        try {
            $claims = (array) JWT::decode($idToken, JWK::parseKeySet($jwks, 'RS256'));
        } catch (Throwable $e) {
            return $this->hasilToken('ID token Google tidak valid atau sudah kedaluwarsa.');
        }

        $email = strtolower(trim((string) ($claims['email'] ?? '')));
        if ($email === '') {
            return $this->hasilToken('ID token Google tidak memuat alamat email.');
        }

        if (! in_array((string) ($claims['iss'] ?? ''), self::ISSUER, true)) {
            return $this->hasilToken('ID token Google tidak valid.');
        }

        $aud = array_map('strval', (array) ($claims['aud'] ?? []));
        if ($aud === [] || ! array_intersect($aud, $clientIds)) {
            return $this->hasilToken('ID token Google tidak diterbitkan untuk aplikasi ini.');
        }

        if ((float) ($claims['exp'] ?? 0) <= time() - self::TOLERANSI_DETIK) {
            return $this->hasilToken('ID token Google sudah kedaluwarsa.');
        }

        return $this->izzaUser([
            'email' => $email,
            'sub' => (string) ($claims['sub'] ?? ''),
            'email_verified' => (bool) ($claims['email_verified'] ?? false),
            'hd' => (string) ($claims['hd'] ?? ''),
        ]);
    }

    /**
     * Verifikasi user hasil Socialite (alur redirect browser), lalu terapkan
     * kebijakan akun yang sama.
     *
     * @return array{user: ?User, pesan: ?string, kode: int}
     */
    public function masukDenganSocialite(SocialiteUser $akunGoogle): array
    {
        $email = strtolower(trim((string) $akunGoogle->getEmail()));
        if ($email === '') {
            return $this->hasilToken('Akun Google ini tidak memuat alamat email.');
        }

        $mentah = method_exists($akunGoogle, 'getRaw') ? (array) $akunGoogle->getRaw() : [];

        return $this->izzaUser([
            'email' => $email,
            'sub' => (string) $akunGoogle->getId(),
            // Google selalu menyertakan klaim ini; dianggap benar bila absen.
            'email_verified' => array_key_exists('email_verified', $mentah) ? (bool) $mentah['email_verified'] : true,
            'hd' => (string) ($mentah['hd'] ?? ''),
        ]);
    }

    /**
     * Kebijakan akun bersama untuk web dan mobile.
     *
     * @return array{user: ?User, pesan: ?string, kode: int}
     */
    public function izzaUser(array $data): array
    {
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $sub = trim((string) ($data['sub'] ?? ''));
        $emailVerifikasiGoogle = (bool) ($data['email_verified'] ?? false);

        if ($email === '') {
            return $this->hasilAkun('Alamat email tidak dikenali.', $email, 'email kosong');
        }

        if (! $emailVerifikasiGoogle) {
            return $this->hasilAkun(
                'Google menyatakan email ini belum terverifikasi, login dibatalkan.',
                $email,
                'email belum terverifikasi di sisi Google'
            );
        }

        $domain = (string) config('services.google.hd', '');
        if ($domain !== '' && strtolower((string) ($data['hd'] ?? '')) !== strtolower($domain)) {
            return $this->hasilAkun(
                'Akun Google harus berasal dari domain '.$domain.'.',
                $email,
                'domain Google di luar daftar'
            );
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            return $this->hasilAkun(
                'Email ini belum terdaftar di sistem. Silakan hubungi administrator untuk pendaftaran akun.',
                $email,
                'email belum terdaftar'
            );
        }

        if ($user->role === 'admin') {
            return $this->hasilAkun(
                'Login dengan Google hanya untuk pegawai, silakan gunakan email dan password.',
                $email,
                'role admin tidak boleh login lewat Google'
            );
        }

        if (! $user->isActive()) {
            return $this->hasilAkun('Akun Anda dinonaktifkan. Hubungi administrator.', $email, 'akun nonaktif');
        }

        if ($sub !== '' && (string) $user->google_sub !== '' && (string) $user->google_sub !== $sub) {
            return $this->hasilAkun(
                'Akun Google ini tidak lagi terhubung dengan email tersebut. Hubungi administrator.',
                $email,
                'subjek akun Google berubah'
            );
        }

        $perubahan = [];

        if ($sub !== '' && (string) $user->google_sub === '') {
            $perubahan['google_sub'] = $sub;
        }

        $baruDiverifikasi = false;
        if (is_null($user->email_verified_at)) {
            $perubahan['email_verified_at'] = now();
            $baruDiverifikasi = true;
        }

        if ($perubahan !== []) {
            $user->forceFill($perubahan)->save();
        }

        if ($baruDiverifikasi) {
            catat_aktivitas(
                'Verifikasi Email (Google)',
                $user->nama_lengkap.' ('.$email.') terverifikasi otomatis karena login dengan Google',
                (int) $user->id
            );
        }

        return ['user' => $user->fresh(), 'pesan' => null, 'kode' => self::KODE_SUKSES];
    }

    /**
     * Kegagalan pada token (bukan pada akun): 401, atau 503 bila konfigurasi
     * server belum siap.
     *
     * @return array{user: null, pesan: string, kode: int}
     */
    private function hasilToken(string $pesan, int $kode = self::KODE_TOKEN): array
    {
        return ['user' => null, 'pesan' => $pesan, 'kode' => $kode];
    }

    /**
     * Penolakan atas sisi akun: dicatat di aktivitas_log lalu 403.
     *
     * @return array{user: null, pesan: string, kode: int}
     */
    private function hasilAkun(string $pesan, string $email, string $alasan): array
    {
        catat_aktivitas('Login Google Ditolak', $email.' ('.$alasan.')');

        return ['user' => null, 'pesan' => $pesan, 'kode' => self::KODE_AKUN];
    }

    /**
     * Kunci publik Google (JWKS), disimpan di cache agar tidak diambil tiap
     * permintaan. Karena Google bisa merotasi kid, kid yang tidak ditemukan
     * diperlakukan sebagai cache basi lalu diambil ulang sekali.
     */
    private function jwks(): ?array
    {
        $kunci = 'google.jwks';
        $jwks = Cache::get($kunci);
        if (is_array($jwks) && $jwks !== []) {
            return $jwks;
        }

        try {
            $respons = Http::timeout(5)->retry(2, 200)->get(self::JWKS_URL);
        } catch (Throwable $e) {
            return null;
        }

        // Simpan seluruh JWK Set (objek beranggota "keys"), bukan hanya daftarnya.
        $jwks = $respons->successful() ? $respons->json() : null;
        $keys = is_array($jwks) ? ($jwks['keys'] ?? null) : null;

        return is_array($keys) && $keys !== []
            ? Cache::remember($kunci, now()->addHour(), fn () => $jwks)
            : null;
    }
}
