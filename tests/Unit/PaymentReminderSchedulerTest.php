<?php

use App\Business\Reminders\PaymentReminderScheduler;
use App\Data\Reminder\ReminderInvoiceData;
use App\Data\Reminder\ReminderSettings;
use App\Enums\ReminderType;
use App\Models\Invoice;
use App\Models\Lease;
use Carbon\Carbon;

function reminderInvoiceData(string $dueDate, string $amount = '1500.000'): ReminderInvoiceData
{
    return new ReminderInvoiceData(
        invoice: new Invoice(['id' => 10]),
        dueDate: Carbon::parse($dueDate)->startOfDay(),
        periodStart: '2026-07-01',
        periodEnd: '2026-07-31',
        amount: $amount,
        currency: 'IDR',
    );
}

test('schedules an upcoming reminder at the configured lead time', function () {
    $events = (new PaymentReminderScheduler)->pendingFor(
        new Lease(['id' => 1]),
        [reminderInvoiceData('2026-07-04')],
        new ReminderSettings(true, 3, []),
        Carbon::parse('2026-07-01'),
    );

    expect($events)->toHaveCount(1)
        ->and($events[0]->type)->toBe(ReminderType::Upcoming)
        ->and($events[0]->amount)->toBe('1500.000');
});

test('schedules every configured overdue interval reached', function () {
    $events = (new PaymentReminderScheduler)->pendingFor(
        new Lease(['id' => 1]),
        [reminderInvoiceData('2026-07-01')],
        new ReminderSettings(true, 3, [1, 3]),
        Carbon::parse('2026-07-04'),
    );

    expect($events)->toHaveCount(2)
        ->and(array_column($events, 'overdueDays'))->toBe([1, 3]);
});

test('does not schedule a reminder before its due date unless the lead time matches', function () {
    $events = (new PaymentReminderScheduler)->pendingFor(
        new Lease(['id' => 1]),
        [reminderInvoiceData('2026-07-04')],
        new ReminderSettings(true, 2, []),
        Carbon::parse('2026-07-01'),
    );

    expect($events)->toBeEmpty();
});
