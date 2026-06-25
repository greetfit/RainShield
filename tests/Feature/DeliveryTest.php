<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\FinishedGood;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\FinishedGoodsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DeliveryTest extends TestCase
{
    use RefreshDatabase;

    private function pm(): User
    {
        Role::firstOrCreate(['name' => 'production_manager']);
        $u = User::factory()->create();
        $u->assignRole('production_manager');

        return $u;
    }

    private function variant(): ProductVariant
    {
        $p = Product::create(['name' => 'Raincoat', 'is_active' => true]);

        return $p->variants()->create(['name' => 'M', 'is_active' => true]);
    }

    public function test_dispatch_reduces_finished_goods_and_tracks_lead_time(): void
    {
        $user = $this->pm();
        $variant = $this->variant();
        app(FinishedGoodsService::class)->add($variant->id, 50);

        // Dispatch 20.
        $this->actingAs($user)->post(route('deliveries.store'), [
            'customer_name' => 'Shop A',
            'product_variant_id' => $variant->id,
            'quantity' => 20,
            'dispatched_on' => '2026-06-01',
        ])->assertRedirect(route('deliveries.index'));

        $this->assertEquals(30, (int) FinishedGood::where('product_variant_id', $variant->id)->value('quantity'));
        $this->assertDatabaseHas('finished_good_movements', [
            'product_variant_id' => $variant->id,
            'direction' => 'out',
            'quantity' => 20,
            'balance_quantity' => 30,
        ]);

        $delivery = Delivery::first();
        $this->assertEquals('dispatched', $delivery->status);

        // Mark delivered after 6 days.
        $this->actingAs($user)->post(route('deliveries.delivered', $delivery), [
            'delivered_on' => '2026-06-07',
        ])->assertRedirect();
        $delivery->refresh();
        $this->assertEquals('delivered', $delivery->status);
        $this->assertEquals(6, $delivery->lead_time);
    }

    public function test_cannot_dispatch_more_than_in_stock(): void
    {
        $user = $this->pm();
        $variant = $this->variant();
        app(FinishedGoodsService::class)->add($variant->id, 5);

        $this->actingAs($user)->post(route('deliveries.store'), [
            'customer_name' => 'Shop B',
            'product_variant_id' => $variant->id,
            'quantity' => 10,
            'dispatched_on' => '2026-06-01',
        ])->assertSessionHasErrors('quantity');

        // Stock untouched, no delivery created.
        $this->assertEquals(5, (int) FinishedGood::where('product_variant_id', $variant->id)->value('quantity'));
        $this->assertEquals(0, Delivery::count());
    }

    public function test_production_manager_can_adjust_finished_goods_to_physical_count(): void
    {
        $user = $this->pm();
        $variant = $this->variant();
        app(FinishedGoodsService::class)->add($variant->id, 12);

        $this->actingAs($user)->post(route('finished-goods.adjust'), [
            'product_variant_id' => $variant->id,
            'counted_quantity' => 9,
            'note' => 'Counted after recount',
        ])->assertRedirect();

        $this->assertEquals(9, (int) FinishedGood::where('product_variant_id', $variant->id)->value('quantity'));
        $this->assertDatabaseHas('finished_good_movements', [
            'product_variant_id' => $variant->id,
            'direction' => 'adjustment',
            'quantity' => 3,
            'balance_quantity' => 9,
            'note' => 'Counted after recount',
        ]);

        $this->actingAs($user)->get(route('finished-goods.movements'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('FinishedGoods/Movements')->has('movements'));
    }
}
