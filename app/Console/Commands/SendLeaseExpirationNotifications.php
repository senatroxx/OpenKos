<?php

namespace App\Console\Commands;

use App\Enums\LeaseStatus;
use App\Models\Lease;
use App\Notifications\TenantPortalNotification;
use App\Services\Localization\ApplicationLocale;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;

#[Signature('app:send-lease-expiration-notifications')]
#[Description('Notify tenants about leases ending in 30 days')]
class SendLeaseExpirationNotifications extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ApplicationLocale $locale): int
    {
        $locale->apply();

        Lease::query()
            ->where('status', LeaseStatus::Active)
            ->whereDate('end_date', today()->addDays(30))
            ->with('primaryTenant')
            ->each(function (Lease $lease) use ($locale): void {
                $tenant = $lease->primaryTenant;
                if (! $tenant) {
                    return;
                }

                $alreadySent = $tenant->notifications()
                    ->where('type', 'lease_expiring')
                    ->get()
                    ->contains(fn (DatabaseNotification $notification): bool => ($notification->data['lease_id'] ?? null) === $lease->id);

                if ($alreadySent) {
                    return;
                }

                $tenant->notify((new TenantPortalNotification([
                    'type' => 'lease_expiring',
                    'title' => __('Lease expiration reminder'),
                    'message' => __('Your lease ends on :date.', ['date' => $lease->end_date->locale($locale->current())->translatedFormat('d M Y')]),
                    'url' => route('portal.lease.show', $lease),
                    'lease_id' => $lease->id,
                ]))->locale($locale->current()));
            });

        return self::SUCCESS;
    }
}
