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
            // invited_at marks a pending activation; skip it when only registering
            // the email for notifications (no portal invite sent).
            'invited_at' => $sendInvite ? now() : null,
        ]);

        $tenant->user()->associate($user)->save();

        if ($sendInvite) {
            /** @var PasswordBroker $broker */
            $broker = Password::broker();
            $token = $broker->createToken($user);

            $user->notify(new TenantInvitation(
                route('tenants.invitations.accept', ['token' => $token, 'email' => $user->email]),
            ));
        }

        return $user;
    }
}
