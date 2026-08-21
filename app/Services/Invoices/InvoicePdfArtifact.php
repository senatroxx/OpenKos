<?php

namespace App\Services\Invoices;

use App\Jobs\GenerateInvoicePdfArtifact;
use App\Models\Invoice;
use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

final class InvoicePdfArtifact
{
    private const DISK = 'local';

    public function status(Invoice $invoice): string
    {
        if (! Setting::get('invoice_pdf_enabled')) {
            return 'disabled';
        }

        return $this->available($invoice) ? 'available' : 'pending';
    }

    public function available(Invoice $invoice): bool
    {
        if (! Setting::get('invoice_pdf_enabled')) {
            return false;
        }

        return Invoice::query()->whereKey($invoice->getKey())->value('invoice_pdf_fingerprint') === $this->fingerprint($invoice)
            && Storage::disk(self::DISK)->exists($this->path($invoice));
    }

    public function content(Invoice $invoice): ?string
    {
        if (! $this->available($invoice)) {
            $this->ensureQueued($invoice);

            return null;
        }

        return Storage::disk(self::DISK)->get($this->path($invoice));
    }

    public function ensureQueued(Invoice $invoice): void
    {
        if (! Setting::get('invoice_pdf_enabled')) {
            return;
        }

        $fingerprint = $this->fingerprint($invoice);

        if (Invoice::query()->whereKey($invoice->getKey())->value('invoice_pdf_fingerprint') === $fingerprint
            && Storage::disk(self::DISK)->exists($this->path($invoice))) {
            return;
        }

        GenerateInvoicePdfArtifact::dispatch($invoice->getKey(), $fingerprint);
    }

    public function generate(Invoice $invoice, string $fingerprint, string $pdf): void
    {
        if (! Setting::get('invoice_pdf_enabled')) {
            return;
        }

        $currentFingerprint = $this->fingerprint($invoice);
        if ($currentFingerprint !== $fingerprint) {
            GenerateInvoicePdfArtifact::dispatch($invoice->getKey(), $currentFingerprint);

            return;
        }

        if (! Storage::disk(self::DISK)->put($this->path($invoice), $pdf)) {
            throw new \RuntimeException('Unable to store the generated invoice PDF.');
        }

        $invoice->forceFill(['invoice_pdf_fingerprint' => $fingerprint])->saveQuietly();
    }

    public function path(Invoice $invoice): string
    {
        return "invoice-pdfs/{$invoice->getKey()}.pdf";
    }

    public function fingerprint(Invoice $invoice): string
    {
        $invoice = Invoice::query()->findOrFail($invoice->getKey());
        $invoice->load([
            'lease.primaryTenant.user',
            'lease.unit.property.city',
            'lease.unit.property.region',
            'lineItems',
            'payments' => fn ($query) => $query
                ->where('status', 'confirmed')
                ->orderBy('payment_date')
                ->orderBy('id'),
        ]);

        $payload = [
            'settings' => Setting::some(['site_name', 'locale', 'currency']),
            'invoice' => $this->attributes($invoice, [
                'id', 'reference', 'created_at', 'period_start', 'period_end',
                'due_date', 'status', 'total', 'amount_paid',
                'currency',
            ]),
            'lease' => $this->attributes($invoice->lease, ['id', 'reference']),
            'unit' => $this->attributes($invoice->lease?->unit, ['id', 'name']),
            'property' => $this->attributes($invoice->lease?->unit?->property, [
                'id', 'name', 'address', 'postal_code',
            ]),
            'city' => $this->attributes($invoice->lease?->unit?->property?->city, ['id', 'name']),
            'region' => $this->attributes($invoice->lease?->unit?->property?->region, ['id', 'name']),
            'tenant' => $this->attributes($invoice->lease?->primaryTenant, ['id', 'name', 'phone']),
            'user' => $this->attributes($invoice->lease?->primaryTenant?->user, ['id', 'email']),
            'line_items' => $invoice->lineItems->map(fn ($item) => $this->attributes($item, [
                'id', 'type', 'description', 'amount', 'updated_at',
            ]))->values()->all(),
            'payments' => $invoice->payments->map(fn ($payment) => $this->attributes($payment, [
                'id', 'amount', 'payment_date', 'payment_method', 'reference_number', 'verified_at', 'updated_at',
            ]))->values()->all(),
        ];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /** @return array<string, mixed>|null */
    private function attributes(?object $model, array $keys): ?array
    {
        if (! $model) {
            return null;
        }

        return collect($keys)->mapWithKeys(fn (string $key): array => [$key => $model->{$key}])->all();
    }
}
