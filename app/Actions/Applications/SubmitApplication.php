<?php

namespace App\Actions\Applications;

use App\Data\Application\SubmitApplicationData;
use App\Enums\ApplicationTargetType;
use App\Models\Application;
use App\Models\Property;
use App\Models\UnitType;
use App\Models\User;
use App\Results\Application\ApplicationResult;
use Illuminate\Support\Facades\DB;

final class SubmitApplication
{
    public function execute(User $user, SubmitApplicationData $data): ApplicationResult
    {
        $property = Property::query()->where('public_slug', $data->propertySlug)->first();
        if (! $property || ! $property->isPubliclyVisible()) {
            return ApplicationResult::error(__('This listing is no longer available for applications.'));
        }

        if ($data->targetType === ApplicationTargetType::WholeProperty) {
            if ($data->unitTypeSlug !== null || ! $property->rental_mode->supportsWholePropertyRental()) {
                return ApplicationResult::error(__('Choose a valid whole-property offering.'));
            }
        } elseif ($data->targetType !== ApplicationTargetType::UnitType || $data->unitTypeSlug === null || ! $property->rental_mode->supportsUnitInventory()) {
            return ApplicationResult::error(__('Choose a valid unit type offering.'));
        }

        $unitType = $data->unitTypeSlug === null ? null : UnitType::query()
            ->where('public_slug', $data->unitTypeSlug)
            ->where('property_id', $property->id)
            ->viablePublicOffering()
            ->first();

        if ($data->targetType === ApplicationTargetType::UnitType && $unitType === null) {
            return ApplicationResult::error(__('This unit type is no longer available for applications.'));
        }

        return DB::transaction(function () use ($user, $data, $property, $unitType): ApplicationResult {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $key = implode('|', [$user->id, $data->targetType->value, $property->id, $unitType?->id ?? 'property']);
            if (Application::query()->where('open_application_key', $key)->lockForUpdate()->exists()) {
                return ApplicationResult::error(__('You already have an open application for this offering.'));
            }

            return ApplicationResult::success(Application::create([
                'user_id' => $user->id,
                'property_id' => $property->id,
                'unit_type_id' => $unitType?->id,
                'target_type' => $data->targetType,
                'status' => 'new',
                'applicant_name' => $user->name,
                'applicant_email' => $user->email,
                'applicant_phone' => $data->applicantPhone,
                'intended_move_in_date' => $data->intendedMoveInDate,
                'intended_move_in_timeframe' => $data->intendedMoveInTimeframe,
                'applicant_message' => $data->applicantMessage,
                'open_application_key' => $key,
            ]));
        });
    }
}
