<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Carbon;


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
        // Register Blade directives for consistent formatting
        Blade::directive('currency', function ($expression) {
            return "<?php echo '₱' . number_format((float) ({$expression}), 2); ?>";
        });

        Blade::directive('dateYmd', function ($expression) {
            return "<?php echo ($expression) ? (\\Illuminate\\Support\\Carbon::parse({$expression})->format('Y-m-d')) : ''; ?>";
        });
    }
}
