<?php

namespace App\Providers;

use App\Http\Cookie\CookieService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CookieService::class, function () {
            return new CookieService(
                secure: (bool) env('COOKIE_SECURE', false),
                accessTtlSeconds: (int) env('COOKIE_ACCESS_TTL', 60 * 60),
                refreshTtlSeconds: (int) env('COOKIE_REFRESH_TTL', 7 * 24 * 60 * 60),
                apiPath: env('COOKIE_API_PATH', '/api/v1'),
            );
        });
    }

    public function boot(): void
    {
        $this->configureDefaults();
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : Password::min(8),
        );
    }
}
