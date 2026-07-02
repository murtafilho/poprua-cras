<?php

namespace App\Providers;

use App\Models\Ponto;
use App\Observers\PontoObserver;
use App\Services\ParametroService;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
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
        app()->setLocale('pt_BR');

        Model::preventLazyLoading(! app()->isProduction());
        Ponto::observe(PontoObserver::class);

        RedirectIfAuthenticated::redirectUsing(fn () => route('mapa.index'));

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        if (Schema::hasTable('parametros')) {
            app(ParametroService::class)->sincronizarConfigApp();
        }
    }
}
