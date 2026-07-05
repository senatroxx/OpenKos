<?php

use App\Business\Units\UnitStatusValidator;
use App\Enums\UnitStatus;
use Database\Seeders\RoleAndPermissionSeeder;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('validates available to occupied transition', function () {
    $validator = app(UnitStatusValidator::class);

    expect(fn () => $validator->validate(UnitStatus::Available, UnitStatus::Occupied))->not->toThrow(Exception::class);
});

it('validates available to maintenance transition', function () {
    $validator = app(UnitStatusValidator::class);

    expect(fn () => $validator->validate(UnitStatus::Available, UnitStatus::Maintenance))->not->toThrow(Exception::class);
});

it('validates occupied to maintenance transition', function () {
    $validator = app(UnitStatusValidator::class);

    expect(fn () => $validator->validate(UnitStatus::Occupied, UnitStatus::Maintenance))->not->toThrow(Exception::class);
});

it('validates occupied to available transition', function () {
    $validator = app(UnitStatusValidator::class);

    expect(fn () => $validator->validate(UnitStatus::Occupied, UnitStatus::Available))->not->toThrow(Exception::class);
});

it('validates maintenance to available transition', function () {
    $validator = app(UnitStatusValidator::class);

    expect(fn () => $validator->validate(UnitStatus::Maintenance, UnitStatus::Available))->not->toThrow(Exception::class);
});

it('validates maintenance to occupied transition', function () {
    $validator = app(UnitStatusValidator::class);

    expect(fn () => $validator->validate(UnitStatus::Maintenance, UnitStatus::Occupied))->not->toThrow(Exception::class);
});

it('rejects available to unavailable transition', function () {
    $validator = app(UnitStatusValidator::class);

    expect(fn () => $validator->validate(UnitStatus::Available, UnitStatus::Unavailable))->toThrow(Exception::class);
});

it('rejects unavailable to any transition', function () {
    $validator = app(UnitStatusValidator::class);

    expect(fn () => $validator->validate(UnitStatus::Unavailable, UnitStatus::Available))->toThrow(Exception::class);
    expect(fn () => $validator->validate(UnitStatus::Unavailable, UnitStatus::Occupied))->toThrow(Exception::class);
});
