<?php

use App\Actions\Invoices\GenerateInvoices;
use App\Actions\Reminders\SendRentReminders;
use App\Business\Reminders\PaymentReminderScheduler;
use App\Data\Reminder\ReminderEvent;
use App\Data\Reminder\ReminderSettings;
use App\Enums\InvoiceStatus;
use App\Enums\ReminderType;
use App\Jobs\GenerateInvoicePdfArtifact;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Property;
use App\Models\ReminderLog;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\RentReminder;
use App\Repositories\ReminderRepository;
use App\Services\Invoices\InvoicePdfArtifact;
use Carbon\Carbon;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    Setting::set('invoice_pdf_enabled', true, 'boolean');
    Storage::fake('local');
});

function prepareReminderInvoicePdf(Invoice $invoice): void
{
    $artifact = app(InvoicePdfArtifact::class);

    GenerateInvoicePdfArtifact::dispatchSync(
        $invoice->getKey(),
        $artifact->fingerprint($invoice),
    );
}

function createLeaseWithTenant(array $overrides = []): Lease
{
    $property = Property::factory()->create();
    $unit = Unit::factory()->for($property)->create();
    $tenant = Tenant::factory()->create(['phone' => '628123456789']);

    $lease = Lease::factory()->create(array_merge([
        'unit_id' => $unit->id,
        'primary_tenant_id' => $tenant->id,
        'start_date' => now()->subMonths(3),
        'rent_amount' => 1500000.00,
        'rent_due_day' => 1,
        'billing_interval' => 1,
        'billing_unit' => 'month',
        'status' => 'active',
    ], $overrides));

    app(GenerateInvoices::class)->execute($lease);

    return $lease;
}

function reminderEventFor(Lease $lease, ReminderType $type, ?int $overdueDays = null): ReminderEvent
{
    return new ReminderEvent(
        lease: $lease,
        type: $type,
        periodStart: '2026-07-01',
        periodEnd: '2026-07-31',
        dueDate: '2026-07-01',
        amount: 1_500_000,
        currency: 'IDR',
        overdueDays: $overdueDays,
    );
}

describe('PaymentReminderScheduler', function () {
    it('returns upcoming event when days match', function () {
        Carbon::setTestNow(Carbon::parse('2026-06-28'));
        $lease = createLeaseWithTenant(['rent_due_day' => 1, 'start_date' => '2026-06-01']);
        $settings = new ReminderSettings(true, 3, []);

        $events = (new PaymentReminderScheduler)->pendingFor($lease, $settings);

        expect($events)->toHaveCount(1);
        expect($events[0]->type->value)->toBe('upcoming');
        expect($events[0]->dueDate)->toBe('2026-07-01');
        expect($events[0]->invoice)->not->toBeNull();
        expect($events[0]->invoice?->due_date->toDateString())->toBe('2026-07-01');

        Carbon::setTestNow();
    });

    it('returns due today event', function () {
        Carbon::setTestNow(Carbon::parse('2026-07-01'));
        $lease = createLeaseWithTenant(['rent_due_day' => 1, 'start_date' => '2026-06-01']);
        $settings = new ReminderSettings(true, 3, []);

        $events = (new PaymentReminderScheduler)->pendingFor($lease, $settings);

        expect($events)->toHaveCount(1);
        expect($events[0]->type->value)->toBe('due_today');

        Carbon::setTestNow();
    });

    it('preserves fractional outstanding amounts', function () {
        Carbon::setTestNow(Carbon::parse('2026-07-01'));
        $lease = createLeaseWithTenant(['rent_due_day' => 1, 'start_date' => '2026-06-01']);
        $lease->invoices()->whereDate('due_date', '2026-07-01')->firstOrFail()->update([
            'total' => '0.29',
            'amount_paid' => '0.00',
        ]);
        $settings = new ReminderSettings(true, 3, []);

        $events = (new PaymentReminderScheduler)->pendingFor($lease, $settings);

        expect($events)->toHaveCount(1);
        expect($events[0]->amount)->toBe('0.290');

        Carbon::setTestNow();
    });

    it('returns overdue event at configured interval', function () {
        Carbon::setTestNow(Carbon::parse('2026-07-02'));
        $lease = createLeaseWithTenant(['rent_due_day' => 1, 'start_date' => '2026-07-01']);
        $settings = new ReminderSettings(true, 3, [1, 3, 7]);

        $events = (new PaymentReminderScheduler)->pendingFor($lease, $settings);

        expect($events)->toHaveCount(1);
        expect($events[0]->type->value)->toBe('overdue');
        expect($events[0]->overdueDays)->toBe(1);

        Carbon::setTestNow();
    });

    it('returns overdue for intervals well past the max', function () {
        Carbon::setTestNow(Carbon::parse('2026-08-10'));
        $lease = createLeaseWithTenant(['rent_due_day' => 1, 'start_date' => '2024-01-01']);
        $settings = new ReminderSettings(true, 3, [7]);

        $events = (new PaymentReminderScheduler)->pendingFor($lease, $settings);

        $overdueEvents = array_filter($events, fn ($e) => $e->type->value === 'overdue');
        expect($overdueEvents)->not->toBeEmpty();
        foreach ($overdueEvents as $event) {
            expect($event->overdueDays)->toBe(7);
        }

        Carbon::setTestNow();
    });

    it('returns no events when lease is paid', function () {
        Carbon::setTestNow(Carbon::parse('2026-07-01'));
        $lease = createLeaseWithTenant(['rent_due_day' => 1, 'start_date' => '2026-06-01']);

        $lease->invoices()->get()->each->update([
            'status' => InvoiceStatus::Paid,
        ]);

        $settings = new ReminderSettings(true, 3, []);
        $events = (new PaymentReminderScheduler)->pendingFor($lease, $settings);

        expect($events)->toBeEmpty();

        Carbon::setTestNow();
    });

    it('suppresses a queued reminder when its invoice is no longer payable', function () {
        Carbon::setTestNow(Carbon::parse('2026-07-01'));
        $lease = createLeaseWithTenant(['rent_due_day' => 1, 'start_date' => '2026-06-01']);
        $settings = new ReminderSettings(true, 3, []);
        $event = (new PaymentReminderScheduler)->pendingFor($lease, $settings)[0];
        $queuedReminder = unserialize(serialize(new RentReminder($event)));

        expect($queuedReminder->shouldSend($lease->primaryTenant, 'mail'))->toBeTrue();

        $event->invoice?->update(['status' => InvoiceStatus::Paid]);

        expect($queuedReminder->shouldSend($lease->primaryTenant, 'mail'))->toBeFalse();

        Carbon::setTestNow();
    });
});

describe('ReminderRepository', function () {
    it('records a new reminder with one insert attempt', function () {
        $lease = createLeaseWithTenant();
        $queries = [];

        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            if (str_contains(strtolower($query->sql), 'reminder_logs')) {
                $queries[] = $query->sql;
            }
        });

        $log = app(ReminderRepository::class)->recordIfAbsent(
            reminderEventFor($lease, ReminderType::Upcoming),
        );

        expect($log)->toBeInstanceOf(ReminderLog::class)
            ->and($log?->overdue_days)->toBe(ReminderLog::NON_OVERDUE_DAYS)
            ->and($queries)->toHaveCount(1)
            ->and(strtolower($queries[0]))->toContain('insert');
    });

    it('ignores duplicate reminder keys', function (ReminderType $type, ?int $overdueDays) {
        $lease = createLeaseWithTenant();
        $repository = app(ReminderRepository::class);
        $event = reminderEventFor($lease, $type, $overdueDays);

        expect($repository->recordIfAbsent($event))->toBeInstanceOf(ReminderLog::class)
            ->and($repository->recordIfAbsent($event))->toBeNull()
            ->and(ReminderLog::count())->toBe(1);
    })->with([
        'upcoming' => [ReminderType::Upcoming, null],
        'due today' => [ReminderType::DueToday, null],
        'overdue' => [ReminderType::Overdue, 1],
    ]);

    it('keeps upcoming and due-today reminder keys distinct', function () {
        $lease = createLeaseWithTenant();
        $repository = app(ReminderRepository::class);

        $upcoming = $repository->recordIfAbsent(
            reminderEventFor($lease, ReminderType::Upcoming),
        );
        $dueToday = $repository->recordIfAbsent(
            reminderEventFor($lease, ReminderType::DueToday),
        );

        expect($upcoming)->toBeInstanceOf(ReminderLog::class)
            ->and($dueToday)->toBeInstanceOf(ReminderLog::class)
            ->and(ReminderLog::count())->toBe(2);
    });

    it('preserves zero overdue days', function () {
        $lease = createLeaseWithTenant();

        $log = app(ReminderRepository::class)->recordIfAbsent(
            reminderEventFor($lease, ReminderType::Overdue, 0),
        );

        expect($log?->overdue_days)->toBe(0);
    });

    it('rethrows foreign-key violations', function () {
        $lease = createLeaseWithTenant();
        $lease->id = PHP_INT_MAX;

        expect(fn () => app(ReminderRepository::class)->recordIfAbsent(
            reminderEventFor($lease, ReminderType::Upcoming),
        ))->toThrow(QueryException::class);
    });

    it('rethrows unrelated unique constraint violations', function () {
        Schema::table('reminder_logs', function (Blueprint $table): void {
            $table->unique('channel', 'reminder_logs_channel_unique');
        });

        try {
            ReminderLog::factory()->create(['channel' => 'whatsapp']);
            $lease = createLeaseWithTenant();

            expect(fn () => app(ReminderRepository::class)->recordIfAbsent(
                reminderEventFor($lease, ReminderType::Upcoming),
            ))->toThrow(UniqueConstraintViolationException::class);
        } finally {
            Schema::table('reminder_logs', function (Blueprint $table): void {
                $table->dropUnique('reminder_logs_channel_unique');
            });
        }
    });
});

describe('SendRentRemindersAction', function () {
    beforeEach(function () {
        Setting::set('reminder_channels', ['whatsapp'], 'array');
    });

    it('dispatches event and creates log', function () {
        Carbon::setTestNow(Carbon::parse('2026-07-01'));
        Notification::fake();

        $lease = createLeaseWithTenant(['rent_due_day' => 1, 'start_date' => '2026-07-01']);
        $lease->load(['primaryTenant']);

        $action = app(SendRentReminders::class);
        $action->execute($lease);

        expect(ReminderLog::count())->toBe(1);
        Notification::assertSentTo($lease->primaryTenant, RentReminder::class);

        Carbon::setTestNow();
    });

    it('does not send duplicate reminders', function () {
        Carbon::setTestNow(Carbon::parse('2026-07-01'));
        Notification::fake();

        $lease = createLeaseWithTenant(['rent_due_day' => 1, 'start_date' => '2026-07-01']);
        $lease->load(['primaryTenant']);

        $action = app(SendRentReminders::class);
        $first = $action->execute($lease);
        $second = $action->execute($lease);

        expect($first)->toHaveCount(1);
        expect($second)->toBeEmpty();
        expect(ReminderLog::count())->toBe(1);

        Notification::assertSentToTimes($lease->primaryTenant, RentReminder::class, 1);

        Carbon::setTestNow();
    });

    it('does nothing when reminders disabled', function () {
        Setting::set('reminder_enabled', false, 'boolean');

        Carbon::setTestNow(Carbon::parse('2026-07-01'));
        Notification::fake();

        $lease = createLeaseWithTenant(['rent_due_day' => 1]);
        $lease->load(['primaryTenant']);

        $action = app(SendRentReminders::class);
        $sent = $action->execute($lease);

        expect($sent)->toBeEmpty();
        Notification::assertNothingSent();

        Carbon::setTestNow();
    });

    it('skips tenants without phone', function () {
        Carbon::setTestNow(Carbon::parse('2026-07-01'));
        Notification::fake();

        $property = Property::factory()->create();
        $unit = Unit::factory()->for($property)->create();
        $tenant = Tenant::factory()->create(['phone' => null]);

        $lease = Lease::factory()->create([
            'unit_id' => $unit->id,
            'primary_tenant_id' => $tenant->id,
            'start_date' => '2026-06-01',
            'rent_amount' => 1500000.00,
            'rent_due_day' => 1,
            'billing_interval' => 1,
            'billing_unit' => 'month',
            'status' => 'active',
        ]);
        $lease->load(['primaryTenant']);

        $action = app(SendRentReminders::class);
        $sent = $action->execute($lease);

        expect($sent)->toBeEmpty();
        Notification::assertNothingSent();

        Carbon::setTestNow();
    });

    it('does not treat a phone as a mail contact', function () {
        Carbon::setTestNow(Carbon::parse('2026-07-01'));
        Setting::set('reminder_channels', ['mail'], 'array');
        Notification::fake();

        $lease = createLeaseWithTenant(['rent_due_day' => 1, 'start_date' => '2026-07-01']);
        $lease->load(['primaryTenant.user']);

        $sent = app(SendRentReminders::class)->execute($lease);

        expect($sent)->toBeEmpty();
        expect(ReminderLog::count())->toBe(0);
        Notification::assertNothingSent();

        Carbon::setTestNow();
    });

    it('emails an invited but unverified tenant', function () {
        Carbon::setTestNow(Carbon::parse('2026-07-01'));
        Setting::set('reminder_channels', ['mail'], 'array');
        Notification::fake();

        $property = Property::factory()->create();
        $unit = Unit::factory()->for($property)->create();
        $user = User::factory()->create([
            'email' => 'tenant@example.com',
            'is_active' => false,
            'email_verified_at' => null,
            'invited_at' => now(),
        ]);
        $tenant = Tenant::factory()->create(['phone' => null, 'user_id' => $user->id]);

        $lease = Lease::factory()->create([
            'unit_id' => $unit->id,
            'primary_tenant_id' => $tenant->id,
            'start_date' => '2026-06-01',
            'rent_amount' => 1500000.00,
            'rent_due_day' => 1,
            'billing_interval' => 1,
            'billing_unit' => 'month',
            'status' => 'active',
        ]);
        app(GenerateInvoices::class)->execute($lease);
        $lease->load(['primaryTenant']);

        $sent = app(SendRentReminders::class)->execute($lease);

        expect($sent)->not->toBeEmpty();
        Notification::assertSentTo($lease->primaryTenant, RentReminder::class);

        Carbon::setTestNow();
    });

    it('includes invoice context and portal link in scheduled mail reminders', function () {
        Carbon::setTestNow(Carbon::parse('2026-07-01'));
        Setting::set('reminder_channels', ['mail'], 'array');
        Notification::fake();

        $user = User::factory()->create(['email' => 'tenant@example.com']);
        $lease = createLeaseWithTenant(['rent_due_day' => 1, 'start_date' => '2026-07-01']);
        $lease->primaryTenant->update([
            'phone' => null,
            'user_id' => $user->id,
        ]);
        $lease->load(['primaryTenant.user']);
        $tenant = $lease->primaryTenant;
        $invoice = $lease->invoices()->payable()->orderBy('period_start')->firstOrFail();
        prepareReminderInvoicePdf($invoice);

        app(SendRentReminders::class)->execute($lease);

        Notification::assertSentTo(
            $tenant,
            RentReminder::class,
            function (RentReminder $notification) use ($invoice, $tenant): bool {
                $content = $notification->toMailChannel($tenant);
                $invoiceUrl = route('portal.billing.invoices.show', $invoice);

                expect($content->plainTextBody)
                    ->toContain($invoice->reference)
                    ->toContain($invoice->period_start->format('d M Y'))
                    ->toContain($invoice->period_end->format('d M Y'))
                    ->toContain($invoice->due_date->format('d M Y'))
                    ->toContain('1.500.000')
                    ->toContain($invoiceUrl);
                expect($content->htmlBody)->toContain($invoiceUrl);
                expect($content->attachments)->toHaveCount(1);
                expect($content->attachments[0]->filename)->toBe("invoice-{$invoice->reference}.pdf");
                expect($content->attachments[0]->mimeType)->toBe('application/pdf');
                expect($content->attachments[0]->content)->toStartWith('%PDF-');

                return true;
            },
        );

        Carbon::setTestNow();
    });

    it('includes invoice context without a portal link for whatsapp-only tenants', function () {
        Carbon::setTestNow(Carbon::parse('2026-07-01'));
        Notification::fake();

        $lease = createLeaseWithTenant(['rent_due_day' => 1, 'start_date' => '2026-07-01']);
        $lease->load(['primaryTenant']);
        $tenant = $lease->primaryTenant;
        $invoice = $lease->invoices()->payable()->orderBy('period_start')->firstOrFail();
        prepareReminderInvoicePdf($invoice);

        app(SendRentReminders::class)->execute($lease);

        Notification::assertSentTo(
            $tenant,
            RentReminder::class,
            function (RentReminder $notification) use ($invoice, $tenant): bool {
                $content = $notification->toWhatsAppChannel($tenant);
                $message = $content->message;

                expect($message)
                    ->toContain($invoice->reference)
                    ->toContain($invoice->period_start->format('d M Y'))
                    ->toContain($invoice->period_end->format('d M Y'))
                    ->toContain($invoice->due_date->format('d M Y'))
                    ->toContain('1.500.000')
                    ->not->toContain(route('portal.billing.invoices.show', $invoice));

                $attachment = $content->attachment;

                expect($attachment)->not->toBeNull();
                expect($attachment->filename)->toBe("invoice-{$invoice->reference}.pdf");
                expect($attachment->mimeType)->toBe('application/pdf');
                expect($attachment->content)->toStartWith('%PDF-');

                return true;
            },
        );

        Carbon::setTestNow();
    });

    it('omits invoice attachments when PDF generation is disabled', function () {
        Carbon::setTestNow(Carbon::parse('2026-07-01'));
        Setting::set('invoice_pdf_enabled', false, 'boolean');
        Setting::set('reminder_channels', ['mail'], 'array');
        Notification::fake();

        $user = User::factory()->create(['email' => 'tenant@example.com']);
        $lease = createLeaseWithTenant(['rent_due_day' => 1, 'start_date' => '2026-07-01']);
        $lease->primaryTenant->update([
            'phone' => null,
            'user_id' => $user->id,
        ]);
        $lease->load(['primaryTenant.user']);
        $tenant = $lease->primaryTenant;

        app(SendRentReminders::class)->execute($lease);

        Notification::assertSentTo(
            $tenant,
            RentReminder::class,
            function (RentReminder $notification) use ($tenant): bool {
                expect($notification->toMailChannel($tenant)->attachments)->toBeEmpty();

                return true;
            },
        );

        Carbon::setTestNow();
    });

    it('uses a custom scheduled template without optional invoice sections', function () {
        Carbon::setTestNow(Carbon::parse('2026-06-28'));
        Setting::set('reminder_message_templates', [
            'upcoming' => 'Custom reminder for :name',
            'due_today' => '',
            'overdue' => '',
        ], 'array');
        Notification::fake();

        $lease = createLeaseWithTenant(['rent_due_day' => 1, 'start_date' => '2026-07-01']);
        $lease->load(['primaryTenant']);
        $tenant = $lease->primaryTenant;

        app(SendRentReminders::class)->execute($lease);

        Notification::assertSentTo(
            $tenant,
            RentReminder::class,
            function (RentReminder $notification) use ($tenant): bool {
                expect($notification->toWhatsAppChannel($tenant)->message)
                    ->toBe('Custom reminder for '.$tenant->name);

                return true;
            },
        );

        Carbon::setTestNow();
    });

    it('includes the portal link in scheduled whatsapp reminders for portal tenants', function () {
        Carbon::setTestNow(Carbon::parse('2026-07-01'));
        Notification::fake();

        $user = User::factory()->create();
        $lease = createLeaseWithTenant(['rent_due_day' => 1, 'start_date' => '2026-07-01']);
        $lease->primaryTenant->update(['user_id' => $user->id]);
        $lease->load(['primaryTenant.user']);
        $tenant = $lease->primaryTenant;
        $invoice = $lease->invoices()->payable()->orderBy('period_start')->firstOrFail();

        app(SendRentReminders::class)->execute($lease);

        Notification::assertSentTo(
            $tenant,
            RentReminder::class,
            function (RentReminder $notification) use ($invoice, $tenant): bool {
                expect($notification->toWhatsAppChannel($tenant)->message)
                    ->toContain(route('portal.billing.invoices.show', $invoice));

                return true;
            },
        );

        Carbon::setTestNow();
    });

    it('omits portal links for users without portal access', function (array $userAttributes) {
        Carbon::setTestNow(Carbon::parse('2026-07-01'));
        Notification::fake();

        $user = User::factory()->create($userAttributes);
        $lease = createLeaseWithTenant(['rent_due_day' => 1, 'start_date' => '2026-07-01']);
        $lease->primaryTenant->update(['user_id' => $user->id]);
        $lease->load(['primaryTenant.user']);
        $tenant = $lease->primaryTenant;
        $invoice = $lease->invoices()->payable()->orderBy('period_start')->firstOrFail();

        app(SendRentReminders::class)->execute($lease);

        Notification::assertSentTo(
            $tenant,
            RentReminder::class,
            function (RentReminder $notification) use ($invoice, $tenant): bool {
                expect($notification->toWhatsAppChannel($tenant)->message)
                    ->not->toContain(route('portal.billing.invoices.show', $invoice));
                expect($notification->toArray($tenant)['url'])->toBeNull();

                return true;
            },
        );

        Carbon::setTestNow();
    })->with([
        'unverified user' => [['email_verified_at' => null]],
        'inactive user' => [['is_active' => false]],
    ]);
});

describe('Command', function () {
    beforeEach(function () {
        Setting::set('reminder_channels', ['whatsapp'], 'array');
    });

    it('sends reminders via artisan command', function () {
        Carbon::setTestNow(Carbon::parse('2026-07-01'));
        Notification::fake();

        $lease = createLeaseWithTenant(['rent_due_day' => 1, 'start_date' => '2026-07-01']);
        $lease->load(['primaryTenant']);

        $this->artisan('rent:send-reminders')
            ->expectsOutputToContain('Sent')
            ->assertSuccessful();

        Carbon::setTestNow();
    });

    it('handles manual send for single lease', function () {
        Carbon::setTestNow(Carbon::parse('2026-07-01'));
        Notification::fake();

        $lease = createLeaseWithTenant(['rent_due_day' => 1, 'start_date' => '2026-07-01']);
        $lease->load(['primaryTenant']);

        $this->artisan('rent:send-reminders', ['lease' => $lease->id])
            ->expectsOutputToContain('Sent')
            ->assertSuccessful();

        Carbon::setTestNow();
    });
});

describe('Manual Send via Controller', function () {
    beforeEach(function () {
        Setting::set('reminder_channels', ['whatsapp'], 'array');
    });

    it('allows user with reminders.send permission to send reminder', function () {
        Carbon::setTestNow(Carbon::parse('2026-07-01'));
        Notification::fake();

        $owner = User::factory()->owner()->create();
        $lease = createLeaseWithTenant(['rent_due_day' => 1, 'start_date' => '2026-07-01']);
        $lease->unit->property->users()->attach($owner);

        $this->actingAs($owner)
            ->post(route('leases.send-reminder', $lease))
            ->assertRedirect();

        expect(ReminderLog::count())->toBe(1);

        Carbon::setTestNow();
    });

    it('includes the selected invoice and portal link in a manual fallback reminder', function () {
        Carbon::setTestNow(Carbon::parse('2026-06-20'));
        Setting::set('reminder_channels', ['mail'], 'array');
        Notification::fake();

        $owner = User::factory()->owner()->create();
        $tenantUser = User::factory()->create(['email' => 'tenant@example.com']);
        $lease = createLeaseWithTenant(['rent_due_day' => 1, 'start_date' => '2026-07-01']);
        $lease->primaryTenant->update([
            'phone' => null,
            'user_id' => $tenantUser->id,
        ]);
        $lease->unit->property->users()->attach($owner);
        $lease->load(['primaryTenant.user']);
        $tenant = $lease->primaryTenant;
        $invoice = $lease->invoices()->payable()->orderBy('period_start')->firstOrFail();
        prepareReminderInvoicePdf($invoice);

        $this->actingAs($owner)
            ->post(route('leases.send-reminder', $lease))
            ->assertRedirect();

        expect(ReminderLog::count())->toBe(1);
        Notification::assertSentTo(
            $tenant,
            RentReminder::class,
            function (RentReminder $notification) use ($invoice, $tenant): bool {
                $content = $notification->toMailChannel($tenant);

                expect($content->plainTextBody)
                    ->toContain($invoice->reference)
                    ->toContain(route('portal.billing.invoices.show', $invoice));
                expect($content->attachments)->toHaveCount(1);
                expect($content->attachments[0]->filename)->toBe("invoice-{$invoice->reference}.pdf");
                expect($content->attachments[0]->content)->toStartWith('%PDF-');

                return true;
            },
        );

        Carbon::setTestNow();
    });

    it('includes the portal link in a manual whatsapp reminder for portal tenants', function () {
        Carbon::setTestNow(Carbon::parse('2026-06-20'));
        Setting::set('reminder_channels', ['whatsapp'], 'array');
        Notification::fake();

        $owner = User::factory()->owner()->create();
        $tenantUser = User::factory()->create();
        $lease = createLeaseWithTenant(['rent_due_day' => 1, 'start_date' => '2026-07-01']);
        $lease->primaryTenant->update(['user_id' => $tenantUser->id]);
        $lease->unit->property->users()->attach($owner);
        $lease->load(['primaryTenant.user']);
        $tenant = $lease->primaryTenant;
        $invoice = $lease->invoices()->payable()->orderBy('period_start')->firstOrFail();

        $this->actingAs($owner)
            ->post(route('leases.send-reminder', $lease))
            ->assertRedirect();

        Notification::assertSentTo(
            $tenant,
            RentReminder::class,
            function (RentReminder $notification) use ($invoice, $tenant): bool {
                expect($notification->toWhatsAppChannel($tenant)->message)
                    ->toContain(route('portal.billing.invoices.show', $invoice));

                return true;
            },
        );

        Carbon::setTestNow();
    });

    it('uses a custom manual template without optional invoice sections', function () {
        Carbon::setTestNow(Carbon::parse('2026-06-20'));
        Setting::set('reminder_channels', ['mail'], 'array');
        Setting::set('reminder_message_templates', [
            'upcoming' => 'Manual reminder for :name',
            'due_today' => '',
            'overdue' => '',
        ], 'array');
        Notification::fake();

        $owner = User::factory()->owner()->create();
        $tenantUser = User::factory()->create(['email' => 'tenant@example.com']);
        $lease = createLeaseWithTenant(['rent_due_day' => 1, 'start_date' => '2026-07-01']);
        $lease->primaryTenant->update([
            'phone' => null,
            'user_id' => $tenantUser->id,
        ]);
        $lease->unit->property->users()->attach($owner);
        $lease->load(['primaryTenant.user']);
        $tenant = $lease->primaryTenant;

        $this->actingAs($owner)
            ->post(route('leases.send-reminder', $lease))
            ->assertRedirect();

        Notification::assertSentTo(
            $tenant,
            RentReminder::class,
            function (RentReminder $notification) use ($tenant): bool {
                expect($notification->toMailChannel($tenant)->plainTextBody)
                    ->toBe('Manual reminder for '.$tenant->name);

                return true;
            },
        );

        Carbon::setTestNow();
    });

    it('denies user without reminders.send permission', function () {
        Carbon::setTestNow(Carbon::parse('2026-07-01'));

        $admin = User::factory()->admin()->create();
        $admin->revokePermissionTo('reminders.send');

        $lease = createLeaseWithTenant(['rent_due_day' => 1, 'start_date' => '2026-07-01']);
        $lease->unit->property->users()->attach($admin);

        $this->actingAs($admin)
            ->post(route('leases.send-reminder', $lease))
            ->assertForbidden();

        Carbon::setTestNow();
    });
});
