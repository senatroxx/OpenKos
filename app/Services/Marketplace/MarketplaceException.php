<?php

namespace App\Services\Marketplace;

use RuntimeException;
use Throwable;

final class MarketplaceException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $status = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
