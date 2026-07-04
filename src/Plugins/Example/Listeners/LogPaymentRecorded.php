<?php

namespace OpenKOS\Plugins\Example\Listeners;

use App\Events\PaymentRecorded;
use Illuminate\Support\Facades\Log;

/**
 * Example listener for a core domain event — wired via ExamplePlugin::listens().
 * A real plugin might post to accounting, update analytics, or notify a channel.
 */
class LogPaymentRecorded
{
    public function handle(PaymentRecorded $event): void
    {
        Log::info('[example-plugin] payment recorded', ['payment_id' => $event->payment->id]);
    }
}
