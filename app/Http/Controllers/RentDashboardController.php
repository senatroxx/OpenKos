<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Services\BankStatement\RentDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RentDashboardController extends Controller
{
    public function index(Request $request, RentDashboardService $service): View
    {
        $month = $request->input('month', now()->format('Y-m'));
        $properties = Property::query()->with('units')->get();

        $propertySummaries = $properties->map(fn (Property $property) => $service->summarizeProperty($property, $month));

        return view('bank-statements.dashboard', [
            'properties' => $properties,
            'propertySummaries' => $propertySummaries,
            'month' => $month,
        ]);
    }

    public function show(Request $request, Property $property, RentDashboardService $service): View
    {
        $month = $request->input('month', now()->format('Y-m'));
        $summary = $service->summarizeProperty($property, $month);

        return view('bank-statements.dashboard-property', [
            'property' => $property,
            'summary' => $summary,
            'month' => $month,
        ]);
    }
}
