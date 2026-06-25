<?php

namespace App\Http\Controllers;

use App\Models\CuttingBatch;
use App\Models\CuttingYieldRule;
use App\Models\Part;
use App\Models\PartConversionRule;
use App\Models\PieceRate;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RawMaterialVariant;
use App\Models\RecoverablePartBalance;
use App\Models\RecoveryCutting;
use App\Models\Staff;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Services\PartStockService;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class CuttingBatchController extends Controller
{
    public function index()
    {
        return Inertia::render('CuttingBatches/Index', [
            'batches' => CuttingBatch::with(['rawMaterialVariant.rawMaterial', 'staff', 'outputs.productVariant.product', 'outputs.part'])
                ->latest()
                ->get()
                ->map(fn (CuttingBatch $batch) => [
                    'id' => $batch->id,
                    'code' => $batch->code,
                    'raw_material_variant_id' => $batch->raw_material_variant_id,
                    'material' => $batch->rawMaterialVariant->rawMaterial->name.' - '.$batch->rawMaterialVariant->name,
                    'material_quantity' => $batch->material_quantity,
                    'staff_id' => $batch->staff_id,
                    'staff' => $batch->staff?->name,
                    'cut_on' => $batch->cut_on?->format('d/m/Y'),
                    'cut_on_input' => $batch->cut_on?->toDateString(),
                    'started_at' => $batch->started_at?->format('Y-m-d\TH:i'),
                    'completed_at' => $batch->completed_at?->format('Y-m-d\TH:i'),
                    'piece_rate' => $batch->piece_rate,
                    'wage_amount' => $batch->wage_amount,
                    'wage_paid_amount' => $batch->wage_paid_amount,
                    'notes' => $batch->notes,
                    'good_total' => $batch->outputs->sum('good_quantity'),
                    'recoverable_total' => $batch->outputs->sum('recoverable_quantity'),
                    'scrap_total' => $batch->outputs->sum('scrap_quantity'),
                    'outputs' => $batch->outputs->map(fn ($output) => [
                        'label' => $this->variantLabel($output->productVariant).' - '.$output->part->name,
                        'product_variant_id' => $output->product_variant_id,
                        'part_id' => $output->part_id,
                        'yield_per_material_unit' => $output->yield_per_material_unit,
                        'expected_quantity' => $output->expected_quantity,
                        'good_quantity' => $output->good_quantity,
                        'recoverable_quantity' => $output->recoverable_quantity,
                        'scrap_quantity' => $output->scrap_quantity,
                    ]),
                ]),
            'recoveries' => RecoveryCutting::with(['fromProductVariant.product', 'fromPart', 'toProductVariant.product', 'toPart', 'staff'])
                ->latest()
                ->limit(100)
                ->get()
                ->map(fn (RecoveryCutting $recovery) => [
                    'id' => $recovery->id,
                    'code' => $recovery->code,
                    'from_product_variant_id' => $recovery->from_product_variant_id,
                    'from_part_id' => $recovery->from_part_id,
                    'from' => $this->variantLabel($recovery->fromProductVariant).' - '.$recovery->fromPart->name,
                    'input_quantity' => $recovery->input_quantity,
                    'to_product_variant_id' => $recovery->to_product_variant_id,
                    'to_part_id' => $recovery->to_part_id,
                    'to' => $this->variantLabel($recovery->toProductVariant).' - '.$recovery->toPart->name,
                    'expected_quantity' => $recovery->expected_quantity,
                    'good_quantity' => $recovery->good_quantity,
                    'scrap_quantity' => $recovery->scrap_quantity,
                    'staff' => $recovery->staff?->name,
                    'staff_id' => $recovery->staff_id,
                    'cut_on' => $recovery->cut_on?->format('d/m/Y'),
                    'cut_on_input' => $recovery->cut_on?->toDateString(),
                    'started_at' => $recovery->started_at?->format('Y-m-d\TH:i'),
                    'completed_at' => $recovery->completed_at?->format('Y-m-d\TH:i'),
                    'piece_rate' => $recovery->piece_rate,
                    'wage_amount' => $recovery->wage_amount,
                    'wage_paid_amount' => $recovery->wage_paid_amount,
                    'notes' => $recovery->notes,
                ]),
            'rawMaterialOptions' => RawMaterialVariant::with('rawMaterial:id,name')
                ->where('is_active', true)
                ->get()
                ->map(function (RawMaterialVariant $variant) {
                    $available = (float) (StockBalance::where('raw_material_variant_id', $variant->id)->value('quantity') ?? 0);

                    return [
                    'id' => $variant->id,
                    'label' => $variant->rawMaterial->name.' - '.$variant->name,
                    'available_quantity' => round($available, 3),
                    ];
                })
                ->sortBy('label')
                ->values(),
            'variantOptions' => $this->variantOptions(),
            'partOptions' => Part::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'staffOptions' => $this->cuttingStaffOptions(),
            'recoverableOptions' => RecoverablePartBalance::with(['productVariant.product', 'part'])
                ->where('quantity', '>', 0)
                ->get()
                ->map(fn (RecoverablePartBalance $balance) => [
                    'product_variant_id' => $balance->product_variant_id,
                    'part_id' => $balance->part_id,
                    'label' => $this->variantLabel($balance->productVariant).' - '.$balance->part->name,
                    'quantity' => $balance->quantity,
                ])
                ->sortBy('label')
                ->values(),
            'conversionRules' => PartConversionRule::where('is_active', true)->get([
                'from_product_variant_id',
                'from_part_id',
                'to_product_variant_id',
                'to_part_id',
                'output_per_input',
            ]),
            'cuttingYieldRules' => CuttingYieldRule::query()
                ->where('is_active', true)
                ->get(['raw_material_variant_id', 'product_variant_id', 'part_id', 'yield_per_material_unit']),
            'pieceRateOptions' => PieceRate::query()
                ->where('stage', 'cutting')
                ->get(['staff_id', 'product_variant_id', 'rate']),
            'today' => now()->toDateString(),
        ]);
    }

    public function store(Request $request, StockService $stock, PartStockService $partStock)
    {
        $data = $this->validateBatch($request);

        try {
            DB::transaction(function () use ($data, $stock, $partStock) {
                $batch = CuttingBatch::create([
                    ...collect($data)->except('outputs')->all(),
                    'cut_on' => $this->cutOnFromData($data),
                    'created_by' => Auth::id(),
                ]);
                $batch->update(['code' => 'CUT-'.str_pad((string) $batch->id, 5, '0', STR_PAD_LEFT)]);

                $this->applyBatch($batch, $data, $stock, $partStock);
            });
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['material_quantity' => $exception->getMessage()])->withInput();
        }

        return back()->with('success', 'Cutting batch recorded and part stock updated.');
    }

    public function update(Request $request, CuttingBatch $cuttingBatch, StockService $stock, PartStockService $partStock)
    {
        $data = $this->validateBatch($request);

        try {
            DB::transaction(function () use ($cuttingBatch, $data, $stock, $partStock) {
                $this->reverseBatch($cuttingBatch, $stock, $partStock, 'Reversed before editing '.$cuttingBatch->code);

                $cuttingBatch->update([
                    ...collect($data)->except('outputs')->all(),
                    'cut_on' => $this->cutOnFromData($data),
                ]);

                $this->applyBatch($cuttingBatch, $data, $stock, $partStock);
            });
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['cutting_batch' => $exception->getMessage()]);
        }

        return back()->with('success', 'Cutting batch updated and stock reconciled.');
    }

    public function destroy(CuttingBatch $cuttingBatch, StockService $stock, PartStockService $partStock)
    {
        try {
            DB::transaction(function () use ($cuttingBatch, $stock, $partStock) {
                $this->reverseBatch($cuttingBatch, $stock, $partStock, 'Deleted '.$cuttingBatch->code);
                $cuttingBatch->delete();
            });
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Cutting batch deleted and stock reversed.');
    }

    private function validateBatch(Request $request): array
    {
        $data = $request->validate([
            'raw_material_variant_id' => ['required', 'exists:raw_material_variants,id'],
            'material_quantity' => ['required', 'numeric', 'min:0.001'],
            'staff_id' => ['nullable', 'exists:staff,id'],
            'cut_on' => ['nullable', 'date'],
            'started_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],
            'piece_rate' => ['nullable', 'numeric', 'min:0'],
            'wage_paid_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'outputs' => ['required', 'array', 'min:1'],
            'outputs.*.product_variant_id' => ['required', 'exists:product_variants,id'],
            'outputs.*.part_id' => ['required', 'exists:parts,id'],
            'outputs.*.yield_per_material_unit' => ['nullable', 'numeric', 'min:0.001'],
            'outputs.*.expected_quantity' => ['nullable', 'integer', 'min:0'],
            'outputs.*.good_quantity' => ['nullable', 'integer', 'min:0'],
            'outputs.*.recoverable_quantity' => ['nullable', 'integer', 'min:0'],
            'outputs.*.scrap_quantity' => ['nullable', 'integer', 'min:0'],
        ]);

        $usedOutputKeys = [];
        $materialUsedByOutputs = 0.0;

        foreach ($data['outputs'] as $index => $output) {
            $outputKey = $output['product_variant_id'].':'.$output['part_id'];
            if (isset($usedOutputKeys[$outputKey])) {
                throw ValidationException::withMessages([
                    "outputs.{$index}.part_id" => 'This product variant and part is already added in another output row.',
                ]);
            }
            $usedOutputKeys[$outputKey] = true;

            $actualTotal = (int) ($output['good_quantity'] ?? 0)
                + (int) ($output['recoverable_quantity'] ?? 0)
                + (int) ($output['scrap_quantity'] ?? 0);

            if ($actualTotal <= 0) {
                throw ValidationException::withMessages([
                    "outputs.{$index}.good_quantity" => 'Enter good, recoverable, or scrap quantity for this output row.',
                ]);
            }

            $yield = $this->resolveYieldPerMaterialUnit($data, $output);
            if ($yield <= 0) {
                throw ValidationException::withMessages([
                    "outputs.{$index}.yield_per_material_unit" => 'Add a yield rule or enter yield per material unit for this output.',
                ]);
            }

            $maxOutput = (int) floor((float) $data['material_quantity'] * $yield);
            if ($actualTotal > $maxOutput) {
                throw ValidationException::withMessages([
                    "outputs.{$index}.good_quantity" => "This material quantity can cut only {$maxOutput} pieces for this output. Increase material used or reduce output.",
                ]);
            }

            $materialUsedByOutputs += $actualTotal / $yield;
        }

        if ($materialUsedByOutputs > ((float) $data['material_quantity'] + 0.000001)) {
            throw ValidationException::withMessages([
                'material_quantity' => 'Output rows consume '.round($materialUsedByOutputs, 3).' material units, but only '.$data['material_quantity'].' was entered.',
            ]);
        }

        if (! empty($data['staff_id']) && ! $this->staffMatchesCutting((int) $data['staff_id'])) {
            throw ValidationException::withMessages([
                'staff_id' => 'Selected staff must have the Cutting designation priority.',
            ]);
        }

        $data['staff_id'] = $data['staff_id'] ?? null;
        $data['piece_rate'] = (float) ($data['piece_rate'] ?? 0);
        $data['wage_paid_amount'] = (float) ($data['wage_paid_amount'] ?? 0);

        return $data;
    }

    public function recover(Request $request, PartStockService $partStock)
    {
        $data = $this->validateRecovery($request, $partStock);

        DB::transaction(function () use ($data, $partStock) {
            $recovery = RecoveryCutting::create([
                ...$data,
                'expected_quantity' => (int) ($data['expected_quantity'] ?? $data['good_quantity']),
                'scrap_quantity' => (int) ($data['scrap_quantity'] ?? 0),
                'cut_on' => $this->cutOnFromData($data),
                'created_by' => Auth::id(),
            ]);
            $recovery->update(['code' => 'RCV-'.str_pad((string) $recovery->id, 5, '0', STR_PAD_LEFT)]);

            $this->applyRecovery($recovery, $partStock);
        });

        return back()->with('success', 'Recovery cutting recorded.');
    }

    public function updateRecovery(Request $request, RecoveryCutting $recoveryCutting, PartStockService $partStock)
    {
        try {
            DB::transaction(function () use ($request, $recoveryCutting, $partStock) {
                $this->reverseRecovery($recoveryCutting, $partStock, 'Reversed before editing '.$recoveryCutting->code);

                $data = $this->validateRecovery($request, $partStock);
                $recoveryCutting->update([
                    ...$data,
                    'expected_quantity' => (int) ($data['expected_quantity'] ?? $data['good_quantity']),
                    'scrap_quantity' => (int) ($data['scrap_quantity'] ?? 0),
                    'cut_on' => $this->cutOnFromData($data),
                ]);

                $this->applyRecovery($recoveryCutting, $partStock);
            });
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['recovery_cutting' => $exception->getMessage()]);
        }

        return back()->with('success', 'Recovery cutting updated and stock reconciled.');
    }

    public function destroyRecovery(RecoveryCutting $recoveryCutting, PartStockService $partStock)
    {
        try {
            DB::transaction(function () use ($recoveryCutting, $partStock) {
                $this->reverseRecovery($recoveryCutting, $partStock, 'Deleted '.$recoveryCutting->code);
                $recoveryCutting->delete();
            });
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Recovery cutting deleted and stock reversed.');
    }

    private function validateRecovery(Request $request, PartStockService $partStock): array
    {
        $data = $request->validate([
            'from_product_variant_id' => ['required', 'exists:product_variants,id'],
            'from_part_id' => ['required', 'exists:parts,id'],
            'input_quantity' => ['required', 'integer', 'min:1'],
            'to_product_variant_id' => ['required', 'exists:product_variants,id'],
            'to_part_id' => ['required', 'exists:parts,id'],
            'expected_quantity' => ['nullable', 'integer', 'min:0'],
            'good_quantity' => ['required', 'integer', 'min:0'],
            'scrap_quantity' => ['nullable', 'integer', 'min:0'],
            'staff_id' => ['nullable', 'exists:staff,id'],
            'cut_on' => ['nullable', 'date'],
            'started_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],
            'piece_rate' => ['nullable', 'numeric', 'min:0'],
            'wage_paid_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $available = $partStock->availableRecoverable((int) $data['from_product_variant_id'], (int) $data['from_part_id']);
        if ($available < (int) $data['input_quantity']) {
            throw ValidationException::withMessages([
                'input_quantity' => "Only {$available} recoverable pieces are available.",
            ]);
        }

        if ((int) $data['good_quantity'] + (int) ($data['scrap_quantity'] ?? 0) <= 0) {
            throw ValidationException::withMessages([
                'good_quantity' => 'Enter good output or scrap quantity.',
            ]);
        }

        if (! empty($data['staff_id']) && ! $this->staffMatchesCutting((int) $data['staff_id'])) {
            throw ValidationException::withMessages([
                'staff_id' => 'Selected staff must have the Cutting designation priority.',
            ]);
        }

        $data['staff_id'] = $data['staff_id'] ?? null;
        $data['piece_rate'] = (float) ($data['piece_rate'] ?? 0);
        if ($data['piece_rate'] <= 0) {
            $data['piece_rate'] = PieceRate::resolve('cutting', (int) $data['to_product_variant_id'], $data['staff_id'] ? (int) $data['staff_id'] : null);
        }
        $data['wage_paid_amount'] = (float) ($data['wage_paid_amount'] ?? 0);
        $data['scrap_quantity'] = (int) ($data['scrap_quantity'] ?? 0);

        return $data;
    }

    private function applyBatch(CuttingBatch $batch, array $data, StockService $stock, PartStockService $partStock): void
    {
        $batch->outputs()->delete();

        $materialMovement = $stock->issue(
            (int) $data['raw_material_variant_id'],
            (float) $data['material_quantity'],
            CuttingBatch::class,
            $batch->id,
            'Cutting batch '.$batch->code,
        );

        $goodTotal = collect($data['outputs'])->sum(fn ($output) => (int) ($output['good_quantity'] ?? 0));
        $outputCostQuantity = collect($data['outputs'])->sum(
            fn ($output) => (int) ($output['good_quantity'] ?? 0) + (int) ($output['recoverable_quantity'] ?? 0)
        );
        $firstVariantId = collect($data['outputs'])->pluck('product_variant_id')->filter()->first();
        $pieceRate = (float) ($data['piece_rate'] ?? 0);
        if ($pieceRate <= 0 && $firstVariantId) {
            $pieceRate = PieceRate::resolve('cutting', (int) $firstVariantId, $data['staff_id'] ? (int) $data['staff_id'] : null);
        }
        $wageAmount = round($goodTotal * $pieceRate, 2);
        $materialCost = (float) $data['material_quantity'] * (float) $materialMovement->unit_cost;
        $unitCost = $outputCostQuantity > 0 ? ($materialCost + $wageAmount) / $outputCostQuantity : 0;

        $batch->forceFill([
            'piece_rate' => $pieceRate,
            'wage_amount' => $wageAmount,
            'wage_paid_amount' => (float) ($data['wage_paid_amount'] ?? 0),
        ])->save();

        foreach ($data['outputs'] as $output) {
            $line = $batch->outputs()->create([
                'product_variant_id' => $output['product_variant_id'],
                'part_id' => $output['part_id'],
                'yield_per_material_unit' => $this->resolveYieldPerMaterialUnit($data, $output) ?: null,
                'expected_quantity' => (int) ($output['expected_quantity'] ?? 0),
                'good_quantity' => (int) ($output['good_quantity'] ?? 0),
                'recoverable_quantity' => (int) ($output['recoverable_quantity'] ?? 0),
                'scrap_quantity' => (int) ($output['scrap_quantity'] ?? 0),
            ]);

            $partStock->receiveGood($line->product_variant_id, $line->part_id, $line->good_quantity, $unitCost, CuttingBatch::class, $batch->id, 'Good output from '.$batch->code);
            $partStock->receiveRecoverable($line->product_variant_id, $line->part_id, $line->recoverable_quantity, $unitCost, CuttingBatch::class, $batch->id, 'Recoverable output from '.$batch->code);
            $partStock->recordScrap($line->product_variant_id, $line->part_id, $line->scrap_quantity, $unitCost, CuttingBatch::class, $batch->id, 'Scrap from '.$batch->code);
        }
    }

    private function reverseBatch(CuttingBatch $batch, StockService $stock, PartStockService $partStock, string $note): void
    {
        $batch->loadMissing('outputs');

        foreach ($batch->outputs as $output) {
            $partStock->issueGood($output->product_variant_id, $output->part_id, (int) $output->good_quantity, CuttingBatch::class, $batch->id, $note);
            $partStock->issueRecoverable($output->product_variant_id, $output->part_id, (int) $output->recoverable_quantity, CuttingBatch::class, $batch->id, $note);
            $partStock->recordScrapReversal($output->product_variant_id, $output->part_id, (int) $output->scrap_quantity, CuttingBatch::class, $batch->id, $note);
        }

        $unitCost = (float) (StockMovement::query()
            ->where('reference_type', CuttingBatch::class)
            ->where('reference_id', $batch->id)
            ->where('raw_material_variant_id', $batch->raw_material_variant_id)
            ->where('direction', 'out')
            ->oldest()
            ->value('unit_cost') ?? 0);

        $stock->receive(
            $batch->raw_material_variant_id,
            (float) $batch->material_quantity,
            $unitCost,
            CuttingBatch::class,
            $batch->id,
            $note,
        );

        $batch->outputs()->delete();
    }

    private function applyRecovery(RecoveryCutting $recovery, PartStockService $partStock): void
    {
        $movement = $partStock->issueRecoverable($recovery->from_product_variant_id, $recovery->from_part_id, $recovery->input_quantity, RecoveryCutting::class, $recovery->id, 'Recovery cutting '.$recovery->code);
        $pieceRate = (float) ($recovery->piece_rate ?? 0);
        $wageAmount = round((int) $recovery->good_quantity * $pieceRate, 2);
        $inputCost = (int) $recovery->input_quantity * (float) ($movement?->unit_cost ?? 0);
        $outputUnitCost = (int) $recovery->good_quantity > 0 ? ($inputCost + $wageAmount) / (int) $recovery->good_quantity : 0;

        $recovery->forceFill([
            'wage_amount' => $wageAmount,
        ])->save();

        $partStock->receiveGood($recovery->to_product_variant_id, $recovery->to_part_id, $recovery->good_quantity, $outputUnitCost, RecoveryCutting::class, $recovery->id, 'Recovered output from '.$recovery->code);
        $partStock->recordScrap($recovery->from_product_variant_id, $recovery->from_part_id, $recovery->scrap_quantity, (float) ($movement?->unit_cost ?? 0), RecoveryCutting::class, $recovery->id, 'Recovery scrap from '.$recovery->code);
    }

    private function reverseRecovery(RecoveryCutting $recovery, PartStockService $partStock, string $note): void
    {
        $partStock->issueGood($recovery->to_product_variant_id, $recovery->to_part_id, (int) $recovery->good_quantity, RecoveryCutting::class, $recovery->id, $note);
        $movement = \App\Models\PartStockMovement::query()
            ->where('reference_type', RecoveryCutting::class)
            ->where('reference_id', $recovery->id)
            ->where('product_variant_id', $recovery->from_product_variant_id)
            ->where('part_id', $recovery->from_part_id)
            ->where('stock_type', 'recoverable')
            ->where('direction', 'out')
            ->oldest()
            ->first();

        $partStock->receiveRecoverable($recovery->from_product_variant_id, $recovery->from_part_id, (int) $recovery->input_quantity, (float) ($movement?->unit_cost ?? 0), RecoveryCutting::class, $recovery->id, $note);
        $partStock->recordScrapReversal($recovery->from_product_variant_id, $recovery->from_part_id, (int) $recovery->scrap_quantity, RecoveryCutting::class, $recovery->id, $note);
    }

    private function variantOptions()
    {
        return ProductVariant::query()
            ->with('product:id,name,source_type')
            ->where('is_active', true)
            ->whereHas('product', fn ($query) => $query->whereIn('source_type', [Product::SOURCE_IN_HOUSE, Product::SOURCE_BOTH]))
            ->get()
            ->map(fn (ProductVariant $variant) => [
                'id' => $variant->id,
                'label' => $this->variantLabel($variant),
            ])
            ->sortBy('label')
            ->values();
    }

    private function variantLabel(ProductVariant $variant): string
    {
        return $variant->product->name.' - '.$variant->name;
    }

    private function cutOnFromData(array $data): string
    {
        if (! empty($data['cut_on'])) {
            return Carbon::parse($data['cut_on'])->toDateString();
        }

        if (! empty($data['started_at'])) {
            return Carbon::parse($data['started_at'])->toDateString();
        }

        return now()->toDateString();
    }

    private function resolveYieldPerMaterialUnit(array $data, array $output): float
    {
        $explicit = (float) ($output['yield_per_material_unit'] ?? 0);
        if ($explicit > 0) {
            return $explicit;
        }

        return (float) (CuttingYieldRule::query()
            ->where('is_active', true)
            ->where('raw_material_variant_id', $data['raw_material_variant_id'])
            ->where('product_variant_id', $output['product_variant_id'])
            ->where('part_id', $output['part_id'])
            ->value('yield_per_material_unit') ?? 0);
    }

    private function cuttingStaffOptions()
    {
        $cuttingPriority = \App\Models\ProductionStage::query()
            ->active()
            ->where('slug', 'cutting')
            ->value('priority_level');

        return Staff::query()
            ->leftJoin('designations', 'designations.id', '=', 'staff.designation_id')
            ->where('staff.is_active', true)
            ->when($cuttingPriority, fn ($query) => $query->where('designations.priority_level', $cuttingPriority))
            ->orderBy('staff.name')
            ->get(['staff.id', 'staff.name']);
    }

    private function staffMatchesCutting(int $staffId): bool
    {
        $cuttingPriority = \App\Models\ProductionStage::query()
            ->active()
            ->where('slug', 'cutting')
            ->value('priority_level');

        if (! $cuttingPriority) {
            return false;
        }

        return Staff::query()
            ->leftJoin('designations', 'designations.id', '=', 'staff.designation_id')
            ->where('staff.id', $staffId)
            ->where('staff.is_active', true)
            ->where('designations.priority_level', $cuttingPriority)
            ->exists();
    }
}
