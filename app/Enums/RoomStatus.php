<?php

// ponytail: backward-compat alias for the UnitStatus rename.
// Keep until all external references are migrated to UnitStatus.
class_alias(\App\Enums\UnitStatus::class, \App\Enums\RoomStatus::class);
