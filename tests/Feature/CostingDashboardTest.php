<?php

namespace Tests\Feature;

use App\Models\Part;
use App\Models\PieceRate;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RawMaterial;
use App\Models\ProductionStage;
use App\Models\Staff;
use App\Models\User;
use App\Services\PartStockService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CostingDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::firstOrCreate(['name' => 'admin']);
        $u = User::factory()->create(['email_verified_at' => now()]);
        $u->assignRole('admin');

        return $u;
    }

    public function test_costing_combines_material_avg_cost_and_piece_rates(): void
    {
        $user = $this->admin();

        $cloth = RawMaterial::create(['name' => 'Cloth', 'unit' => 'meter', 'is_active' => true]);
        $clothV = $cloth->variants()->create(['name' => 'Double', 'is_active' => true]);
        $body = Part::create(['name' => 'Body', 'is_active' => true]);

        $product = Product::create(['name' => 'Raincoat', 'is_active' => true]);
        $variant = $product->variants()->create(['name' => 'M', 'is_active' => true]);
        $variant->recipeMaterials()->create(['raw_material_variant_id' => $clothV->id, 'quantity' => 2, 'unit' => 'meter']);
        $variant->recipeParts()->create(['part_id' => $body->id, 'quantity_per_garment' => 2]);

        // Stock cloth so it has an average cost of 15.
        app(StockService::class)->receive($clothV->id, 100, 10);
        app(StockService::class)->receive($clothV->id, 100, 20); // avg 15
        app(PartStockService::class)->receiveGood($variant->id, $body->id, 100, 15);

        // Labour excludes cutting because cutting is costed into pre-cut part stock.
        PieceRate::create(['stage' => 'cutting', 'product_variant_id' => null, 'rate' => 5]);
        PieceRate::create(['stage' => 'stitching', 'product_variant_id' => null, 'rate' => 8]);

        $response = $this->actingAs($user)->get(route('costing.index'));
        $response->assertOk();

        // material = 2 body parts * 15 = 30 ; labour = stitching 8 ; unit = 38.
        $response->assertInertia(fn ($page) => $page
            ->component('Costing/Index')
            ->where('rows.0.material_cost', 30)
            ->where('rows.0.labor_cost', 8)
            ->where('rows.0.unit_cost', 38)
        );
    }

    public function test_costing_uses_staff_specific_stage_rates_when_no_global_rate_exists(): void
    {
        $user = $this->admin();

        ProductionStage::firstOrCreate(
            ['slug' => 'packing'],
            ['name' => 'Packing', 'priority_level' => 3, 'is_active' => true],
        );

        $body = Part::create(['name' => 'Body', 'is_active' => true]);
        $product = Product::create(['name' => 'Raincoat', 'is_active' => true]);
        $variant = $product->variants()->create(['name' => 'M', 'is_active' => true]);
        $variant->recipeParts()->create(['part_id' => $body->id, 'quantity_per_garment' => 2]);
        app(PartStockService::class)->receiveGood($variant->id, $body->id, 100, 15);

        $stitcher = Staff::create(['name' => 'Stitcher', 'salary_type' => 'piece_rate', 'is_active' => true]);
        $packer = Staff::create(['name' => 'Packer', 'salary_type' => 'piece_rate', 'is_active' => true]);

        PieceRate::create(['stage' => 'stitching', 'staff_id' => $stitcher->id, 'product_variant_id' => $variant->id, 'rate' => 12]);
        PieceRate::create(['stage' => 'packing', 'staff_id' => $packer->id, 'product_variant_id' => null, 'rate' => 4]);

        $response = $this->actingAs($user)->get(route('costing.index'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Costing/Index')
                ->where('rows.0.material_cost', 30)
                ->where('rows.0.labor.stitching', 12)
                ->where('rows.0.labor.packing', 4)
                ->where('rows.0.labor_cost', 16)
                ->where('rows.0.unit_cost', 46)
            );
    }

    public function test_dashboard_renders_with_stats(): void
    {
        $user = $this->admin();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Dashboard')->has('stats'));
    }

    public function test_dashboard_shows_raw_material_stock_alerts(): void
    {
        $user = $this->admin();

        $cloth = RawMaterial::create([
            'name' => 'Raincoat Cloth',
            'unit' => 'meter',
            'alert_quantity' => 25,
            'is_active' => true,
        ]);
        $clothV = $cloth->variants()->create(['name' => 'Normal', 'is_active' => true]);

        app(StockService::class)->receive($clothV->id, 10, 100);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('stats.low_stock', 1)
                ->where('lowStockMaterials.0.name', 'Raincoat Cloth')
                ->where('lowStockMaterials.0.current_quantity', 10)
                ->where('lowStockMaterials.0.alert_quantity', 25)
                ->where('lowStockMaterials.0.short_by', 15)
            );
    }
}
