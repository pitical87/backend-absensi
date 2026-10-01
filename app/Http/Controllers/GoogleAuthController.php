<?php

namespace App\Http\Controllers;

use App\Models\Pengaturan;
use App\Services\GoogleLoginService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

/**
 * Login dengan Google untuk halaman login web (alur redirect browser).
 * Kebijakan akun ditegakkan oleh GoogleLoginService yang sama dengan
 * endpoint mobile, sehingga hasil dan pesannya konsisten.
 */
class GoogleAuthController extends Controller
{
    public function __construct(private readonly GoogleLoginService $google) {}

    /** Kirim pengguna ke layar persetujuan akun Google. */
    public function redirect(Request $request): SymfonyRedirect|JsonResponse
    {
        if (! $this->google->tersedia()) {
            return $this->kembali('Login dengan Google belum dikonfigurasi di server ini.');
        }

        if (! $this->skemaSiap()) {
            return redirect('install');
        }

        $terdaftar = $this->redirectUriTeregister();
        $efektif = $this->selaraskanHostLokal($request, $terdaftar);

        try {
            $driver = Socialite::driver('google');

            if ($efektif !== $terdaftar) {
                $driver->redirectUrl($efektif);
            }

            $respons = $driver->redirect();
        } catch (Throwable $e) {
            Log::warning('Login Google gagal dimulai: '.$e->getMessage());

            return $this->kembali('Tidak dapat terhubung ke Google. Coba lagi sebentar lagi.');
        }

        // Dicatat agar redirect_uri yang benar-benar dikirim bisa disalin persis
        // ke Authorized redirect URI di Google Cloud Console saat terjadi
        // redirect_uri_mismatch, sekaligus memeriksa host callback masih sama
        // dengan host tempat login dibuka (kalau berbeda, cookie sesi hilang).
        Log::info('Login Google dimulai, redirect_uri: '.$efektif.($efektif === $terdaftar ? '' : ' (configured: '.$terdaftar.')'));

        if (! $this->hostSama($request, $efektif)) {
            Log::warning(
                'Login Google: host browser ('.$request->getSchemeAndHttpHost()
                .') berbeda dari redirect_uri ('.$efektif.'). Callback akan kembali ke host lain sehingga'
                .' cookie sesi tidak terkirim. Daftarkan URI dengan host yang sama di Google Cloud Console.'
            );
        }

        return $respons;
    }

    /** Penerima balasan Google, lalu lanjutkan ke sesi aplikasi. */
    public function callback(Request $request): RedirectResponse
    {
        if (! $this->google->tersedia()) {
            return $this->kembali('Login dengan Google belum dikonfigurasi di server ini.');
        }

        if (! $this->skemaSiap()) {
            return redirect('install');
        }

        try {
            $akunGoogle = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            // InvalidStateException = state/session tidak cocok, umumnya karena
            // login di dua tab atau callback datang dari host berbeda.
            Log::info('Login Google dibatalkan/tidak valid: '.$e::class.' - '.$e->getMessage());

            if ($e instanceof InvalidStateException) {
                return $this->kembali(
                    'Sesi login Google tidak cocok. Buka ulang halaman login dari URL yang sama persis dengan '
                    .'GOOGLE_REDIRECT_URI (jangan tercampur localhost, 127.0.0.1, atau IP lain), lalu coba lagi.'
                );
            }

            return $this->kembali('Login dengan Google gagal. Silakan coba lagi.');
        }

        $hasil = $this->google->masukDenganSocialite($akunGoogle);
        $user = $hasil['user'];

        if (! $user) {
            return $this->kembali((string) $hasil['pesan']);
        }

        session()->regenerate(true);
        session()->put([
            'uid' => (int) $user->id,
            'role' => $user->role,
            'nama' => $user->nama_lengkap,
            'posisi' => $user->posisi ?? 'Staf',
            'email' => $user->email,
        ]);
        catat_aktivitas('Masuk Google', $user->nama_lengkap.' ('.$user->role.') masuk ke sistem dengan akun Google');

        return redirect()->intended('dashboard');
    }

    /** Persis yang dikirim ke Google; harus dicocokkan karakter demi karakter. */
    public function redirectUriTeregister(): string
    {
        return (string) config('services.google.redirect', '');
    }

    /**
     * Cookie sesi terikat host tempat login dibuka. Bila redirect_uri menunjuk
     * host lain, browser kembali tanpa cookie sehingga state tidak akan cocok.
     * Untuk pengembangan lokal saja, host loopback pada URI disesuaikan dengan
     * host yang dipakai browser (localhost vs 127.0.0.1). Di produksi nilai dari
     * .env tidak pernah ditimpa, sehingga Host header tidak bisa menyuntik
     * redirect.
     */
    private function selaraskanHostLokal(Request $request, string $terdaftar): string
    {
        $hostTercatat = (string) parse_url($terdaftar, PHP_URL_HOST);
        $hostBrowser = (string) parse_url($request->getSchemeAndHttpHost(), PHP_URL_HOST);

        if (! $this->hostLokal($hostTercatat) || ! $this->hostLokal($hostBrowser) || $hostTercatat === $hostBrowser) {
            return $terdaftar;
        }

        return $request->getSchemeAndHttpHost().(string) parse_url($terdaftar, PHP_URL_PATH);
    }

    private function hostSama(Request $request, string $efektif): bool
    {
        $host = (string) parse_url($efektif, PHP_URL_HOST);
        $port = (int) parse_url($efektif, PHP_URL_PORT);

        return $host !== ''
            && $host === (string) parse_url($request->getSchemeAndHttpHost(), PHP_URL_HOST)
            && $port === (int) parse_url($request->getSchemeAndHttpHost(), PHP_URL_PORT)
            && parse_url($efektif, PHP_URL_SCHEME) === $request->getScheme();
    }

    private function hostLokal(string $host): bool
    {
        return in_array(strtolower(trim($host, '[]')), ['localhost', '127.0.0.1', '::1'], true);
    }

    private function kembali(string $pesan): RedirectResponse
    {
        return redirect('login')->with('galat', $pesan);
    }

    private function skemaSiap(): bool
    {
        try {
            Pengaturan::limit(1)->get();

            return true;
        } catch (Throwable $e) {
            return false;
        }
    }
}
