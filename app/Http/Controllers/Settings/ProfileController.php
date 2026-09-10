<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->fill($request->safe()->except(['photo', 'remove_photo']));
        $user->forceFill($this->resolvePhoto($request, $user));

        // Stored the way Twilio dials it, whatever punctuation was typed.
        if ($request->has('phone')) {
            $user->phone = PhoneNumber::toE164($user->phone);
        }

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Work out what should happen to the photo on this request.
     *
     * A request that says nothing about the photo leaves it alone, so saving a
     * new name never wipes it -- the same rule the organization logo follows.
     *
     * @return array<string, string|null>
     */
    protected function resolvePhoto(ProfileUpdateRequest $request, User $user): array
    {
        if ($request->boolean('remove_photo')) {
            $this->deletePhoto($user->avatar_path);

            return ['avatar_path' => null];
        }

        if (! $request->hasFile('photo')) {
            return [];
        }

        $path = $request->file('photo')->store('avatars', 'public');

        if ($path === false) {
            throw new RuntimeException('The photo could not be stored.');
        }

        // Stored first, so a failed write cannot lose both.
        $this->deletePhoto($user->avatar_path);

        return ['avatar_path' => $path];
    }

    /**
     * Remove a stored photo, ignoring one that has already gone.
     */
    protected function deletePhoto(?string $path): void
    {
        if ($path !== null && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
