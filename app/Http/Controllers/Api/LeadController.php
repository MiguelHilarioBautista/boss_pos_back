<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    public function index()
    {
        return Lead::query()
            ->with(['customer', 'pipelineStage', 'owner'])
            ->orderByDesc('id')
            ->paginate(20);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'pipeline_stage_id' => 'nullable|exists:pipeline_stages,id',
            'owner_user_id' => 'nullable|exists:users,id',
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'source' => 'nullable|string|max:255',
            'status' => 'nullable|string|max:50',
            'estimated_value' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $lead = Lead::create($data);

        return response()->json($lead->load(['customer', 'pipelineStage', 'owner']), 201);
    }

    public function show(Lead $lead)
    {
        return $lead->load(['customer', 'pipelineStage', 'owner', 'notes', 'tasks']);
    }

    public function update(Request $request, Lead $lead)
    {
        $data = $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'pipeline_stage_id' => 'nullable|exists:pipeline_stages,id',
            'owner_user_id' => 'nullable|exists:users,id',
            'name' => 'sometimes|required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'source' => 'nullable|string|max:255',
            'status' => 'nullable|string|max:50',
            'estimated_value' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $lead->update($data);

        return $lead->load(['customer', 'pipelineStage', 'owner']);
    }

    public function destroy(Lead $lead)
    {
        $lead->delete();

        return response()->noContent();
    }
}
