<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tenant\CompleteTenantInvitationRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Inertia\Inertia;
use Inertia\Response;

class TenantInvitationController extends Controller
{
    public function acceptInvitation(Request $request, string $token): Response
    {
        return Inertia::render('auth/tenant-accept-invitation', [
            'email' => $request->query('email', ''),
            'token' => $token,
            'passwordRules' => PasswordRule::defaults()->toPasswordRulesString(),
        ]);
    }

    public function completeInvitation(CompleteTenantInvitationRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = User::where('email', $validated['email'])->first();

        // Scope strictly to tenant users who currently have access: a pending invite
        // (invited_at) or an already-active account (a resend acts as a password reset).
        // A disabled/un-invited tenant (inactive with no pending invite) is rejected, so
        // this endpoint can never activate a staff account or revive disabled access.
        if (! $user || ! $user->tenant()->exists() || (! $user->is_active && ! $user->invited_at)) {
            return back()->withErrors(['email' => __('This invitation is invalid or inactive.')]);
        }

        $status = Password::broker()->reset($validated, function (User $user, string $password): void {
            $user->forceFill([
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'invited_at' => null,
                'is_active' => true,
            ])->save();
        });

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => __('This invitation link is invalid or has expired.')]);
        }

        return to_route('login')->with('status', __('Invitation accepted. You can now log in.'));
    }
}
