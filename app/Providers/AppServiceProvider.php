<?php

namespace App\Providers;

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
