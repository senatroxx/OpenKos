<?php

namespace Tests\Support\Fakes;

use OpenKOS\Core\Contracts\PaymentGateway;
use OpenKOS\Core\Data\Payment\PaymentCreationResult;
use OpenKOS\Core\Data\Payment\PaymentRequest;
use OpenKOS\Core\Data\Payment\PaymentWebhookRequest;
use OpenKOS\Core\Data\Payment\PaymentWebhookResult;
use RuntimeException;

class BrokenManagerTestPaymentGateway implements PaymentGateway
{
    public function __construct()
    {
        throw new RuntimeException('broken gateway');
    }

    public function key(): string
    {
        return 'broken/gateway';
    }

    public function displayName(): string
    {
        return 'Broken Gateway';
    }

    public function createPayment(PaymentRequest $request): PaymentCreationResult
    {
        throw new RuntimeException('broken gateway');
    }

    public function handleCallback(PaymentWebhookRequest $request): PaymentWebhookResult
    {
        throw new RuntimeException('broken gateway');
    }

    public function configurationSchema(): array
    {
        return [];
    }
}
