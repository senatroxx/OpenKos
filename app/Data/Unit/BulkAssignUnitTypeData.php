<?php

namespace App\Data\Unit;

final readonly class BulkAssignUnitTypeData
{
    /**
     * @param  list<int>  $unitIds
     */
    public function __construct(
        public array $unitIds,
        public int $unitTypeId,
    ) {}
}
