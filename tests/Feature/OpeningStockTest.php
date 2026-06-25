<?php

namespace Tests\Feature;

use App\Models\FinishedGood;
use App\Models\Part;
use App\Models\PartStockBalance;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RecoverablePartBalance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OpeningStockTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role]);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function variant(): ProductVariant
    {
        $product = Product::create(['name' => 'Rain Coat', 'is_active' => true]);

        return $product->variants()->create(['name' => 'Medium', 'is_active' => true]);
    }

    public function test_production_manager_can_set_finished_goods_opening_stock(): void
    {
        $user = $this->userWithRole('production_manager');
        $variant = $this->variant();

        $this->actingAs($user)->post(route('finished-goods.opening'), [
            'product_variant_id' => $variant->id,
            'quantity' => 25,
            'unit_cost' => 120.50,
            'alert_quantity' => 5,
            'note' => 'Initial count',
        ])->assertRedirect();

        $finishedGood = FinishedGood::where('product_variant_id', $variant->id)->firstOrFail();
        $this->assertEquals(25, $finishedGood->quantity);
        $this->assertEquals(120.50, (float) $finishedGood->average_cost);
        $this->assertEquals(5, $finishedGood->alert_quantity);
        $this->assertDatabaseHas('finished_good_movements', [
            'product_variant_id' => $variant->id,
            'direction' => 'opening',
            'quantity' => 25,
            'balance_quantity' => 25,
            'note' => 'Initial count',
        ]);
    }

    public function test_stock_manager_can_set_part_opening_stock(): void
    {
        $user = $this->userWithRole('stock_manager');
        $variant = $this->variant();
        $part = Part::create(['name' => 'Arms', 'is_active' => true]);

        $this->actingAs($user)->post(route('part-stock.opening'), [
            'product_variant_id' => $variant->id,
            'part_id' => $part->id,
            'stock_type' => 'good',
            'quantity' => 40,
            'unit_cost' => 8.25,
            'alert_quantity' => 10,
            'note' => 'Opening cut parts',
        ])->assertRedirect();

        $balance = PartStockBalance::where('product_variant_id', $variant->id)
            ->where('part_id', $part->id)
            ->firstOrFail();
        $this->assertEquals(40, $balance->quantity);
        $this->assertEquals(8.25, (float) $balance->average_cost);
        $this->assertEquals(10, $balance->alert_quantity);
        $this->assertDatabaseHas('part_stock_movements', [
            'product_variant_id' => $variant->id,
            'part_id' => $part->id,
            'stock_type' => 'good',
            'direction' => 'opening',
            'quantity' => 40,
            'balance_quantity' => 40,
            'note' => 'Opening cut parts',
        ]);
    }

    public function test_recoverable_part_opening_stock_is_supported(): void
    {
        $user = $this->userWithRole('stock_manager');
        $variant = $this->variant();
        $part = Part::create(['name' => 'Body', 'is_active' => true]);

        $this->actingAs($user)->post(route('part-stock.opening'), [
            'product_variant_id' => $variant->id,
            'part_id' => $part->id,
            'stock_type' => 'recoverable',
            'quantity' => 7,
            'unit_cost' => 3,
        ])->assertRedirect();

        $this->assertEquals(7, RecoverablePartBalance::where('product_variant_id', $variant->id)
            ->where('part_id', $part->id)
            ->value('quantity'));
    }
}
