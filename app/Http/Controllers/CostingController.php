<?php

namespace App\Http\Controllers;

use App\Models\ProductionStage;
use App\Services\ProductCostingService;
use Inertia\Inertia;

class CostingController extends Controller
{
    public function index(ProductCostingService $costing)
    {
        $stages = ProductionStage::options()
            ->reject(fn (array $stage) => $stage['value'] === 'cutting')
            ->values();

        return Inertia::render('Costing/Index', [
            'rows' => $costing->rows()->sortBy('label')->values(),
            'stages' => $stages,
        ]);
    }
}
