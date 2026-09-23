<?php

namespace App\Http\Controllers;

use App\Actions\Applications\ConvertApplicationToTenant;
use App\Actions\Applications\SubmitApplication;
use App\Actions\Applications\TransitionApplication;
use App\Enums\ApplicationTargetType;
use App\Http\Requests\Application\StoreApplicationRequest;
use App\Http\Requests\Application\TransitionApplicationRequest;
use App\Models\Application;
use App\Models\Property;
use App\Models\UnitType;
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

    public function create(Request $request): Response
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

        return Inertia::render('applications/create', [
            'target' => [
                'target_type' => $targetType->value,
                'property_slug' => $property->public_slug,
                'property_name' => $property->name,
                'unit_type_slug' => $unitType?->public_slug,
                'unit_type_name' => $unitType?->name,
            ],
        ]);
    }

    public function show(Request $request, Application $application): Response
    {
        $this->authorize('view', $application);

        return Inertia::render('applications/show', [
            'application' => $this->projection($application, $request->user()->id === $application->user_id),
            'operator' => $request->user()->isOwner() || $request->user()->can('tenants.view'),
        ]);
    }

    public function store(StoreApplicationRequest $request, SubmitApplication $action): RedirectResponse
    {
        $this->authorize('create', Application::class);
        $result = $action->execute($request->user(), $request->toData());
        if ($result->failed()) {
            return back()->withErrors(['application' => $result->error]);
        }

        return to_route('applications.index')->with('status', __('Application submitted.'));
    }

    public function transition(TransitionApplicationRequest $request, Application $application, TransitionApplication $action): RedirectResponse
    {
        if ($request->string('status')->value() === 'withdrawn') {
            $this->authorize('withdraw', $application);
        } else {
            $this->authorize('update', $application);
        }
        $result = $action->execute($request->user(), $application, $request->toData());

        return $result->failed()
            ? back()->withErrors(['application' => $result->error])
            : back()->with('status', __('Application updated.'));
    }

    public function convert(Request $request, Application $application, ConvertApplicationToTenant $action): RedirectResponse
    {
        $this->authorize('update', $application);
        $result = $action->execute($request->user(), $application);

        return $result->failed()
            ? back()->withErrors(['application' => $result->error])
            : back()->with('status', __('Application converted to a Tenant.'));
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
            'applicant_message' => $application->applicant_message,
            'applicant_feedback' => $application->applicant_feedback,
            'converted_at' => $application->converted_at?->toIso8601String(),
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
            'converted_tenant_id' => $application->converted_tenant_id,
        ];
    }
}
