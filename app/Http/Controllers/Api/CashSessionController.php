<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashMovement;
use App\Models\CashSession;
use Illuminate\Http\Request;

class CashSessionController extends Controller
{
    public function index()
    {
        return CashSession::query()->with('movements')->orderByDesc('id')->paginate(20);
    }

    public function show(CashSession $cashSession)
    {
        return $cashSession->load('movements');
    }

    public function open(Request $request)
    {
        $data = $request->validate([
            'opening_balance' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $session = CashSession::create([
            'user_id' => $request->user()->id,
            'opened_at' => now(),
            'opening_balance' => $data['opening_balance'],
            'status' => 'open',
            'notes' => $data['notes'] ?? null,
        ]);

        return response()->json($session, 201);
    }

    public function close(Request $request, CashSession $cashSession)
    {
        $data = $request->validate([
            'closing_balance' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $cashSession->update([
            'closing_balance' => $data['closing_balance'],
            'closed_at' => now(),
            'status' => 'closed',
            'notes' => $data['notes'] ?? $cashSession->notes,
        ]);

        return $cashSession->load('movements');
    }

    public function addMovement(Request $request, CashSession $cashSession)
    {
        $data = $request->validate([
            'type' => 'required|string|in:in,out',
            'reason' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0',
            'reference' => 'nullable|string|max:255',
        ]);

        $movement = CashMovement::create([
            'cash_session_id' => $cashSession->id,
            'type' => $data['type'],
            'reason' => $data['reason'] ?? null,
            'amount' => $data['amount'],
            'reference' => $data['reference'] ?? null,
        ]);

        return response()->json($movement, 201);
    }
}
