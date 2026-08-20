<?php

namespace Tests\Support\Fakes;

use OpenKOS\Core\Contracts\PaymentGateway;
use OpenKOS\Core\Data\Payment\CheckoutInstructions;
use OpenKOS\Core\Data\Payment\PaymentCreationResult;
use OpenKOS\Core\Data\Payment\PaymentRequest;
use OpenKOS\Core\Data\Payment\PaymentWebhookRequest;
use OpenKOS\Core\Data\Payment\PaymentWebhookResult;
use OpenKOS\Core\Enums\PaymentStatus;

class ManagerTestPaymentGateway implements PaymentGateway
{
    public function __construct(public array $config = []) {}

    public function key(): string
    {
        return 'test/gateway';
    }

    public function displayName(): string
    {
        return 'Test Gateway';
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
                'presentation' => 'segmented',
                'default' => 'sandbox',
                'options' => [
                    ['value' => 'sandbox', 'label' => 'Sandbox'],
                    ['value' => 'production', 'label' => 'Production'],
                ],
            ],
            'webhook_setup' => [
                'label' => 'Webhook setup',
                'type' => 'info',
                'instructions' => [
                    'Open the webhook settings.',
                    'Add the webhook URL shown below.',
                ],
                'link' => [
                    'label' => 'Open webhook settings',
                    'url' => 'https://example.test/webhooks',
                ],
                'url' => '/api/webhooks/test',
            ],
            'secret_key' => [
                'label' => 'Secret key',
                'type' => 'password',
                'required' => true,
                'description' => 'Keep this value secret.',
                'visible_when' => [
                    'field' => 'environment',
                    'value' => 'sandbox',
                ],
            ],
        ];
    }
}
