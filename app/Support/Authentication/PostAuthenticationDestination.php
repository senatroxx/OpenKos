<?php

namespace App\Support\Authentication;

use App\Models\User;
use Illuminate\Http\Request;

final class PostAuthenticationDestination
{
    public function resolve(Request $request, User $user): string
    {
        $intended = $request->session()->get('url.intended');

        if (is_string($intended) && $this->isAllowedIntended($intended, $user)) {
            return $intended;
        }

        if (in_array($request->string('redirect')->value(), ['portal', 'account'], true)) {
            return route('portal.dashboard', absolute: false);
        }

        return $this->canUseStaffDashboard($user)
            ? route('dashboard', absolute: false)
            : route('portal.dashboard', absolute: false);
    }

    private function isAllowedIntended(string $intended, User $user): bool
    {
        $path = parse_url($intended, PHP_URL_PATH);

        if (! is_string($path)) {
            return false;
        }

        if (str_starts_with($path, '/dashboard') && ! $this->canUseStaffDashboard($user)) {
            return false;
        }

        return ! $this->isTenantPath($path) || $user->hasTenantProfile();
    }

    private function isTenantPath(string $path): bool
    {
        return str_starts_with($path, '/portal/leases')
            || str_starts_with($path, '/portal/lease')
            || str_starts_with($path, '/portal/billing')
            || str_starts_with($path, '/portal/maintenance')
            || str_starts_with($path, '/portal/notifications');
    }

    private function canUseStaffDashboard(User $user): bool
    {
        return $user->isOwner() || $user->can('dashboard.view');
    }
}
