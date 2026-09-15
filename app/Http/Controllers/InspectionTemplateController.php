<?php

namespace App\Http\Controllers;

use App\Actions\Inspections\CreateInspectionTemplate;
use App\Actions\Inspections\UpdateInspectionTemplate;
use App\Http\Requests\Inspection\StoreInspectionTemplateRequest;
use App\Http\Requests\Inspection\UpdateInspectionTemplateRequest;
use App\Models\InspectionTemplate;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class InspectionTemplateController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', InspectionTemplate::class);

        return Inertia::render('inspections/templates', [
            'templates' => InspectionTemplate::query()
                ->with('items')
                ->orderBy('inspection_type')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(
        StoreInspectionTemplateRequest $request,
        CreateInspectionTemplate $createInspectionTemplate,
    ): RedirectResponse {
        $this->authorize('create', InspectionTemplate::class);
        $createInspectionTemplate->execute($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Inspection template created.')]);

        return back();
    }

    public function update(
        UpdateInspectionTemplateRequest $request,
        InspectionTemplate $inspectionTemplate,
        UpdateInspectionTemplate $updateInspectionTemplate,
    ): RedirectResponse {
        $this->authorize('update', $inspectionTemplate);
        $updateInspectionTemplate->execute($inspectionTemplate, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Inspection template updated.')]);

        return back();
    }
}
