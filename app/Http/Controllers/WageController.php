<?php

namespace App\Http\Controllers;

use App\Models\CuttingBatch;
use App\Models\JobCard;
use App\Models\RecoveryCutting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WageController extends Controller
{
    public function index(Request $request)
    {
        $from = $request->date('from') ?? now()->startOfMonth();
        $to = $request->date('to') ?? now()->endOfMonth();

        $rows = collect();

        JobCard::query()
            ->whereNotNull('staff_id')
            ->where('status', 'completed')
            ->whereBetween('completed_on', [$from->toDateString(), $to->toDateString()])
            ->with('staff:id,name')
            ->get()
            ->each(fn (JobCard $card) => $rows->push([
                'staff_id' => $card->staff_id,
                'staff' => $card->staff?->name ?? '-',
                'pieces' => (int) $card->quantity_received,
                'wage' => (float) $card->wage_amount,
                'cards' => 1,
            ]));

        CuttingBatch::query()
            ->whereNotNull('staff_id')
            ->whereBetween('cut_on', [$from->toDateString(), $to->toDateString()])
            ->with('staff:id,name')
            ->get()
            ->each(fn (CuttingBatch $batch) => $rows->push([
                'staff_id' => $batch->staff_id,
                'staff' => $batch->staff?->name ?? '-',
                'pieces' => (int) $batch->outputs()->sum('good_quantity'),
                'wage' => (float) $batch->wage_amount,
                'cards' => 1,
            ]));

        RecoveryCutting::query()
            ->whereNotNull('staff_id')
            ->whereBetween('cut_on', [$from->toDateString(), $to->toDateString()])
            ->with('staff:id,name')
            ->get()
            ->each(fn (RecoveryCutting $cutting) => $rows->push([
                'staff_id' => $cutting->staff_id,
                'staff' => $cutting->staff?->name ?? '-',
                'pieces' => (int) $cutting->good_quantity,
                'wage' => (float) $cutting->wage_amount,
                'cards' => 1,
            ]));

        $byStaff = $rows
            ->groupBy('staff_id')
            ->map(fn ($group) => [
                'staff' => $group->first()['staff'] ?? '-',
                'pieces' => (int) $group->sum('pieces'),
                'wage' => round((float) $group->sum('wage'), 2),
                'cards' => $group->count(),
            ])
            ->values()
            ->sortByDesc('wage')
            ->values();

        return Inertia::render('Wages/Index', [
            'rows' => $byStaff,
            'total' => round((float) $rows->sum('wage'), 2),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ]);
    }
}
