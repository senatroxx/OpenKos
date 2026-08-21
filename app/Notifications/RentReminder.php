<?php

namespace App\Notifications;

use App\Data\Reminder\ReminderEvent;
use App\Enums\ReminderType;
use App\Models\Invoice;
use App\Models\Setting;
use App\Models\Tenant;
use App\Notifications\Channels\LogChannel;
use App\Notifications\Channels\MailChannel;
use App\Notifications\Channels\WhatsAppChannel;
use App\Services\Invoices\InvoicePdfArtifact;
use App\Services\Payments\MoneyConverter;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use OpenKOS\Core\Contracts\MailChannelNotification;
use OpenKOS\Core\Contracts\WhatsAppChannelNotification;
use OpenKOS\Core\Data\Mail\MailAttachment;
use OpenKOS\Core\Data\Mail\MailContent;
use OpenKOS\Core\Data\WhatsApp\WhatsAppAttachment;
use OpenKOS\Core\Data\WhatsApp\WhatsAppContent;

class RentReminder extends Notification implements MailChannelNotification, ShouldQueue, WhatsAppChannelNotification
{
    use Queueable;

    private ?string $invoicePdfContent = null;

    public function __construct(private ReminderEvent $event) {}

    public function via(object $notifiable): array
    {
        $map = [
            'database' => 'database',
            'log' => LogChannel::class,
            'whatsapp' => WhatsAppChannel::class,
            'mail' => MailChannel::class,
        ];

        $channels = Setting::get('reminder_channels') ?? ['log'];

        return array_values(array_intersect_key($map, array_flip(['database', ...$channels])));
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        $invoice = $this->invoice();

        if (! $invoice?->getKey()) {
            return true;
        }

        return Invoice::query()->payable()->whereKey($invoice->getKey())->exists();
    }

    public function toMailChannel(object $notifiable): MailContent
    {
        $subject = match ($this->event->type) {
            ReminderType::Upcoming => __('Rent Reminder'),
            ReminderType::DueToday => __('Rent Due Today'),
            ReminderType::Overdue => __('Rent Overdue'),
        };

        $messageText = $this->renderMessage($notifiable);
        $invoiceUrl = $this->invoiceUrl($notifiable);
        $escapedMessage = e($messageText);

        if ($invoiceUrl) {
            $escapedMessage = str_replace(
                e($invoiceUrl),
                '<a href="'.e($invoiceUrl).'">'.e($invoiceUrl).'</a>',
                $escapedMessage,
            );
        }

        $htmlBody = '<div>'.nl2br($escapedMessage).'</div>';
        $plainTextBody = $messageText;
        $invoice = $this->invoice();

        $attachments = [];
        if ($invoice && $notifiable instanceof Tenant && $notifiable->user?->email) {
            $reference = preg_replace(
                '/[^A-Za-z0-9_-]/',
                '-',
                $invoice->reference ?? (string) $invoice->getKey(),
            );

            if ($content = $this->invoicePdfContent($invoice)) {
                $attachments[] = new MailAttachment(
                    content: $content,
                    filename: 'invoice-'.($reference ?: $invoice->getKey()).'.pdf',
                    mimeType: 'application/pdf',
                );
            }
        }

        return new MailContent(
            subject: $subject,
            htmlBody: $htmlBody,
            plainTextBody: $plainTextBody,
            attachments: $attachments,
        );
    }

    public function toWhatsAppChannel(object $notifiable): WhatsAppContent
    {
        $attachment = null;
        $invoice = $this->invoice();

        if ($invoice && ($content = $this->invoicePdfContent($invoice))) {
            $reference = preg_replace(
                '/[^A-Za-z0-9_-]/',
                '-',
                $invoice->reference ?? (string) $invoice->getKey(),
            );

            $attachment = new WhatsAppAttachment(
                content: $content,
                filename: 'invoice-'.($reference ?: $invoice->getKey()).'.pdf',
                mimeType: 'application/pdf',
            );
        }

        return new WhatsAppContent(
            message: $this->renderMessage($notifiable),
            attachment: $attachment,
        );
    }

    public function toLog(object $notifiable): string
    {
        return $this->renderMessage($notifiable);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $invoice = $this->invoice();

        return [
            'type' => 'rent_reminder',
            'title' => __('Rent reminder'),
            'message' => $this->renderMessage($notifiable),
            'url' => $this->portalUrl($notifiable, $invoice),
        ];
    }

    public function databaseType(object $notifiable): string
    {
        return 'rent_reminder';
    }

    public function toWhatsApp(object $notifiable): string
    {
        return $this->renderMessage($notifiable);
    }

    private function renderMessage(object $notifiable): string
    {
        $days = $this->event->overdueDays
            ?? (int) now()->startOfDay()->diffInDays(Carbon::parse($this->event->dueDate), false);

        $currency = app(MoneyConverter::class)->normalizeCurrency($this->event->currency);
        $amount = app(MoneyConverter::class)->format(
            $this->event->amount,
            $currency,
            (string) (Setting::get('locale') ?? 'id'),
        );
        $date = Carbon::parse($this->event->dueDate)->format('d M Y');

        $templates = Setting::get('reminder_message_templates');
        $template = is_array($templates)
            ? ($templates[$this->event->type->value] ?? null)
            : null;
        $template ??= Setting::get('reminder_message_template');

        $invoice = $this->invoice();
        $invoiceContext = $invoice
            ? __('notifications.rent.invoice_context', [
                'reference' => $invoice->reference,
                'period' => Carbon::parse($this->event->periodStart)->format('d M Y')
                    .' – '.Carbon::parse($this->event->periodEnd)->format('d M Y'),
                'date' => $date,
                'amount' => $amount,
            ])
            : '';
        $invoiceUrl = $this->invoiceUrl($notifiable);
        $invoiceLink = $invoiceUrl
            ? __('notifications.rent.view_invoice').': '.$invoiceUrl
            : '';

        $message = $template
            ? str_replace(
                [':name', ':unit', ':days', ':amount', ':date', ':invoice_context', ':invoice_link'],
                [$notifiable->name, $this->event->lease->unit?->name ?? '—', $days, $amount, $date, $invoiceContext, $invoiceLink],
                $template,
            )
            : __("notifications.rent.{$this->event->type->value}", [
                'name' => $notifiable->name,
                'unit' => $this->event->lease->unit?->name ?? '—',
                'days' => $days,
                'amount' => $amount,
                'date' => $date,
                'invoice_context' => $invoiceContext,
                'invoice_link' => $invoiceLink,
            ]);

        return trim(preg_replace('/\n{3,}/', "\n\n", $message) ?? $message);
    }

    private function invoice(): ?Invoice
    {
        return isset($this->event->invoice) ? $this->event->invoice : null;
    }

    private function invoiceUrl(object $notifiable): ?string
    {
        return $this->portalUrl($notifiable, $this->invoice());
    }

    private function portalUrl(object $notifiable, ?Invoice $invoice = null): ?string
    {
        if (! ($notifiable instanceof Tenant)) {
            return null;
        }

        $user = $notifiable->user;

        if (! $user?->is_active || ! $user->hasVerifiedEmail()) {
            return null;
        }

        return $invoice
            ? route('portal.billing.invoices.show', $invoice)
            : route('portal.billing.index');
    }

    private function invoicePdfContent(Invoice $invoice): ?string
    {
        return $this->invoicePdfContent ??= app(InvoicePdfArtifact::class)->content($invoice);
    }
}
