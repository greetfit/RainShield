<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CuttingYieldRule;
use App\Models\Designation;
use App\Models\FinishedGood;
use App\Models\FinishedGoodMovement;
use App\Models\Part;
use App\Models\PartConversionRule;
use App\Models\PartStockBalance;
use App\Models\PartStockMovement;
use App\Models\PaymentMethod;
use App\Models\PieceRate;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductGrade;
use App\Models\ProductLayer;
use App\Models\ProductSize;
use App\Models\ProductVariant;
use App\Models\ProductionStage;
use App\Models\RawMaterial;
use App\Models\RawMaterialVariant;
use App\Models\RecipeMaterial;
use App\Models\RecipePart;
use App\Models\Staff;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SystemSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoSystemSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            UnitOfMeasureSeeder::class,
            PaymentMethodSeeder::class,
            ExpenseCategorySeeder::class,
        ]);

        DB::transaction(function (): void {
            $this->settings();

            $category = ProductCategory::updateOrCreate(
                ['name' => 'Rain Wear'],
                ['description' => 'Raincoats and rainwear products', 'is_active' => true],
            );

            $small = ProductSize::updateOrCreate(['name' => 'Small'], ['description' => 'Small size', 'is_active' => true]);
            $medium = ProductSize::updateOrCreate(['name' => 'Medium'], ['description' => 'Medium size', 'is_active' => true]);
            $large = ProductSize::updateOrCreate(['name' => 'Large'], ['description' => 'Large size', 'is_active' => true]);
            $single = ProductLayer::updateOrCreate(['name' => 'Single'], ['description' => 'Single layer', 'is_active' => true]);
            $double = ProductLayer::updateOrCreate(['name' => 'Double'], ['description' => 'Double layer', 'is_active' => true]);
            $gradeA = ProductGrade::updateOrCreate(['name' => 'A Grade'], ['description' => 'Premium grade', 'is_active' => true]);

            $materials = $this->materials();
            $parts = $this->parts();
            $products = $this->products($category, $small, $medium, $large, $single, $double, $gradeA);

            $this->recipes($products, $materials, $parts);
            $this->productionStages();
            $staff = $this->staff();
            $this->pieceRates($products, $staff);
            $this->cuttingYieldRules($products, $materials, $parts);
            $this->partConversionRules($products, $parts);
            $this->openingStock($products, $materials, $parts);
            $this->partners();
        });
    }

    private function settings(): void
    {
        foreach ([
            'company_name' => 'RainShield',
            'company_phone' => '0771234567',
            'company_email' => 'admin@rainshield.test',
            'currency_code' => 'LKR',
            'currency_symbol' => 'Rs',
            'timezone' => 'Asia/Colombo',
            'date_format' => 'd/m/Y',
            'time_format' => 'h:i A',
        ] as $key => $value) {
            SystemSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }

    private function materials(): array
    {
        $definitions = [
            'tapata' => ['Tapata Cloth', 'roll', 2, ['Normal Tapata', 'A Grade']],
            'zip' => ['Zip', 'piece', 50, ['Single Line', 'Double Line']],
            'thread' => ['Thread', 'kg', 5, ['Black Color', 'Red Color']],
            'elastic' => ['Elastic', 'meter', 20, ['Standard Elastic']],
            'buttons' => ['Buttons', 'piece', 100, ['A Grade Button']],
            'packing' => ['Packing', 'piece', 100, ['Polythene Bag']],
        ];

        $materials = [];

        foreach ($definitions as $key => [$name, $unit, $alert, $variants]) {
            $material = RawMaterial::updateOrCreate(
                ['name' => $name],
                ['unit' => $unit, 'alert_quantity' => $alert, 'description' => "Demo {$name}", 'is_active' => true],
            );

            foreach ($variants as $variantName) {
                $materials[$key][$variantName] = RawMaterialVariant::updateOrCreate(
                    ['raw_material_id' => $material->id, 'name' => $variantName],
                    ['code' => strtoupper(substr($key, 0, 3)).'-'.strtoupper(substr(str_replace(' ', '', $variantName), 0, 4)), 'is_active' => true],
                );
            }
        }

        return $materials;
    }

    private function parts(): array
    {
        $parts = [];

        foreach (['Head', 'Arms', 'Body'] as $name) {
            $parts[$name] = Part::updateOrCreate(
                ['name' => $name],
                ['description' => "Demo {$name} cut part", 'is_active' => true],
            );
        }

        return $parts;
    }

    private function products(ProductCategory $category, ProductSize $small, ProductSize $medium, ProductSize $large, ProductLayer $single, ProductLayer $double, ProductGrade $gradeA): array
    {
        $rainCoat = Product::updateOrCreate(
            ['name' => 'Rain Cort'],
            [
                'product_category_id' => $category->id,
                'source_type' => Product::SOURCE_BOTH,
                'category' => $category->name,
                'description' => 'Demo raincoat product with own production and resale support',
                'is_active' => true,
            ],
        );

        $tShirt = Product::updateOrCreate(
            ['name' => 'T Shirt'],
            [
                'product_category_id' => $category->id,
                'source_type' => Product::SOURCE_OUTSOURCED,
                'category' => $category->name,
                'description' => 'Demo outsourced resale product',
                'is_active' => true,
            ],
        );

        return [
            'raincoat_small' => ProductVariant::updateOrCreate(
                ['product_id' => $rainCoat->id, 'name' => 'Small Rain Cord'],
                [
                    'product_size_id' => $small->id,
                    'size' => $small->name,
                    'product_layer_id' => $single->id,
                    'layer' => $single->name,
                    'product_grade_id' => $gradeA->id,
                    'grade' => $gradeA->name,
                    'sku' => 'RS-RC-S-A',
                    'selling_price' => 1850,
                    'profit_markup_type' => 'percent',
                    'profit_margin_percent' => 25,
                    'profit_markup_amount' => 0,
                    'is_active' => true,
                ],
            ),
            'raincoat_medium' => ProductVariant::updateOrCreate(
                ['product_id' => $rainCoat->id, 'name' => 'Medium'],
                [
                    'product_size_id' => $medium->id,
                    'size' => $medium->name,
                    'product_layer_id' => $single->id,
                    'layer' => $single->name,
                    'product_grade_id' => $gradeA->id,
                    'grade' => $gradeA->name,
                    'sku' => 'RS-RC-M-A',
                    'selling_price' => 2250,
                    'profit_markup_type' => 'percent',
                    'profit_margin_percent' => 25,
                    'profit_markup_amount' => 0,
                    'is_active' => true,
                ],
            ),
            'raincoat_large' => ProductVariant::updateOrCreate(
                ['product_id' => $rainCoat->id, 'name' => 'Large Double'],
                [
                    'product_size_id' => $large->id,
                    'size' => $large->name,
                    'product_layer_id' => $double->id,
                    'layer' => $double->name,
                    'product_grade_id' => $gradeA->id,
                    'grade' => $gradeA->name,
                    'sku' => 'RS-RC-LD-A',
                    'selling_price' => 2850,
                    'profit_markup_type' => 'flat',
                    'profit_margin_percent' => 0,
                    'profit_markup_amount' => 600,
                    'is_active' => true,
                ],
            ),
            'tshirt_medium' => ProductVariant::updateOrCreate(
                ['product_id' => $tShirt->id, 'name' => 'Medium'],
                [
                    'product_size_id' => $medium->id,
                    'size' => $medium->name,
                    'product_layer_id' => null,
                    'layer' => null,
                    'product_grade_id' => $gradeA->id,
                    'grade' => $gradeA->name,
                    'sku' => 'RS-TS-M-A',
                    'selling_price' => 1200,
                    'profit_markup_type' => 'percent',
                    'profit_margin_percent' => 20,
                    'profit_markup_amount' => 0,
                    'is_active' => true,
                ],
            ),
        ];
    }

    private function recipes(array $products, array $materials, array $parts): void
    {
        foreach ([$products['raincoat_small'], $products['raincoat_medium'], $products['raincoat_large']] as $variant) {
            $multiplier = $variant->name === 'Large Double' ? 1.35 : ($variant->name === 'Small Rain Cord' ? 0.8 : 1);

            foreach ([
                [$materials['tapata']['Normal Tapata']->id, round(0.10 * $multiplier, 3), 'roll'],
                [$materials['zip']['Single Line']->id, 1, 'piece'],
                [$materials['thread']['Black Color']->id, round(0.05 * $multiplier, 3), 'kg'],
                [$materials['elastic']['Standard Elastic']->id, round(1.5 * $multiplier, 3), 'meter'],
                [$materials['buttons']['A Grade Button']->id, 4, 'piece'],
                [$materials['packing']['Polythene Bag']->id, 1, 'piece'],
            ] as [$rawMaterialVariantId, $quantity, $unit]) {
                RecipeMaterial::updateOrCreate(
                    ['product_variant_id' => $variant->id, 'raw_material_variant_id' => $rawMaterialVariantId],
                    ['quantity' => $quantity, 'unit' => $unit],
                );
            }

            foreach ([
                'Head' => 1,
                'Arms' => 2,
                'Body' => 1,
            ] as $partName => $quantity) {
                RecipePart::updateOrCreate(
                    ['product_variant_id' => $variant->id, 'part_id' => $parts[$partName]->id],
                    ['quantity_per_garment' => $quantity],
                );
            }
        }
    }

    private function productionStages(): void
    {
        foreach ([
            ['Cutting', 'cutting', 1, 'Raw material to cut parts'],
            ['Stitching', 'stitching', 2, 'Cut parts to stitched garments'],
            ['Packing', 'packing', 3, 'Final product output'],
        ] as [$name, $slug, $priority, $description]) {
            ProductionStage::updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'priority_level' => $priority, 'description' => $description, 'is_active' => true],
            );
        }
    }

    private function staff(): array
    {
        $designations = [
            'cutter' => Designation::updateOrCreate(['name' => 'Cutter'], ['description' => 'Cutting staff', 'priority_level' => 1, 'is_active' => true]),
            'stitcher' => Designation::updateOrCreate(['name' => 'Stitcher'], ['description' => 'Stitching staff', 'priority_level' => 2, 'is_active' => true]),
            'packer' => Designation::updateOrCreate(['name' => 'Packer'], ['description' => 'Packing staff', 'priority_level' => 3, 'is_active' => true]),
        ];

        return [
            'cutter' => Staff::updateOrCreate(
                ['name' => 'Ruwan'],
                ['phone' => '0771112222', 'designation_id' => $designations['cutter']->id, 'designation' => $designations['cutter']->name, 'salary_type' => 'piece_rate', 'monthly_salary' => 0, 'is_active' => true],
            ),
            'stitcher' => Staff::updateOrCreate(
                ['name' => 'Malini'],
                ['phone' => '0773334444', 'designation_id' => $designations['stitcher']->id, 'designation' => $designations['stitcher']->name, 'salary_type' => 'piece_rate', 'monthly_salary' => 0, 'is_active' => true],
            ),
            'packer' => Staff::updateOrCreate(
                ['name' => 'Kamal'],
                ['phone' => '0775556666', 'designation_id' => $designations['packer']->id, 'designation' => $designations['packer']->name, 'salary_type' => 'monthly', 'monthly_salary' => 65000, 'is_active' => true],
            ),
        ];
    }

    private function pieceRates(array $products, array $staff): void
    {
        foreach ([
            ['cutting', null, null, 8],
            ['stitching', null, null, 100],
            ['packing', null, null, 15],
            ['cutting', $staff['cutter']->id, null, 10],
            ['stitching', $staff['stitcher']->id, null, 120],
            ['packing', $staff['packer']->id, null, 0],
            ['stitching', $staff['stitcher']->id, $products['raincoat_medium']->id, 150],
        ] as [$stage, $staffId, $variantId, $rate]) {
            PieceRate::updateOrCreate(
                ['stage' => $stage, 'staff_id' => $staffId, 'product_variant_id' => $variantId],
                ['rate' => $rate],
            );
        }
    }

    private function cuttingYieldRules(array $products, array $materials, array $parts): void
    {
        $tapata = $materials['tapata']['Normal Tapata'];

        foreach ([
            [$products['raincoat_small'], 'Head', 70],
            [$products['raincoat_small'], 'Arms', 80],
            [$products['raincoat_small'], 'Body', 45],
            [$products['raincoat_medium'], 'Head', 60],
            [$products['raincoat_medium'], 'Arms', 50],
            [$products['raincoat_medium'], 'Body', 30],
            [$products['raincoat_large'], 'Head', 40],
            [$products['raincoat_large'], 'Arms', 36],
            [$products['raincoat_large'], 'Body', 24],
        ] as [$variant, $partName, $yield]) {
            CuttingYieldRule::updateOrCreate(
                ['raw_material_variant_id' => $tapata->id, 'product_variant_id' => $variant->id, 'part_id' => $parts[$partName]->id],
                ['yield_per_material_unit' => $yield, 'is_active' => true],
            );
        }
    }

    private function partConversionRules(array $products, array $parts): void
    {
        PartConversionRule::updateOrCreate(
            [
                'from_product_variant_id' => $products['raincoat_large']->id,
                'from_part_id' => $parts['Arms']->id,
                'to_product_variant_id' => $products['raincoat_small']->id,
                'to_part_id' => $parts['Arms']->id,
            ],
            ['output_per_input' => 2, 'is_active' => true],
        );
    }

    private function openingStock(array $products, array $materials, array $parts): void
    {
        $this->rawOpening($materials['tapata']['Normal Tapata'], 8, 10150);
        $this->rawOpening($materials['zip']['Single Line'], 300, 18);
        $this->rawOpening($materials['thread']['Black Color'], 12, 850);
        $this->rawOpening($materials['elastic']['Standard Elastic'], 500, 12);
        $this->rawOpening($materials['buttons']['A Grade Button'], 1000, 3);
        $this->rawOpening($materials['packing']['Polythene Bag'], 800, 6);

        foreach ([
            [$products['raincoat_medium'], 'Head', 20, 75],
            [$products['raincoat_medium'], 'Arms', 40, 60],
            [$products['raincoat_medium'], 'Body', 20, 95],
            [$products['raincoat_small'], 'Arms', 15, 45],
        ] as [$variant, $partName, $quantity, $cost]) {
            $this->partOpening($variant, $parts[$partName], $quantity, $cost, 10);
        }

        $this->finishedOpening($products['raincoat_medium'], 6, 1650, 3);
        $this->finishedOpening($products['tshirt_medium'], 25, 850, 5);
    }

    private function partners(): void
    {
        Supplier::updateOrCreate(
            ['name' => 'Supplier 1'],
            ['phone' => '0777778888', 'email' => 'supplier1@example.com', 'address' => 'Colombo', 'notes' => 'Demo supplier', 'is_active' => true],
        );

        Customer::updateOrCreate(
            ['name' => 'Walk-in customer'],
            ['phone' => '0779990000', 'email' => 'customer@example.com', 'address' => 'Colombo', 'notes' => 'Demo customer', 'is_active' => true],
        );
    }

    private function rawOpening(RawMaterialVariant $variant, float $quantity, float $unitCost): void
    {
        StockBalance::updateOrCreate(
            ['raw_material_variant_id' => $variant->id],
            ['quantity' => $quantity, 'average_cost' => $unitCost],
        );

        StockMovement::updateOrCreate(
            [
                'raw_material_variant_id' => $variant->id,
                'direction' => 'opening',
                'note' => 'Demo opening stock',
            ],
            [
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'balance_quantity' => $quantity,
                'balance_average_cost' => $unitCost,
                'created_by' => null,
            ],
        );
    }

    private function partOpening(ProductVariant $variant, Part $part, int $quantity, float $unitCost, int $alertQuantity): void
    {
        PartStockBalance::updateOrCreate(
            ['product_variant_id' => $variant->id, 'part_id' => $part->id],
            ['quantity' => $quantity, 'average_cost' => $unitCost, 'alert_quantity' => $alertQuantity],
        );

        PartStockMovement::updateOrCreate(
            [
                'product_variant_id' => $variant->id,
                'part_id' => $part->id,
                'stock_type' => 'good',
                'direction' => 'opening',
                'note' => 'Demo opening stock',
            ],
            [
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'balance_quantity' => $quantity,
                'balance_average_cost' => $unitCost,
                'created_by' => null,
            ],
        );
    }

    private function finishedOpening(ProductVariant $variant, int $quantity, float $unitCost, int $alertQuantity): void
    {
        FinishedGood::updateOrCreate(
            ['product_variant_id' => $variant->id],
            ['quantity' => $quantity, 'average_cost' => $unitCost, 'alert_quantity' => $alertQuantity],
        );

        FinishedGoodMovement::updateOrCreate(
            [
                'product_variant_id' => $variant->id,
                'direction' => 'opening',
                'note' => 'Demo opening stock',
            ],
            [
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'balance_quantity' => $quantity,
                'created_by' => null,
            ],
        );
    }
}
