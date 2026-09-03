<?php

namespace App\Providers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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
        $this->configureDefaults();
        $this->grantSuperAdminsEveryAbility();
        $this->restrictLogViewerToSuperAdmins();
    }

    /**
     * Let a super admin through every policy check.
     *
     * A super admin belongs to no organization, so teamRole() is null and any
     * role based permission lookup denies them — the trap TeamPolicy::createMember
     * already works around by asking isSuperAdmin() explicitly. Granting it
     * centrally means a policy added later cannot forget to.
     *
     * Returning null rather than false for everyone else is what lets the normal
     * policies run; false here would deny the entire application.
     */
    protected function grantSuperAdminsEveryAbility(): void
    {
        Gate::before(fn (User $user): ?bool => $user->isSuperAdmin() ? true : null);
    }

    /**
     * Keep the bundled log viewer to super admins.
     *
     * opcodesio/log-viewer authorizes through a `viewLogViewer` gate and, when
     * nothing defines it, falls back to "local environment only". That is a
     * silent dependency on APP_ENV for something that serves raw application
     * logs -- stack traces, request payloads and whatever a log line happens to
     * carry. Defining it explicitly means the answer never depends on how the
     * environment is configured.
     *
     * Gate::before already lets super admins through, so this exists to DENY
     * everyone else.
     */
    protected function restrictLogViewerToSuperAdmins(): void
    {
        Gate::define('viewLogViewer', fn (User $user): bool => $user->isSuperAdmin());
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
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
            : null,
        );
    }
}
