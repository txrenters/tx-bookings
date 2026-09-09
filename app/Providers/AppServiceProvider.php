<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Mail\MicrosoftGraphTransport;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

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
        $this->registerMicrosoftGraphMailer();
        $this->grantSuperAdminsEveryAbility();
        $this->restrictLogViewerToSuperAdmins();
        $this->restrictUserDirectoryToSuperAdmins();
    }

    /**
     * Register the Microsoft Graph mail transport.
     *
     * The Azure app registration is the same one the calendar integration
     * uses, but this token is app-only rather than a signed in user's, so the
     * registration needs the Mail.Send APPLICATION permission with admin
     * consent -- ideally narrowed to the bookings mailbox with an Exchange
     * application access policy.
     */
    protected function registerMicrosoftGraphMailer(): void
    {
        Mail::extend('microsoft-graph', function (array $config = []): MicrosoftGraphTransport {
            foreach (['tenant', 'client_id', 'client_secret', 'mailbox'] as $key) {
                if (blank($config[$key] ?? null)) {
                    throw new RuntimeException("The microsoft-graph mailer needs a {$key}; see config/mail.php.");
                }
            }

            /**
             * The calendar integration signs users in and can accept the
             * multi-tenant 'common' placeholder. An app-only token cannot:
             * there is no user to resolve the tenant from, so the mail tenant
             * has to name the real one.
             */
            if ($config['tenant'] === 'common') {
                throw new RuntimeException('The microsoft-graph mailer needs a real tenant; client credentials cannot use "common".');
            }

            return new MicrosoftGraphTransport(
                cache: app(Cache::class),
                tenant: $config['tenant'],
                clientId: $config['client_id'],
                clientSecret: $config['client_secret'],
                mailbox: $config['mailbox'],
                saveToSentItems: (bool) ($config['save_to_sent_items'] ?? false),
            );
        });
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
     * Keep the user directory to super admins.
     *
     * It lists every account in the installation and can send any of them a
     * password reset, so it is not an organization level permission: only an
     * operator of the whole system sees it. Gate::before already lets super
     * admins through, so this exists to DENY everyone else.
     */
    protected function restrictUserDirectoryToSuperAdmins(): void
    {
        Gate::define('manageUsers', fn (User $user): bool => $user->isSuperAdmin());
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
