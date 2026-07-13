<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function byCategory(Request $request)
    {
        $rows = $request->user()->transactions()
            ->where('type', 'expense')
            ->with('category')
            ->select('category_id', DB::raw('SUM(amount) as total'))
            ->groupBy('category_id')
            ->get()
            ->map(fn ($row) => [
                'category_id' => $row->category_id,
                'name' => $row->category->name ?? 'Lainnya',
                'color' => $row->category->color ?? '#7d8aa8',
                'total' => (float) $row->total,
            ]);

        return response()->json($rows);
    }

    public function byMonth(Request $request)
    {
        // Grouped in PHP (rather than a DB-specific DATE_FORMAT/strftime) so this
        // works the same on MySQL, PostgreSQL, and SQLite without changes.
        $months = (int) $request->query('months', 6);

        $transactions = $request->user()->transactions()->orderBy('date')->get(['date', 'type', 'amount']);

        $grouped = [];
        foreach ($transactions as $t) {
            $key = $t->date->format('Y-m');
            $grouped[$key]['month'] = $key;
            $grouped[$key]['income'] = ($grouped[$key]['income'] ?? 0) + ($t->type === 'income' ? (float) $t->amount : 0);
            $grouped[$key]['expense'] = ($grouped[$key]['expense'] ?? 0) + ($t->type === 'expense' ? (float) $t->amount : 0);
        }

        $result = array_values($grouped);

        return response()->json(array_slice($result, -$months));
    }
}
