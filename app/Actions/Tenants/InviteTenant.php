<?php

namespace App\Actions\Tenants;

use App\Models\Tenant;
use App\Models\User;
use App\Notifications\TenantInvitation;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class InviteTenant
{
    public function execute(Tenant $tenant, string $email, bool $sendInvite = true): User
    {
        $user = User::create([
            'name' => $tenant->name,
            'email' => $email,
            'password' => Hash::make(Str::password(64)),
            'is_active' => false,
        ]);

        $tenant->user()->associate($user)->save();

        if ($sendInvite) {
            $this->sendInvitation($user);
        }

        return $user;
    }

    /**
     * Issue a fresh activation link and email it to the linked user. Shared by the
     * invite, resend, and tenant create/edit flows.
     */
    public function sendInvitation(User $user): void
    {
        // Active users can already sign in, so a resend is just a fresh link — don't
        // re-flag them as pending. Only mark genuinely un-activated users.
        if (! $user->is_active && ! $user->invited_at) {
            $user->update(['invited_at' => now()]);
        }

        /** @var PasswordBroker $broker */
        $broker = Password::broker();
        $token = $broker->createToken($user);

        $user->notify(new TenantInvitation(
            route('tenants.invitations.accept', ['token' => $token, 'email' => $user->email]),
        ));
    }
}
