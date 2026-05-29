<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeadNote;
use Illuminate\Http\Request;

class LeadNoteController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'lead_id' => 'required|exists:leads,id',
            'note' => 'required|string',
        ]);

        $note = LeadNote::create([
            'lead_id' => $data['lead_id'],
            'user_id' => $request->user()->id,
            'note' => $data['note'],
        ]);

        return response()->json($note->load('user'), 201);
    }

    public function destroy(LeadNote $leadNote)
    {
        $leadNote->delete();

        return response()->noContent();
    }
}
