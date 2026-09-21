<?php

namespace App\Http\Controllers\Dashboard;

use App\Business\Dashboard\FinancialDashboardCalculator;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\FinancialDashboardRequest;
use App\Models\Property;
use App\Repositories\DashboardRepository;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

class FinancialController extends Controller
{
    public function __invoke(FinancialDashboardRequest $request, FinancialDashboardCalculator $calculator, DashboardRepository $repository): Response
    {
        $accessibleProperties = Property::query()
            ->when(! $request->user()->isOwner(), fn (Builder $query) => $query->whereHas(
                'users',
                fn (Builder $userQuery) => $userQuery->whereKey($request->user()->id),
            ))
            ->orderBy('name')
            ->get(['id', 'name']);
        $validated = $request->validated();
        $selectedPropertyId = isset($validated['property_id']) ? (int) $validated['property_id'] : null;

        abort_if(
            $selectedPropertyId !== null && ! $accessibleProperties->contains('id', $selectedPropertyId),
            403,
        );

        $period = $validated['period'] ?? 'current_month';

        $now = now();
        $propertyIds = $selectedPropertyId === null
            ? $accessibleProperties->pluck('id')
            : collect([$selectedPropertyId]);
        $periodStart = $period === 'ytd' ? $now->copy()->startOfYear() : $now->copy()->startOfMonth();
        $financialData = $repository->financialData(
            $propertyIds,
            $now->copy()->startOfMonth()->subMonthsNoOverflow(11),
            $now,
            $periodStart,
            $now->copy()->endOfDay(),
        );

        return Inertia::render('dashboard/financial', [
            'financial' => $calculator->calculate(
                $accessibleProperties->map(fn (Property $property): array => [
                    'id' => $property->id,
                    'name' => $property->name,
                ]),
                $financialData,
                $selectedPropertyId,
                $period,
                $now,
            ),
            'filters' => [
                'period' => $period,
                'property_id' => $selectedPropertyId,
            ],
            'properties' => $accessibleProperties,
        ]);
    }
}
