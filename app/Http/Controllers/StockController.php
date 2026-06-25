<?php

namespace App\Http\Controllers;

use App\Models\RawMaterialVariant;
use App\Models\StockMovement;
use App\Services\StockService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StockController extends Controller
{
    public function index()
    {
        // Every active material variant, with its current balance (0 if never stocked).
        $rows = RawMaterialVariant::query()
            ->where('is_active', true)
            ->with('rawMaterial:id,name,unit')
            ->leftJoin('stock_balances', 'stock_balances.raw_material_variant_id', '=', 'raw_material_variants.id')
            ->orderBy('raw_material_variants.name')
            ->get([
                'raw_material_variants.id',
                'raw_material_variants.name',
                'raw_material_variants.raw_material_id',
                'stock_balances.quantity',
                'stock_balances.average_cost',
            ])
            ->map(fn ($v) => [
                'id' => $v->id,
                'label' => $v->rawMaterial->name.' — '.$v->name,
                'unit' => $v->rawMaterial->unit,
                'quantity' => (float) ($v->quantity ?? 0),
                'average_cost' => (float) ($v->average_cost ?? 0),
                'value' => round((float) ($v->quantity ?? 0) * (float) ($v->average_cost ?? 0), 2),
            ]);

        return Inertia::render('Stock/Index', [
            'rows' => $rows,
            'totalValue' => round($rows->sum('value'), 2),
        ]);
    }

    public function adjust(Request $request, StockService $stock)
    {
        $data = $request->validate([
            'raw_material_variant_id' => ['required', 'exists:raw_material_variants,id'],
            'counted_quantity' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $stock->adjust(
            (int) $data['raw_material_variant_id'],
            (float) $data['counted_quantity'],
            $data['note'] ?? null,
        );

        return back()->with('success', 'Raw material stock adjusted.');
    }

    public function movements()
    {
        return Inertia::render('Stock/Movements', [
            'movements' => StockMovement::query()
                ->with('rawMaterialVariant.rawMaterial:id,name')
                ->latest('id')
                ->limit(300)
                ->get()
                ->map(fn ($m) => [
                    'id' => $m->id,
                    'label' => $m->rawMaterialVariant->rawMaterial->name.' — '.$m->rawMaterialVariant->name,
                    'direction' => $m->direction,
                    'quantity' => $m->quantity,
                    'unit_cost' => $m->unit_cost,
                    'balance_quantity' => $m->balance_quantity,
                    'note' => $m->note,
                    'at' => $m->created_at->format('Y-m-d H:i'),
                ]),
        ]);
    }
}
