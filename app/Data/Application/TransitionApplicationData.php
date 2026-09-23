<?php

namespace App\Data\Application;

final readonly class TransitionApplicationData
{
    public function __construct(
        public string $status,
        public ?string $operatorNotes = null,
        public ?string $applicantFeedback = null,
    ) {}
}
