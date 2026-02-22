<?php

namespace App\Providers;

use App\Models\Rating;
use App\Models\Claim;
use App\Models\TechnicianRequest;
use App\Models\User;
use App\Policies\RatingPolicy;
use App\Policies\ClaimPolicy;
use App\Policies\TechnicianRequestPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
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
        $appUrl = (string) config('app.url');
        if (app()->environment('production') && $appUrl !== '') {
            URL::forceRootUrl($appUrl);
            if (str_starts_with($appUrl, 'https://')) {
                URL::forceScheme('https');
            }
        }

        Gate::policy(Rating::class, RatingPolicy::class);
        Gate::policy(Claim::class, ClaimPolicy::class);
        Gate::policy(TechnicianRequest::class, TechnicianRequestPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
    }
}
