<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        // Brute-force protection: per account + IP, and a wider per-IP cap.
        RateLimiter::for('login', function (Request $request) {
            $id = strtolower((string) ($request->input('email') ?: $request->input('student_id')));
            return [
                Limit::perMinute(5)->by('login:'.$id.'|'.$request->ip()),
                Limit::perMinute(20)->by('login-ip:'.$request->ip()),
            ];
        });
        RateLimiter::for('public-form', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip());
        });

        RateLimiter::for('api', function (Request $request) {
            $user = $request->user();

            // The shared tester account is open on several computers at once
            // while the system is being tested, and a single page load is 5-10
            // requests, so it gets more room. Remove this together with the
            // tester account and its role switcher (DevController) at deployment.
            $isTester = $user instanceof \App\Models\User
                && strtolower((string) $user->email) === \App\Http\Controllers\Api\DevController::TESTER_EMAIL;

            return Limit::perMinute($isTester ? 240 : 60)->by($user?->id ?: $request->ip());
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}