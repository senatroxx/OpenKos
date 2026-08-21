<?php

namespace App\Actions\Invoices;

use App\Models\Invoice;
use App\Models\Setting;
use Dompdf\Dompdf;
use Dompdf\Options;

final class GenerateInvoicePdf
{
    public function execute(Invoice $invoice): string
    {
        $invoice->loadMissing([
            'lease.primaryTenant.user',
            'lease.unit.property',
            'lineItems',
            'payments' => fn ($query) => $query
                ->where('status', 'confirmed')
                ->orderBy('payment_date')
                ->orderBy('id'),
        ]);
        $invoice->append(['outstanding', 'display_status']);

        $settings = Setting::some(['site_name', 'locale', 'currency']);
        $options = new Options;
        $options->setDefaultFont('DejaVu Sans');
        $options->setIsRemoteEnabled(false);

        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('invoices.pdf', [
            'currency' => $invoice->currency,
            'invoice' => $invoice,
            'locale' => $settings['locale'] ?? 'id',
            'siteName' => $settings['site_name'] ?? config('app.name'),
        ])->render(), 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();

        $output = $pdf->output();
        unset($pdf);
        gc_collect_cycles();

        return $output;
    }
}
