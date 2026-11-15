<?php

namespace App\Providers;

use App\Services\Administration\SettingsService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        Model::preventLazyLoading(! $this->app->isProduction());

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(
            max(10, (int) app(SettingsService::class)->get('api', 'rate_limit_per_minute', 60))
        )->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(10)->by(strtolower((string) $request->input('login', $request->input('email'))).'|'.$request->ip()));
    }
}
