<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! app()->isProduction());

        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)
                ->by($request->user()?->id ?: $request->ip());
        });

        $this->enforceMaxRequestSize();
    }

    private function enforceMaxRequestSize(): void
    {
        if (app()->runningInConsole()) {
            return;
        }

        $request = request();

        if (! $request->hasHeader('Content-Length')) {
            return;
        }

        $maxSize = 10 * 1024 * 1024;

        if ((int) $request->header('Content-Length') > $maxSize) {
            abort(413, 'Request terlalu besar. Maksimal 10MB.');
        }
    }
}
