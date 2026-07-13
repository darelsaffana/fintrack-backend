<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function summary(Request $request)
    {
        $user = $request->user();

        $income = (clone $user->transactions())->where('type', 'income')->sum('amount');
        $expense = (clone $user->transactions())->where('type', 'expense')->sum('amount');

        $recent = $user->transactions()->with('category')->orderByDesc('date')->limit(6)->get();

        $history = $user->transactions()->orderBy('date')->get(['date', 'type', 'amount']);
        $running = 0;
        $byDay = [];
        foreach ($history as $t) {
            $running += $t->type === 'income' ? (float) $t->amount : -(float) $t->amount;
            $byDay[$t->date->format('Y-m-d')] = $running;
        }
        $saldoHistory = array_slice(array_values($byDay), -14);

        return response()->json([
            'income' => (float) $income,
            'expense' => (float) $expense,
            'balance' => (float) $income - (float) $expense,
            'recent_transactions' => $recent,
            'saldo_history' => $saldoHistory,
        ]);
    }
}
