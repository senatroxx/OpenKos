<?php

namespace App\Http\Controllers;

use App\Actions\Applications\SubmitApplication;
use App\Actions\Applications\TransitionApplication;
use App\Actions\Reservations\FindAvailableReservationUnits;
use App\Enums\ApplicationStatus;
use App\Enums\ApplicationTargetType;
use App\Enums\ReservationStatus;
use App\Http\Requests\Application\StoreApplicationRequest;
use App\Http\Requests\Application\TransitionApplicationRequest;
use App\Models\Application;
use App\Models\Property;
use App\Models\Unit;
use App\Models\UnitType;
use App\Services\Pricing\EffectiveUnitRateResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ApplicationController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Application::class);
        $isOperator = $request->user()->isOwner() || $request->user()->can('tenants.view');
        $applications = Application::query()
            ->with(['property:id,name,public_slug', 'unitType:id,name,public_slug', 'convertedTenant:id,name'])
            ->when(! $isOperator, fn ($query) => $query->where('user_id', $request->user()->id))
            ->latest()
            ->get();

        return Inertia::render('applications/index', [
            'applications' => $applications->map(fn (Application $application): array => $this->applicantProjection($application))->values(),
            'operator' => $isOperator,
        ]);
    }

    public function create(Request $request): Response|RedirectResponse
    {
        $this->authorize('create', Application::class);
        $targetType = ApplicationTargetType::tryFrom($request->string('target_type')->toString());
        abort_if($targetType === null, 404);
        $property = Property::query()->where('public_slug', $request->string('property_slug'))->firstOrFail();
        $unitType = $request->filled('unit_type_slug') ? UnitType::query()
            ->where('public_slug', $request->string('unit_type_slug'))
            ->where('property_id', $property->id)
            ->first() : null;

        abort_unless($property->isPubliclyVisible(), 404);
        abort_unless(
            ($targetType === ApplicationTargetType::WholeProperty && $property->rental_mode->supportsWholePropertyRental() && $unitType === null)
            || ($targetType === ApplicationTargetType::UnitType && $unitType?->isViablePublicOffering()),
            404,
        );

        return $unitType
            ? to_route('public.portal.unit-types.show', [
                'property' => $property->public_slug,
                'unitType' => $unitType->public_slug,
            ])
            : to_route('public.portal.show', ['property' => $property->public_slug]);
    }

    public function show(Request $request, Application $application, FindAvailableReservationUnits $findAvailableReservationUnits): Response
    {
        $this->authorize('view', $application);
        $operator = $request->user()->isOwner() || $request->user()->can('tenants.view');
        $application->loadMissing([
            'property.activePropertyRates',
            'latestReservation.unit.activeRates',
            'latestReservation.unit.unitType.activeRates',
        ]);

        $reservation = $application->latestReservation;
        $canRequestReservation = ! $operator
            && $request->user()->id === $application->user_id
            && $request->user()->can('requestReservation', $application);
        $availableUnits = $operator
            && $reservation?->status === ReservationStatus::Pending
            && $application->target_type === ApplicationTargetType::UnitType
            && $application->property?->rental_mode->supportsUnitInventory()
            ? $findAvailableReservationUnits->execute($application, $reservation->move_in_date->toDateString())->loadMissing(['activeRates', 'unitType.activeRates'])
            : collect();

        return Inertia::render('applications/show', [
            'application' => [
                ...$this->projection($application, $request->user()->id === $application->user_id),
                'can_request_reservation' => $canRequestReservation,
                'property' => $application->property === null ? null : [
                    ...$application->property->only(['id', 'name', 'public_slug', 'slug']),
                    'active_property_rates' => $application->property->activePropertyRates->map(fn ($rate): array => $rate->only([
                        'id', 'billing_interval', 'billing_unit', 'amount', 'currency',
                    ]))->values(),
                ],
                'reservation' => $reservation === null ? null : [
                    'id' => $reservation->id,
                    'status' => $reservation->status->value,
                    'move_in_date' => $reservation->move_in_date->toDateString(),
                    'expires_at' => $reservation->expires_at?->toIso8601String(),
                    'is_expired' => $reservation->isExpired(),
                    'unit' => $reservation->unit === null ? null : $this->unitProjection($reservation->unit, $operator),
                ],
                'available_units' => $availableUnits->map(fn (Unit $unit): array => $this->unitProjection($unit))->values(),
            ],
            'operator' => $operator,
        ]);
    }

    public function store(StoreApplicationRequest $request, SubmitApplication $action): RedirectResponse
    {
        $this->authorize('create', Application::class);

        if (! $request->user()->hasCompleteRenterProfile()) {
            return back()->withErrors(['profile' => __('Complete your profile before applying.')]);
        }

        $result = $action->execute($request->user(), $request->toData());
        if ($result->failed()) {
            return back()->withErrors(['application' => $result->error]);
        }

        $application = $result->value;

        return to_route('applications.show', $application)->with('status', __('Application submitted.'));
    }

    public function transition(TransitionApplicationRequest $request, Application $application, TransitionApplication $action): RedirectResponse
    {
        if ($request->status() === ApplicationStatus::Withdrawn) {
            $this->authorize('withdraw', $application);
        } else {
            $this->authorize('update', $application);
        }
        $result = $action->execute($request->user(), $application, $request->toData());

        return $result->failed()
            ? back()->withErrors(['application' => $result->error])
            : back()->with('status', __('Application updated.'));
    }

    /** @return array<string, mixed> */
    private function applicantProjection(Application $application): array
    {
        return [
            'id' => $application->id,
            'status' => $application->status->value,
            'target_type' => $application->target_type->value,
            'property' => $application->property?->only(['id', 'name', 'public_slug']),
            'unit_type' => $application->unitType?->only(['id', 'name', 'public_slug']),
            'intended_move_in_date' => $application->intended_move_in_date?->toDateString(),
            'intended_move_in_timeframe' => $application->intended_move_in_timeframe,
            'rental_billing_unit' => $application->rental_billing_unit,
            'rental_billing_interval' => $application->rental_billing_interval,
            'rental_currency' => $application->rental_currency,
            'rental_amount' => $application->rental_amount,
            'applicant_message' => $application->applicant_message,
            'applicant_feedback' => $application->applicant_feedback,
        ];
    }

    /** @return array<string, mixed> */
    private function projection(Application $application, bool $applicant): array
    {
        $projection = $this->applicantProjection($application);
        if ($applicant) {
            return $projection;
        }

        return [
            ...$projection,
            'applicant' => [
                'name' => $application->applicant_name,
                'email' => $application->applicant_email,
                'phone' => $application->applicant_phone,
            ],
            'operator_notes' => $application->operator_notes,
            'reviewed_at' => $application->reviewed_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function unitProjection(Unit $unit, bool $includeLease = false): array
    {
        $unit->loadMissing([
            'activeRates',
            'unitType.activeRates',
            ...($includeLease ? ['leases' => fn ($query) => $query->active()] : []),
        ]);
        $unit->setAttribute('effective_rates', app(EffectiveUnitRateResolver::class)->resolve($unit)->map(fn (array $item): array => [
            ...$item['rate']->toArray(),
            'source' => $item['source'],
        ])->values());

        return $unit->only([
            'id', 'name', 'slug', 'capacity', 'property_id', 'unit_type_id', 'status', 'effective_rates',
            ...($includeLease ? ['leases'] : []),
        ]);
    }
}
