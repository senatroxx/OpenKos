<?php

use App\Data\Reminder\ReminderEvent;
use App\Enums\ReminderType;
use App\Models\Lease;
use App\Models\Setting;
use App\Models\Unit;
use App\Notifications\Channels\LogChannel;
use App\Notifications\Channels\MailChannel;
use App\Notifications\RentReminder;
use App\Services\MailManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use OpenKOS\Core\Contracts\MailChannelNotification;
use OpenKOS\Core\Data\Mail\MailAttachment;
use OpenKOS\Core\Data\Mail\MailContent;
use OpenKOS\Core\Data\Mail\MailSendResult;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('MailChannel rejects notifications without MailChannelNotification interface', function () {
    $channel = app(MailChannel::class);
    $notifiable = (object) ['email' => 'user@example.com'];
    $invalidNotification = new class extends Notification {};

    expect(fn () => $channel->send($notifiable, $invalidNotification))->toThrow(InvalidArgumentException::class);
});

test('MailChannel logs warning and skips sending when notifiable has no email route', function () {
    Log::spy();

    $channel = app(MailChannel::class);
    $notifiable = (object) ['name' => 'No Email User'];

    $notification = new class extends Notification implements MailChannelNotification
    {
        public function toMailChannel(object $notifiable): MailContent
        {
            return new MailContent('Subject', 'Body');
        }
    };

    $channel->send($notifiable, $notification);

    Log::shouldHaveReceived('warning')->once();
});

test('MailChannel normalizes string and array recipient routes', function () {
    Setting::set('mail_config', ['driver' => 'log']);
    $managerMock = Mockery::mock(MailManager::class);
    $managerMock->shouldReceive('send')->once()->withArgs(function ($message) {
        return count($message->to) === 2
            && $message->to[0]->address === 'one@example.com'
            && $message->to[1]->address === 'two@example.com'
            && $message->to[1]->name === 'User Two';
    })->andReturn(new MailSendResult('test-id', 'Sent'));

    $channel = new MailChannel($managerMock);
    $notifiable = new class
    {
        public function routeNotificationForMail(): array
        {
            return ['one@example.com', 'two@example.com' => 'User Two'];
        }
    };

    $notification = new class extends Notification implements MailChannelNotification
    {
        public function toMailChannel(object $notifiable): MailContent
        {
            return new MailContent('Subject', 'Body');
        }
    };

    $channel->send($notifiable, $notification);
});

test('MailChannel forwards attachments to the mail manager', function () {
    $attachment = new MailAttachment('%PDF-test', 'invoice.pdf', 'application/pdf');
    $managerMock = Mockery::mock(MailManager::class);
    $managerMock->shouldReceive('send')
        ->once()
        ->withArgs(fn ($message): bool => $message->attachments === [$attachment])
        ->andReturn(new MailSendResult('test-id', 'Sent'));

    $channel = new MailChannel($managerMock);
    $notification = new class($attachment) extends Notification implements MailChannelNotification
    {
        public function __construct(private MailAttachment $attachment) {}

        public function toMailChannel(object $notifiable): MailContent
        {
            return new MailContent('Subject', 'Body', attachments: [$this->attachment]);
        }
    };

    $channel->send((object) ['email' => 'user@example.com'], $notification);
});

test('RentReminder via returns only configured channels', function () {
    Setting::set('reminder_channels', ['mail', 'log']);

    $lease = new Lease;
    $event = new ReminderEvent(
        lease: $lease,
        type: ReminderType::Upcoming,
        periodStart: '2026-08-01',
        periodEnd: '2026-08-31',
        dueDate: '2026-08-01',
        amount: 1500000,
        currency: 'IDR',
    );

    $reminder = new RentReminder($event);
    $via = $reminder->via((object) []);

    expect($via)->toBe(['database', LogChannel::class, MailChannel::class]);
});

test('RentReminder still renders events without invoice context', function () {
    $lease = new Lease;
    $lease->setRelation('unit', null);
    $event = new ReminderEvent(
        lease: $lease,
        type: ReminderType::Upcoming,
        periodStart: '2026-08-01',
        periodEnd: '2026-08-31',
        dueDate: '2026-08-01',
        amount: 150000000,
        currency: 'IDR',
    );

    $reminder = new RentReminder($event);
    $content = $reminder->toMailChannel((object) ['name' => 'Tenant']);

    expect($content->plainTextBody)
        ->toContain('Tenant')
        ->not->toContain('/portal/billing/invoices/');
    expect($reminder->shouldSend((object) [], 'mail'))->toBeTrue();
});

test('RentReminder replaces every documented custom template placeholder', function () {
    Setting::set('locale', 'en');
    Setting::set('reminder_message_template', ':name|:unit|:days|:amount|:date');

    $lease = new Lease;
    $lease->setRelation('unit', new Unit(['name' => 'A-01']));
    $event = new ReminderEvent(
        lease: $lease,
        type: ReminderType::Overdue,
        periodStart: '2026-08-01',
        periodEnd: '2026-08-31',
        dueDate: '2026-08-01',
        amount: 150000000,
        currency: 'IDR',
        overdueDays: 3,
    );

    $content = (new RentReminder($event))->toMailChannel((object) ['name' => 'Ayu']);

    expect($content->plainTextBody)->toContain('Ayu|A-01|3|')
        ->and($content->plainTextBody)->toContain('150,000,000')
        ->and($content->plainTextBody)->toContain('01 Aug 2026');
});

test('RentReminder selects templates by reminder type', function () {
    Setting::set('reminder_message_templates', [
        'upcoming' => 'Upcoming :name',
        'due_today' => 'Due today :name',
        'overdue' => 'Overdue :name :days',
    ]);

    $lease = new Lease;
    $lease->setRelation('unit', new Unit(['name' => 'A-01']));

    foreach ([
        [ReminderType::Upcoming, 'Upcoming Ayu'],
        [ReminderType::DueToday, 'Due today Ayu'],
        [ReminderType::Overdue, 'Overdue Ayu 3'],
    ] as [$type, $expected]) {
        $event = new ReminderEvent(
            lease: $lease,
            type: $type,
            periodStart: '2026-08-01',
            periodEnd: '2026-08-31',
            dueDate: '2026-08-01',
            amount: 150000000,
            currency: 'IDR',
            overdueDays: $type === ReminderType::Overdue ? 3 : null,
        );

        expect((new RentReminder($event))->toLog((object) ['name' => 'Ayu']))
            ->toBe($expected);
    }
});

test('RentReminder only includes optional invoice placeholders when configured', function () {
    Setting::set('reminder_message_templates', [
        'upcoming' => ':name|:invoice_context|:invoice_link',
        'due_today' => '',
        'overdue' => '',
    ]);

    $lease = new Lease;
    $lease->setRelation('unit', new Unit(['name' => 'A-01']));
    $event = new ReminderEvent(
        lease: $lease,
        type: ReminderType::Upcoming,
        periodStart: '2026-08-01',
        periodEnd: '2026-08-31',
        dueDate: '2026-08-01',
        amount: 150000000,
        currency: 'IDR',
    );

    expect((new RentReminder($event))->toLog((object) ['name' => 'Ayu']))
        ->toBe('Ayu||');
});
