<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventExpense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventExpenseController extends Controller
{
    public function index(Event $event): JsonResponse
    {
        $expenses = $event->expenses()
            ->orderByDesc('expense_date')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (EventExpense $expense) => $this->formatExpense($expense));

        return response()->json([
            'success' => true,
            'data' => $expenses,
        ]);
    }

    public function store(Request $request, Event $event): JsonResponse
    {
        $user = $request->user();

        if ($user === null || ! $user->role->canManageEvents()) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak',
            ], 403);
        }

        $validated = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'expense_date' => ['required', 'date'],
        ]);

        $expense = $event->expenses()->create([
            ...$validated,
            'created_by' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Biaya berhasil ditambahkan',
            'data' => $this->formatExpense($expense),
        ], 201);
    }

    public function update(Request $request, Event $event, EventExpense $expense): JsonResponse
    {
        if ($expense->event_id !== $event->id) {
            abort(404);
        }

        $user = $request->user();

        if ($user === null || ! $user->role->canManageEvents()) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak',
            ], 403);
        }

        $validated = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'expense_date' => ['required', 'date'],
        ]);

        $expense->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Biaya berhasil diperbarui',
            'data' => $this->formatExpense($expense->fresh()),
        ]);
    }

    public function destroy(Event $event, EventExpense $expense): JsonResponse
    {
        if ($expense->event_id !== $event->id) {
            abort(404);
        }

        $user = request()->user();

        if ($user === null || ! $user->role->canManageEvents()) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak',
            ], 403);
        }

        $expense->delete();

        return response()->json([
            'success' => true,
            'message' => 'Biaya berhasil dihapus',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatExpense(EventExpense $expense): array
    {
        return [
            'id' => $expense->id,
            'event_id' => $expense->event_id,
            'description' => $expense->description,
            'amount' => (float) $expense->amount,
            'expense_date' => $expense->expense_date->toDateString(),
            'created_by' => $expense->created_by,
            'created_at' => $expense->created_at?->toIso8601String(),
            'updated_at' => $expense->updated_at?->toIso8601String(),
        ];
    }
}
