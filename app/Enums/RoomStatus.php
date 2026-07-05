<?php

use App\Enums\RoomStatus;
use App\Enums\UnitStatus;

// ponytail: backward-compat alias for the UnitStatus rename.
// Keep until all external references are migrated to UnitStatus.
class_alias(UnitStatus::class, RoomStatus::class);
