<?php

namespace OpenKOS\Core\Contracts;

/**
 * Payment gateway contract for platform plugins. No implementations yet.
 */
interface PaymentGateway
{
    public function key(): string;

    public function displayName(): string;

    public function createPayment(array $payload): array;

    public function handleCallback(array $payload): array;

    /**
     * Describe the configuration fields this gateway needs.
     */
    public function configurationSchema(): array;
}
