<?php

namespace App\Providers;

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
