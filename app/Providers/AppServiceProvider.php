<?php

namespace App\Providers;

use App\Models\AvailabilityRule;
use App\Models\Center;
use App\Models\DayOff;
use App\Models\Doctor;
use App\Policies\AvailabilityRulePolicy;
use App\Policies\CenterPolicy;
use App\Policies\DayOffPolicy;
use App\Policies\DoctorPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->configureModels();
        $this->registerPolicies();
    }

    /**
     * Rate limits by route class, as required by the security checklist.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)
            ->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by($this->identifier($request)),
            Limit::perMinute(20)->by($request->ip()),
        ]);

        RateLimiter::for('register', fn (Request $request) => Limit::perMinute(5)
            ->by($request->ip()));

        RateLimiter::for('center-register', fn (Request $request) => Limit::perMinute(3)
            ->by($request->ip()));

        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinute(3)
            ->by($this->identifier($request)));

        RateLimiter::for('verification', fn (Request $request) => Limit::perMinute(3)
            ->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('sensitive', fn (Request $request) => Limit::perMinute(6)
            ->by($request->user()?->id ?: $request->ip()));
    }

    /**
     * Throttle per identifier *and* per IP, so neither one alone can be rotated
     * to bypass the limit.
     */
    private function identifier(Request $request): string
    {
        return Str::lower((string) $request->input('email')).'|'.$request->ip();
    }

    private function configureModels(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());
    }

    private function registerPolicies(): void
    {
        Gate::policy(Center::class, CenterPolicy::class);
        Gate::policy(Doctor::class, DoctorPolicy::class);
        Gate::policy(AvailabilityRule::class, AvailabilityRulePolicy::class);
        Gate::policy(DayOff::class, DayOffPolicy::class);
    }
}
