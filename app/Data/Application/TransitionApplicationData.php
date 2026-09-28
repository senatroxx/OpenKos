<?php

namespace App\Data\Application;

use App\Enums\ApplicationStatus;

final readonly class TransitionApplicationData
{
    public function __construct(
        public ApplicationStatus $status,
        public ?string $operatorNotes = null,
        public ?string $applicantFeedback = null,
    ) {}
}
