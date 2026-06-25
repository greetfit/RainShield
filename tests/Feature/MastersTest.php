<?php

namespace Tests\Feature;

use App\Models\Part;
use App\Models\PartConversionRule;
use App\Models\Designation;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductGrade;
use App\Models\ProductLayer;
use App\Models\ProductSize;
use App\Models\RawMaterial;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MastersTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        foreach (['admin', 'viewer'] as $r) {
            Role::firstOrCreate(['name' => $r]);
        }
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    public function test_admin_can_view_all_master_index_pages(): void
    {
        $admin = $this->admin();

        foreach ([
            'masters.raw-materials.index',
            'masters.product-categories.index',
            'masters.products.index',
            'masters.product-sizes.index',
            'masters.product-layers.index',
            'masters.product-grades.index',
            'masters.parts.index',
            'masters.designations.index',
            'masters.staff.index',
            'masters.suppliers.index',
            'masters.customers.index',
        ] as $name) {
            $this->actingAs($admin)->get(route($name))->assertOk();
        }
    }

    public function test_admin_can_manage_part_conversion_rules(): void
    {
        $admin = $this->admin();
        $product = Product::create(['name' => 'Rain Coat', 'is_active' => true]);
        $large = $product->variants()->create(['name' => 'Large', 'is_active' => true]);
        $small = $product->variants()->create(['name' => 'Small', 'is_active' => true]);
        $arm = Part::create(['name' => 'Arm', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('business-settings.part-conversion-rules.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->post(route('business-settings.part-conversion-rules.store'), [
                'from_product_variant_id' => $large->id,
                'from_part_id' => $arm->id,
                'to_product_variant_id' => $small->id,
                'to_part_id' => $arm->id,
                'output_per_input' => 2,
                'is_active' => true,
            ])
            ->assertRedirect();

        $rule = PartConversionRule::first();
        $this->assertNotNull($rule);
        $this->assertEquals(2.000, (float) $rule->output_per_input);

        $this->actingAs($admin)
            ->post(route('business-settings.part-conversion-rules.store'), [
                'from_product_variant_id' => $large->id,
                'from_part_id' => $arm->id,
                'to_product_variant_id' => $small->id,
                'to_part_id' => $arm->id,
                'output_per_input' => 2,
                'is_active' => true,
            ])
            ->assertSessionHasErrors('to_part_id');

        $this->actingAs($admin)
            ->put(route('business-settings.part-conversion-rules.update', $rule), [
                'from_product_variant_id' => $large->id,
                'from_part_id' => $arm->id,
                'to_product_variant_id' => $small->id,
                'to_part_id' => $arm->id,
                'output_per_input' => 1.5,
                'is_active' => false,
            ])
            ->assertRedirect();

        $rule->refresh();
        $this->assertEquals(1.500, (float) $rule->output_per_input);
        $this->assertFalse($rule->is_active);
    }

    public function test_admin_can_update_general_business_settings(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('business-settings.general.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('BusinessSettings/General/Index')
                ->where('settings.currency_code', 'LKR')
            );

        $this->actingAs($admin)
            ->put(route('business-settings.general.update'), [
                'currency_code' => 'USD',
                'currency_symbol' => '$',
                'timezone' => 'UTC',
                'date_format' => 'Y-m-d',
                'time_format' => 'H:i',
            ])
            ->assertRedirect();

        $this->assertSame('USD', SystemSetting::where('key', 'currency_code')->value('value'));
        $this->assertSame('UTC', SystemSetting::where('key', 'timezone')->value('value'));
    }

    public function test_admin_can_create_raw_material_with_variant(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('masters.raw-materials.store'), [
                'name' => 'Raincoat Cloth',
                'unit' => 'meter',
                'alert_quantity' => 25,
                'is_active' => true,
            ])
            ->assertRedirect();

        $material = RawMaterial::firstWhere('name', 'Raincoat Cloth');
        $this->assertNotNull($material);
        $this->assertEquals(25.000, (float) $material->alert_quantity);

        $this->actingAs($admin)
            ->post(route('masters.raw-material-variants.store', $material), [
                'name' => 'Double Layer',
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('raw_material_variants', [
            'raw_material_id' => $material->id,
            'name' => 'Double Layer',
        ]);

        // Uniqueness within the same material is enforced.
        $this->actingAs($admin)
            ->post(route('masters.raw-material-variants.store', $material), [
                'name' => 'Double Layer',
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_admin_can_create_product_with_variant_and_part(): void
    {
        $admin = $this->admin();
        $category = ProductCategory::create(['name' => 'Rainwear', 'is_active' => true]);
        $size = ProductSize::create(['name' => 'Medium', 'is_active' => true]);
        $layer = ProductLayer::create(['name' => 'Double', 'is_active' => true]);
        $grade = ProductGrade::create(['name' => 'A', 'is_active' => true]);

        $this->actingAs($admin)
            ->post(route('masters.products.store'), [
                'name' => 'Raincoat',
                'product_category_id' => $category->id,
                'is_active' => true,
            ])
            ->assertRedirect();
        $product = Product::firstWhere('name', 'Raincoat');
        $this->assertEquals($category->id, $product->product_category_id);
        $this->assertEquals('Rainwear', $product->category);

        $this->actingAs($admin)
            ->post(route('masters.product-variants.store', $product), [
                'name' => 'Medium / Double / A',
                'product_size_id' => $size->id,
                'product_layer_id' => $layer->id,
                'product_grade_id' => $grade->id,
                'profit_margin_percent' => 18.5,
                'is_active' => true,
            ])
            ->assertRedirect();
        $this->assertDatabaseHas('product_variants', [
            'product_id' => $product->id,
            'product_size_id' => $size->id,
            'product_layer_id' => $layer->id,
            'product_grade_id' => $grade->id,
            'size' => 'Medium',
            'layer' => 'Double',
            'grade' => 'A',
            'profit_margin_percent' => 18.5,
        ]);

        $this->actingAs($admin)
            ->post(route('masters.parts.store'), ['name' => 'Body', 'is_active' => true])
            ->assertRedirect();
        $this->assertDatabaseHas('parts', ['name' => 'Body']);
    }

    public function test_admin_can_set_product_variant_opening_stock(): void
    {
        $admin = $this->admin();
        $product = Product::create(['name' => 'Raincoat', 'is_active' => true]);
        $variant = $product->variants()->create(['name' => 'Medium', 'is_active' => true]);

        $this->actingAs($admin)
            ->post(route('masters.product-variants.opening-stock', $variant), [
                'quantity' => 25,
                'unit_cost' => 1250.50,
                'alert_quantity' => 5,
                'note' => 'Initial finished goods count',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('finished_goods', [
            'product_variant_id' => $variant->id,
            'quantity' => 25,
            'average_cost' => 1250.50,
            'alert_quantity' => 5,
        ]);

        $this->assertDatabaseHas('finished_good_movements', [
            'product_variant_id' => $variant->id,
            'direction' => 'opening',
            'quantity' => 25,
            'unit_cost' => 1250.50,
            'balance_quantity' => 25,
            'note' => 'Initial finished goods count',
        ]);

        $this->actingAs($admin)
            ->get(route('masters.products.variants', $product))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Masters/Products/Variants')
                ->where('variants.0.stock_quantity', 25)
                ->where('variants.0.stock_average_cost', 1250.50)
                ->where('variants.0.stock_alert_quantity', 5)
            );
    }

    public function test_admin_can_create_supplier_and_customer_masters(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('masters.suppliers.store'), [
                'name' => 'Cloth Supplier A',
                'phone' => '077 123 4567',
                'email' => 'supplier@example.com',
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('suppliers', [
            'name' => 'Cloth Supplier A',
            'phone' => '0771234567',
        ]);

        $this->actingAs($admin)
            ->post(route('masters.customers.store'), [
                'name' => 'Shop A',
                'phone' => '077 765 4321',
                'email' => 'shop@example.com',
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('customers', [
            'name' => 'Shop A',
            'phone' => '0777654321',
        ]);

        $this->actingAs($admin)
            ->post(route('masters.customers.store'), [
                'name' => 'Short Phone Shop',
                'phone' => '077123456',
                'is_active' => true,
            ])
            ->assertSessionHasErrors('phone');
    }

    public function test_admin_can_create_product_category_master(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('masters.product-categories.store'), [
                'name' => 'Winterwear',
                'description' => 'Winter clothing products',
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('product_categories', ['name' => 'Winterwear']);
    }

    public function test_admin_can_create_product_variant_attribute_masters(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('masters.product-sizes.store'), [
                'name' => 'Small',
                'description' => 'Small size',
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('masters.product-layers.store'), [
                'name' => 'Single Layer',
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('masters.product-grades.store'), [
                'name' => 'B',
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('product_sizes', ['name' => 'Small']);
        $this->assertDatabaseHas('product_layers', ['name' => 'Single Layer']);
        $this->assertDatabaseHas('product_grades', ['name' => 'B']);
    }

    public function test_admin_can_create_designation_master_and_assign_staff(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('masters.designations.store'), [
                'name' => 'Cutter',
                'description' => 'Cuts raw material pieces',
                'priority_level' => 1,
                'is_active' => true,
            ])
            ->assertRedirect();

        $designation = Designation::firstWhere('name', 'Cutter');
        $this->assertNotNull($designation);
        $this->assertEquals(1, $designation->priority_level);

        $this->actingAs($admin)
            ->post(route('masters.staff.store'), [
                'name' => 'Cutter A',
                'phone' => '0771112222',
                'designation_id' => $designation->id,
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('staff', [
            'name' => 'Cutter A',
            'designation_id' => $designation->id,
            'designation' => 'Cutter',
        ]);
    }

    public function test_viewer_is_blocked_from_masters(): void
    {
        Role::firstOrCreate(['name' => 'viewer']);
        $viewer = User::factory()->create();
        $viewer->assignRole('viewer');

        $this->actingAs($viewer)->get(route('masters.raw-materials.index'))->assertForbidden();
    }
}
