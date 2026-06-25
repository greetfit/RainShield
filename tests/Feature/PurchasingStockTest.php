<?php

namespace Tests\Feature;

use App\Models\RawMaterial;
use App\Models\RawMaterialVariant;
use App\Models\FinishedGood;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\StockBalance;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchasingStockTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string $role): User
    {
        Role::firstOrCreate(['name' => $role]);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function variant(string $name, string $unit = 'piece'): RawMaterialVariant
    {
        $m = RawMaterial::create(['name' => $name, 'unit' => $unit, 'is_active' => true]);

        return $m->variants()->create(['name' => 'Std', 'is_active' => true]);
    }

    public function test_purchase_allocates_transport_by_value_and_updates_stock(): void
    {
        $user = $this->actor('stock_manager');
        $cloth = $this->variant('Cloth', 'meter');   // line value 1000
        $zip = $this->variant('Zip');                // line value 1000

        $this->actingAs($user)->post(route('purchases.store'), [
            'purchased_on' => now()->toDateString(),
            'transport_charge' => 200,
            'allocation_method' => 'value',
            'items' => [
                ['raw_material_variant_id' => $cloth->id, 'quantity' => 100, 'unit_price' => 10], // 1000
                ['raw_material_variant_id' => $zip->id, 'quantity' => 200, 'unit_price' => 5],     // 1000
            ],
        ])->assertRedirect(route('purchases.index'));

        // Equal line values → transport (200) splits 100/100.
        $this->assertDatabaseHas('purchase_items', [
            'raw_material_variant_id' => $cloth->id,
            'allocated_transport' => 100,
            'landed_unit_cost' => 11.0000, // (1000 + 100) / 100
        ]);

        // Stock balance reflects landed cost.
        $clothBal = StockBalance::where('raw_material_variant_id', $cloth->id)->first();
        $this->assertEquals(100, (float) $clothBal->quantity);
        $this->assertEquals(11.0, (float) $clothBal->average_cost);
    }

    public function test_weighted_average_blends_two_receipts(): void
    {
        $stock = app(StockService::class);
        $v = $this->variant('Elastic', 'meter');

        $stock->receive($v->id, 100, 10);   // 100 @ 10
        $stock->receive($v->id, 100, 20);   // +100 @ 20  → avg 15

        $bal = StockBalance::where('raw_material_variant_id', $v->id)->first();
        $this->assertEquals(200, (float) $bal->quantity);
        $this->assertEquals(15.0, (float) $bal->average_cost);

        // Issue keeps the average, reduces qty.
        $stock->issue($v->id, 50);
        $bal->refresh();
        $this->assertEquals(150, (float) $bal->quantity);
        $this->assertEquals(15.0, (float) $bal->average_cost);
    }

    public function test_issue_beyond_balance_throws(): void
    {
        $stock = app(StockService::class);
        $v = $this->variant('Thread', 'spool');
        $stock->receive($v->id, 10, 5);

        $this->expectException(\RuntimeException::class);
        $stock->issue($v->id, 25);
    }

    public function test_stock_manager_can_adjust_raw_stock_to_physical_count(): void
    {
        $user = $this->actor('stock_manager');
        $v = $this->variant('Badge');
        app(StockService::class)->receive($v->id, 10, 2);

        $this->actingAs($user)->post(route('stock.adjust'), [
            'raw_material_variant_id' => $v->id,
            'counted_quantity' => 7,
            'note' => 'Physical count',
        ])->assertRedirect();

        $this->assertEquals(7, (float) StockBalance::where('raw_material_variant_id', $v->id)->value('quantity'));
        $this->assertDatabaseHas('stock_movements', [
            'raw_material_variant_id' => $v->id,
            'direction' => 'adjustment',
            'quantity' => 3,
            'note' => 'Physical count',
        ]);
    }

    public function test_stock_manager_can_record_purchase_payment(): void
    {
        $user = $this->actor('stock_manager');
        $variant = $this->variant('Cloth', 'meter');

        $this->actingAs($user)->post(route('purchases.store'), [
            'purchased_on' => now()->toDateString(),
            'transport_charge' => 0,
            'allocation_method' => 'value',
            'items' => [
                ['raw_material_variant_id' => $variant->id, 'quantity' => 10, 'unit_price' => 100],
            ],
        ])->assertRedirect(route('purchases.index'));

        $purchase = Purchase::first();

        $this->actingAs($user)->post(route('purchases.payments.store', $purchase), [
            'paid_on' => now()->toDateString(),
            'amount' => 400,
            'method' => 'Cash',
        ])->assertRedirect();

        $this->assertDatabaseHas('purchase_payments', [
            'purchase_id' => $purchase->id,
            'amount' => 400,
            'method' => 'Cash',
        ]);
    }

    public function test_finished_product_purchase_updates_finished_goods_stock(): void
    {
        $user = $this->actor('stock_manager');
        $product = Product::create(['name' => 'Rain Coat', 'category' => 'Rain Wear', 'is_active' => true]);
        $variant = $product->variants()->create([
            'name' => 'Medium',
            'selling_price' => 0,
            'profit_margin_percent' => 25,
            'profit_markup_type' => 'percent',
            'profit_markup_amount' => 0,
            'is_active' => true,
        ]);

        $this->actingAs($user)->post(route('purchases.store'), [
            'purchased_on' => now()->toDateString(),
            'transport_charge' => 100,
            'allocation_method' => 'value',
            'items' => [
                [
                    'item_type' => 'finished_good',
                    'product_variant_id' => $variant->id,
                    'quantity' => 10,
                    'unit_price' => 500,
                ],
            ],
        ])->assertRedirect(route('purchases.index'));

        $stock = FinishedGood::where('product_variant_id', $variant->id)->first();

        $this->assertEquals(10, $stock->quantity);
        $this->assertEquals(510, (float) $stock->average_cost);
        $this->assertDatabaseHas('purchase_items', [
            'item_type' => 'finished_good',
            'product_variant_id' => $variant->id,
            'landed_unit_cost' => 510,
        ]);
    }

    public function test_stock_manager_can_edit_purchase_and_sync_stock_quantity(): void
    {
        $user = $this->actor('stock_manager');
        $variant = $this->variant('Thread');

        $this->actingAs($user)->post(route('purchases.store'), [
            'purchased_on' => now()->toDateString(),
            'transport_charge' => 0,
            'allocation_method' => 'value',
            'items' => [
                ['raw_material_variant_id' => $variant->id, 'quantity' => 10, 'unit_price' => 20],
            ],
        ])->assertRedirect(route('purchases.index'));

        $purchase = Purchase::with('items')->first();

        $this->actingAs($user)->put(route('purchases.update', $purchase), [
            'reference' => 'INV-2',
            'supplier_name' => 'Supplier A',
            'purchased_on' => now()->toDateString(),
            'transport_charge' => 0,
            'allocation_method' => 'value',
            'items' => [
                [
                    'id' => $purchase->items->first()->id,
                    'raw_material_variant_id' => $variant->id,
                    'quantity' => 6,
                    'unit_price' => 20,
                ],
            ],
        ])->assertRedirect();

        $this->assertEquals(6, (float) StockBalance::where('raw_material_variant_id', $variant->id)->value('quantity'));
        $this->assertDatabaseHas('purchases', [
            'id' => $purchase->id,
            'reference' => 'INV-2',
            'supplier_name' => 'Supplier A',
            'grand_total' => 120,
        ]);
    }

    public function test_stock_manager_can_update_purchase_status_only(): void
    {
        $user = $this->actor('stock_manager');
        $variant = $this->variant('Thread');

        $this->actingAs($user)->post(route('purchases.store'), [
            'purchased_on' => now()->toDateString(),
            'status' => 'placed',
            'transport_charge' => 0,
            'allocation_method' => 'value',
            'items' => [
                ['raw_material_variant_id' => $variant->id, 'quantity' => 10, 'unit_price' => 20],
            ],
        ])->assertRedirect(route('purchases.index'));

        $purchase = Purchase::first();

        $this->actingAs($user)->patch(route('purchases.status.update', $purchase), [
            'status' => 'partially_received',
        ])->assertRedirect();

        $this->assertDatabaseHas('purchases', [
            'id' => $purchase->id,
            'status' => 'partially_received',
        ]);
    }

    public function test_stock_manager_can_record_purchase_return_and_reduce_stock(): void
    {
        $user = $this->actor('stock_manager');
        $variant = $this->variant('Zip');

        $this->actingAs($user)->post(route('purchases.store'), [
            'purchased_on' => now()->toDateString(),
            'transport_charge' => 0,
            'allocation_method' => 'value',
            'items' => [
                ['raw_material_variant_id' => $variant->id, 'quantity' => 10, 'unit_price' => 50],
            ],
        ])->assertRedirect(route('purchases.index'));

        $purchase = Purchase::with('items')->first();

        $this->actingAs($user)->post(route('purchase-returns.store', $purchase), [
            'returned_on' => now()->toDateString(),
            'items' => [
                ['purchase_item_id' => $purchase->items->first()->id, 'quantity' => 3],
            ],
        ])->assertRedirect(route('purchase-returns.index'));

        $this->assertEquals(7, (float) StockBalance::where('raw_material_variant_id', $variant->id)->value('quantity'));
        $this->assertDatabaseHas('purchase_return_items', [
            'purchase_item_id' => $purchase->items->first()->id,
            'quantity' => 3,
            'line_total' => 150,
        ]);
    }

    public function test_purchase_returns_reduce_due_and_payment_limit(): void
    {
        $user = $this->actor('stock_manager');
        $variant = $this->variant('Buttons');

        $this->actingAs($user)->post(route('purchases.store'), [
            'purchased_on' => now()->toDateString(),
            'transport_charge' => 0,
            'allocation_method' => 'value',
            'items' => [
                ['raw_material_variant_id' => $variant->id, 'quantity' => 10, 'unit_price' => 50],
            ],
        ])->assertRedirect(route('purchases.index'));

        $purchase = Purchase::with('items')->first();

        $this->actingAs($user)->post(route('purchase-returns.store', $purchase), [
            'returned_on' => now()->toDateString(),
            'items' => [
                ['purchase_item_id' => $purchase->items->first()->id, 'quantity' => 3],
            ],
        ])->assertRedirect(route('purchase-returns.index'));

        $this->actingAs($user)->get(route('purchases.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Purchases/Index')
                ->where('purchases.0.returned_total', 150)
                ->where('purchases.0.net_total', 350)
                ->where('purchases.0.due_amount', 350)
            );

        $this->actingAs($user)->post(route('purchases.payments.store', $purchase), [
            'paid_on' => now()->toDateString(),
            'amount' => 351,
        ])->assertSessionHasErrors('amount');
    }

    public function test_placed_purchase_does_not_receive_stock_until_status_is_received(): void
    {
        $user = $this->actor('stock_manager');
        $variant = $this->variant('Placed Cloth', 'roll');

        $this->actingAs($user)->post(route('purchases.store'), [
            'purchased_on' => now()->toDateString(),
            'status' => 'placed',
            'transport_charge' => 0,
            'allocation_method' => 'value',
            'items' => [
                ['raw_material_variant_id' => $variant->id, 'quantity' => 5, 'unit_price' => 100],
            ],
        ])->assertRedirect(route('purchases.index'));

        $purchase = Purchase::with('items')->first();
        $this->assertEquals(0, (float) StockBalance::where('raw_material_variant_id', $variant->id)->value('quantity'));
        $this->assertEquals(0, (float) $purchase->items->first()->received_quantity);

        $this->actingAs($user)->patch(route('purchases.status.update', $purchase), [
            'status' => 'received',
        ])->assertRedirect();

        $this->assertEquals(5, (float) StockBalance::where('raw_material_variant_id', $variant->id)->value('quantity'));
        $this->assertEquals(5, (float) $purchase->items()->first()->received_quantity);
    }

    public function test_production_manager_cannot_access_purchases(): void
    {
        $user = $this->actor('production_manager');
        $this->actingAs($user)->get(route('purchases.index'))->assertForbidden();
    }
}
