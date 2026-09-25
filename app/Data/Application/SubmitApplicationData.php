<?php

namespace App\Data\Application;

use App\Enums\ApplicationTargetType;

final readonly class SubmitApplicationData
{
    public function __construct(
        public ApplicationTargetType $targetType,
        public string $propertySlug,
        public ?string $unitTypeSlug,
        public ?string $intendedMoveInDate,
        public ?string $rentalBillingUnit,
        public ?int $rentalBillingInterval,
        public ?string $rentalCurrency,
        public ?string $applicantMessage,
    ) {}
}
