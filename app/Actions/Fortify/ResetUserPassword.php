<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and reset the user's forgotten password.
     *
     * @param  array<string, string>  $input
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        $user->forceFill([
            'password' => $input['password'],
            /*
             * Completing an emailed reset link proves the same mailbox
             * ownership the verification email exists to prove, and imported
             * shell accounts arrive unverified — without this they would
             * chase a third email before ever seeing the app.
             */
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();
    }
}
