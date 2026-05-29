<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PipelineStage;
use Illuminate\Http\Request;

class PipelineStageController extends Controller
{
    public function index()
    {
        return PipelineStage::query()->orderBy('sort_order')->paginate(20);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'sort_order' => 'nullable|integer',
            'is_active' => 'boolean',
        ]);

        $stage = PipelineStage::create($data);

        return response()->json($stage, 201);
    }

    public function show(PipelineStage $pipelineStage)
    {
        return $pipelineStage;
    }

    public function update(Request $request, PipelineStage $pipelineStage)
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'sort_order' => 'nullable|integer',
            'is_active' => 'boolean',
        ]);

        $pipelineStage->update($data);

        return $pipelineStage;
    }

    public function destroy(PipelineStage $pipelineStage)
    {
        $pipelineStage->delete();

        return response()->noContent();
    }
}
