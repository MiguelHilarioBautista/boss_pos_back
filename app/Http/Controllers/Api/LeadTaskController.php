<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeadTask;
use Illuminate\Http\Request;

class LeadTaskController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'lead_id' => 'required|exists:leads,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_at' => 'nullable|date',
            'status' => 'nullable|string|max:50',
        ]);

        $task = LeadTask::create([
            'lead_id' => $data['lead_id'],
            'user_id' => $request->user()->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'due_at' => $data['due_at'] ?? null,
            'status' => $data['status'] ?? 'open',
        ]);

        return response()->json($task, 201);
    }

    public function update(Request $request, LeadTask $leadTask)
    {
        $data = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'due_at' => 'nullable|date',
            'status' => 'nullable|string|max:50',
        ]);

        $leadTask->update($data);

        return $leadTask;
    }

    public function destroy(LeadTask $leadTask)
    {
        $leadTask->delete();

        return response()->noContent();
    }
}
