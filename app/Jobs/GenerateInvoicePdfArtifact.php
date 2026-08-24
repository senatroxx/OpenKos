<?php

namespace App\Jobs;

use App\Actions\Invoices\GenerateInvoicePdf;
use App\Models\Invoice;
use App\Models\Setting;
use App\Services\Invoices\InvoicePdfArtifact;
use App\Services\Localization\ApplicationLocale;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateInvoicePdfArtifact implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $invoiceId,
        public string $fingerprint,
    ) {}

    public function uniqueId(): string
    {
        return "invoice-pdf:{$this->invoiceId}:{$this->fingerprint}";
    }

    public function handle(InvoicePdfArtifact $artifact, GenerateInvoicePdf $renderer, ApplicationLocale $locale): void
    {
        $locale->apply();

        if (! Setting::get('invoice_pdf_enabled')) {
            return;
        }

        $invoice = Invoice::find($this->invoiceId);

        if (! $invoice) {
            return;
        }

        $currentFingerprint = $artifact->fingerprint($invoice);
        if ($currentFingerprint !== $this->fingerprint) {
            self::dispatch($invoice->getKey(), $currentFingerprint);

            return;
        }

        $artifact->generate($invoice, $this->fingerprint, $renderer->execute($invoice));
    }
}
