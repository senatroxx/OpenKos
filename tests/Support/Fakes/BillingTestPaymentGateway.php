<?php

namespace Tests\Support\Fakes;

use OpenKOS\Core\Contracts\PaymentGateway;
use OpenKOS\Core\Data\Payment\CheckoutInstructions;
use OpenKOS\Core\Data\Payment\PaymentCreationResult;
use OpenKOS\Core\Data\Payment\PaymentRequest;
use OpenKOS\Core\Data\Payment\PaymentWebhookRequest;
use OpenKOS\Core\Data\Payment\PaymentWebhookResult;
use OpenKOS\Core\Enums\PaymentStatus;

class BillingTestPaymentGateway implements PaymentGateway
{
    public function __construct(public array $config = []) {}

    public function key(): string
    {
        return 'test/billing';
    }

    public function displayName(): string
    {
        return 'Billing Test Gateway';
    }

    public function createPayment(PaymentRequest $request): PaymentCreationResult
    {
        return new PaymentCreationResult(
            providerReference: 'provider-reference',
            status: PaymentStatus::Pending,
            amount: $request->amount,
            instructions: new CheckoutInstructions,
        );
    }

    public function handleCallback(PaymentWebhookRequest $request): PaymentWebhookResult
    {
        return new PaymentWebhookResult(
            eventReference: 'event-reference',
            providerReference: 'provider-reference',
            status: PaymentStatus::Pending,
        );
    }

    public function configurationSchema(): array
    {
        return [
            'environment' => [
                'label' => 'Environment',
                'type' => 'select',
                'required' => true,
                'options' => [
                    ['value' => 'sandbox', 'label' => 'Sandbox'],
                    ['value' => 'production', 'label' => 'Production'],
                ],
            ],
            'secret_key' => [
                'label' => 'Secret key',
                'type' => 'password',
                'required' => true,
            ],
        ];
    }
}
