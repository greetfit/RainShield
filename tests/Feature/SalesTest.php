<?php

namespace Tests\Feature;

use App\Models\FinishedGood;
use App\Models\Part;
use App\Models\PieceRate;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\User;
use App\Services\FinishedGoodsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SalesTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        Role::firstOrCreate(['name' => 'production_manager']);
        $user = User::factory()->create();
        $user->assignRole('production_manager');

        return $user;
    }

    private function variant(): ProductVariant
    {
        $product = Product::create(['name' => 'Rain Coat', 'is_active' => true]);

        return $product->variants()->create([
            'name' => 'Medium',
            'selling_price' => 2500,
            'is_active' => true,
        ]);
    }

    public function test_sale_invoice_reduces_finished_goods_and_void_restores_stock(): void
    {
        $user = $this->manager();
        $variant = $this->variant();
        app(FinishedGoodsService::class)->add($variant->id, 10);

        $this->actingAs($user)->post(route('sales.store'), [
            'sold_at' => '2026-06-25 10:00:00',
            'payment_method' => 'cash',
            'paid' => 2500,
            'items' => [
                ['product_variant_id' => $variant->id, 'quantity' => 2, 'unit_price' => 2500],
            ],
        ])->assertRedirect();

        $sale = Sale::first();
        $this->assertNotNull($sale);
        $this->assertEquals(5000.00, (float) $sale->total);
        $this->assertEquals('partial', $sale->payment_status);
        $this->assertEquals(8, (int) FinishedGood::where('product_variant_id', $variant->id)->value('quantity'));

        $this->actingAs($user)->post(route('sales.void', $sale))->assertRedirect();

        $this->assertEquals(10, (int) FinishedGood::where('product_variant_id', $variant->id)->value('quantity'));
        $this->assertEquals('void', $sale->refresh()->status);
    }

    public function test_sale_cannot_exceed_finished_goods_stock(): void
    {
        $user = $this->manager();
        $variant = $this->variant();
        app(FinishedGoodsService::class)->add($variant->id, 1);

        $this->actingAs($user)->post(route('sales.store'), [
            'sold_at' => '2026-06-25 10:00:00',
            'payment_method' => 'cash',
            'paid' => 0,
            'items' => [
                ['product_variant_id' => $variant->id, 'quantity' => 2, 'unit_price' => 2500],
            ],
        ])->assertSessionHasErrors('items');

        $this->assertEquals(1, (int) FinishedGood::where('product_variant_id', $variant->id)->value('quantity'));
        $this->assertEquals(0, Sale::count());
    }

    public function test_sales_print_pages_render_and_create_can_redirect_to_print_mode(): void
    {
        $user = $this->manager();
        $variant = $this->variant();
        app(FinishedGoodsService::class)->add($variant->id, 10);

        $this->actingAs($user)->post(route('sales.store'), [
            'sold_at' => '2026-06-25 10:00:00',
            'payment_method' => 'cash',
            'paid' => 2500,
            'print_mode' => 'invoice',
            'items' => [
                ['product_variant_id' => $variant->id, 'quantity' => 1, 'unit_price' => 2500],
            ],
        ])->assertRedirect(route('sales.print', Sale::first()));

        $sale = Sale::first();

        $this->actingAs($user)->get(route('sales.print', $sale))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Sales/PrintInvoice')
                ->where('sale.invoice_no', $sale->invoice_no)
            );

        $this->actingAs($user)->get(route('sales.receipt', $sale))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Sales/ThermalReceipt')
                ->where('sale.invoice_no', $sale->invoice_no)
            );
    }

    public function test_pos_sale_can_redirect_to_thermal_receipt(): void
    {
        $user = $this->manager();
        $variant = $this->variant();
        app(FinishedGoodsService::class)->add($variant->id, 10);

        $this->actingAs($user)->post(route('sales.pos.open'), [
            'opening_amount' => 1000,
        ])->assertRedirect();

        $this->actingAs($user)->post(route('sales.store'), [
            'sold_at' => '2026-06-25 10:00:00',
            'payment_method' => 'cash',
            'paid' => 2500,
            'print_mode' => 'receipt',
            'items' => [
                ['product_variant_id' => $variant->id, 'quantity' => 1, 'unit_price' => 2500],
            ],
        ])->assertRedirect(route('sales.receipt', Sale::first()));
    }

    public function test_sales_product_price_uses_costing_plus_profit_markup(): void
    {
        $user = $this->manager();
        $body = Part::create(['name' => 'Body', 'is_active' => true]);
        $product = Product::create(['name' => 'Rain Coat', 'is_active' => true]);
        $variant = $product->variants()->create([
            'name' => 'Medium',
            'profit_margin_percent' => 25,
            'is_active' => true,
        ]);
        $variant->recipeParts()->create(['part_id' => $body->id, 'quantity_per_garment' => 2]);
        app(\App\Services\PartStockService::class)->receiveGood($variant->id, $body->id, 10, 50);
        app(FinishedGoodsService::class)->add($variant->id, 5);
        PieceRate::create(['stage' => 'stitching', 'product_variant_id' => null, 'rate' => 20]);

        $this->actingAs($user)->get(route('sales.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Sales/Index')
                ->where('products.0.cost', 120)
                ->where('products.0.profit_margin_percent', 25)
                ->where('products.0.price', 150)
            );
    }

    public function test_sales_return_restores_finished_goods_and_reduces_due(): void
    {
        $user = $this->manager();
        $variant = $this->variant();
        app(FinishedGoodsService::class)->add($variant->id, 10, unitCost: 1000);

        $this->actingAs($user)->post(route('sales.store'), [
            'sold_at' => '2026-06-25 10:00:00',
            'payment_method' => 'cash',
            'paid' => 2500,
            'items' => [
                ['product_variant_id' => $variant->id, 'quantity' => 2, 'unit_price' => 2500],
            ],
        ])->assertRedirect();

        $sale = Sale::with('items')->first();

        $this->actingAs($user)->post(route('sale-returns.store', $sale), [
            'returned_on' => '2026-06-25',
            'items' => [
                ['sale_item_id' => $sale->items->first()->id, 'quantity' => 1],
            ],
        ])->assertRedirect(route('sales.index'));

        $this->assertEquals(9, (int) FinishedGood::where('product_variant_id', $variant->id)->value('quantity'));
        $this->assertEquals(0, (float) $sale->refresh()->due);
        $this->assertDatabaseHas('sale_returns', [
            'sale_id' => $sale->id,
            'total_amount' => 2500,
        ]);
    }
}
