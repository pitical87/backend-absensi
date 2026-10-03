<?php

namespace App\Providers;

use App\Models\Notifikasi;
use App\Services\AncamanLoginService;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerBrainPackage();
        $this->registerBadgeAncaman();
        $this->registerNotifikasi();
    }

    /**
     * Bagikan jumlah ancaman login ke layout admin supaya sidebar dan lonceng
     * navbar bisa menampilkan badge. Hanya dihitung untuk admin, dan
     * di-cache singkat oleh service agar tidak agregasi di setiap halaman.
     */
    private function registerBadgeAncaman(): void
    {
        View::composer('layouts.admin', function ($view) {
            $jumlah = 0;

            if (session('role') === 'admin') {
                $jumlah = app(AncamanLoginService::class)->jumlahBadge();
            }

            $view->with('jumlahAncaman', $jumlah);
        });
    }

    /**
     * Bagikan notifikasi milik admin sendiri ke navbar, sehingga lonceng tidak
     * hanya menampilkan pengajuan izin dan ancaman login. Query dibatasi ke
     * user_id dari sesi; panel ini tidak pernah menampilkan notifikasi user lain.
     */
    private function registerNotifikasi(): void
    {
        View::composer('layouts.admin', function ($view) {
            $daftar = collect();
            $belum = 0;

            if ($uid = (int) session('uid')) {
                // Hanya yang belum dibaca: begitu ditandai terbaca, notifikasi
                // hilang dari lonceng supaya panel tidak pernah menumpuk.
                $daftar = Notifikasi::where('user_id', $uid)
                    ->belumDibaca()
                    ->orderByDesc('id')
                    ->limit(8)
                    ->get();

                // Badge menghitung seluruh yang belum dibaca, bukan hanya 8 baris
                // yang tampil, supaya angkanya tidak salah saat notifikasi banyak.
                $belum = (int) Notifikasi::where('user_id', $uid)->belumDibaca()->count();
            }

            $view->with([
                'notifikasiDaftar' => $daftar,
                'notifikasiBelumDibaca' => $belum,
            ]);
        });
    }

    /**
     * Register Laravel Brain views.
     * The package's own service provider is excluded from auto-discovery
     * so we can apply admin middleware to its routes.
     * The brain:scan command is registered in routes/console.php.
     */
    private function registerBrainPackage(): void
    {
        if (! $this->app->isLocal()) {
            return;
        }

        $pkgPath = base_path('vendor/laramint/laravel-brain');

        $this->app['view']->addNamespace(
            'laravel-brain',
            $pkgPath.'/resources/views'
        );
    }
}
