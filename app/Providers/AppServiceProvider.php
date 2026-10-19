<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        // Before HTTP session writes, Artisan migrations, or RefreshDatabase traits.
        if (app()->environment('testing', 'validation') || getenv('FREECAST_TEST_RUNTIME') === '1') {
            \App\Support\TestEnvironmentGuard::assert();
        }
        if (app()->environment('production')) {
            Password::defaults(
                fn (): Password => Password::min(12)->mixedCase()->numbers()->symbols()
            );
        }

        Vite::prefetch(concurrency: 3);
    }
}
