<?php

namespace App\Data\Application;

final readonly class SubmitApplicationData
{
    public function __construct(
        public string $targetType,
        public string $propertySlug,
        public ?string $unitTypeSlug,
        public ?string $intendedMoveInDate,
        public ?string $intendedMoveInTimeframe,
        public ?string $applicantPhone,
        public ?string $applicantMessage,
    ) {}
}
