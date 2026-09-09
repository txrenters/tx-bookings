<?php

namespace App\Actions\Fortify;

use App\Actions\Scheduling\ApplyDefaultHolidays;
use App\Actions\Scheduling\CreateDefaultAvailability;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as ValidatorInstance;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(
        private CreateDefaultAvailability $createDefaultAvailability,
        private ApplyDefaultHolidays $applyDefaultHolidays,
    ) {
        //
    }

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ], [
            /*
             * The Calendly importer creates shell accounts with unusable
             * passwords, so an invited person may "exist" without ever having
             * registered. The default unique message reads as a dead end;
             * point them at the flow that actually gets them in.
             */
            'email.unique' => __('This address already has an account. Use "Forgot password?" on the log-in page to set a password.'),
        ])->after(function (ValidatorInstance $validator) use ($input) {
            /**
             * Self-service signup is closed: this is an internal system, so an
             * account may only be created for an address someone has actually
             * invited. This is the real boundary — the register view redirect
             * only keeps the form out of sight.
             */
            if ($validator->errors()->has('email')) {
                return;
            }

            $isInvited = TeamInvitation::query()
                ->whereRaw('lower(email) = ?', [mb_strtolower((string) ($input['email'] ?? ''))])
                ->pending()
                ->exists();

            if (! $isInvited) {
                $validator->errors()->add('email', __('Registration is by invitation only. Ask an administrator to invite you.'));
            }
        })->validate();

        return DB::transaction(function () use ($input) {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);

            /*
             * No personal organization: registration is invitation-only, so the
             * account belongs in the organization that invited it, with the
             * role the invitation named.
             */
            $invitation = TeamInvitation::query()
                ->whereRaw('lower(email) = ?', [mb_strtolower($input['email'])])
                ->pending()
                ->first();

            if ($invitation !== null) {
                $invitation->team->memberships()->create([
                    'user_id' => $user->id,
                    'role' => $invitation->role,
                ]);

                $user->forceFill(['current_team_id' => $invitation->team_id])->save();
                $invitation->update(['accepted_at' => now()]);
            }

            $this->createDefaultAvailability->handle($user);
            $this->applyDefaultHolidays->handle($user);

            return $user;
        });
    }
}
