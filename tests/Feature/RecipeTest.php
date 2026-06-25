<?php

namespace Tests\Feature;

use App\Models\Part;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RawMaterial;
use App\Models\RawMaterialVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RecipeTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string $role): User
    {
        Role::firstOrCreate(['name' => $role]);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function variant(): ProductVariant
    {
        $product = Product::create(['name' => 'Raincoat', 'is_active' => true]);

        return $product->variants()->create([
            'name' => 'Medium / Double / A',
            'size' => 'Medium',
            'layer' => 'double',
            'grade' => 'A',
            'is_active' => true,
        ]);
    }

    public function test_production_manager_can_build_a_recipe(): void
    {
        $user = $this->actor('production_manager');
        $variant = $this->variant();

        $cloth = RawMaterial::create(['name' => 'Cloth', 'unit' => 'meter', 'is_active' => true]);
        $clothVariant = $cloth->variants()->create(['name' => 'Double Layer', 'is_active' => true]);
        $body = Part::create(['name' => 'Body', 'is_active' => true]);

        // Recipe page loads
        $this->actingAs($user)->get(route('recipes.edit', $variant))->assertOk();

        // Add a material line — unit is snapshotted from the material
        $this->actingAs($user)
            ->post(route('recipes.materials.store', $variant), [
                'raw_material_variant_id' => $clothVariant->id,
                'quantity' => 2.5,
            ])
            ->assertRedirect();
        $this->assertDatabaseHas('recipe_materials', [
            'product_variant_id' => $variant->id,
            'raw_material_variant_id' => $clothVariant->id,
            'quantity' => 2.5,
            'unit' => 'meter',
        ]);

        // No duplicate material on the same variant
        $this->actingAs($user)
            ->post(route('recipes.materials.store', $variant), [
                'raw_material_variant_id' => $clothVariant->id,
                'quantity' => 1,
            ])
            ->assertSessionHasErrors('raw_material_variant_id');

        // Add a part line (double layer ⇒ body x2)
        $this->actingAs($user)
            ->post(route('recipes.parts.store', $variant), [
                'part_id' => $body->id,
                'quantity_per_garment' => 2,
            ])
            ->assertRedirect();
        $this->assertDatabaseHas('recipe_parts', [
            'product_variant_id' => $variant->id,
            'part_id' => $body->id,
            'quantity_per_garment' => 2,
        ]);
    }

    public function test_stock_manager_cannot_edit_recipes(): void
    {
        $user = $this->actor('stock_manager');
        $variant = $this->variant();

        $this->actingAs($user)->get(route('recipes.edit', $variant))->assertForbidden();
    }
}
