<?php

namespace Tests\Feature;

use App\Models\Part;
use App\Models\PartConversionRule;
use App\Models\PartStockMovement;
use App\Models\PieceRate;
use App\Models\PartStockBalance;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RawMaterial;
use App\Models\RawMaterialVariant;
use App\Models\RecoverablePartBalance;
use App\Models\RecoveryCutting;
use App\Models\Staff;
use App\Models\StockBalance;
use App\Models\Designation;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\CuttingBatch;
use App\Services\PartStockService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductionTest extends TestCase
{
    use RefreshDatabase;

    private function pm(): User
    {
        Role::firstOrCreate(['name' => 'production_manager']);
        $u = User::factory()->create();
        $u->assignRole('production_manager');

        return $u;
    }

    private function seedRecipe(int $partStockQuantity = 200): array
    {
        $cloth = RawMaterial::create(['name' => 'Cloth', 'unit' => 'meter', 'is_active' => true]);
        $clothV = $cloth->variants()->create(['name' => 'Double', 'is_active' => true]);
        $body = Part::create(['name' => 'Body', 'is_active' => true]);

        $product = Product::create(['name' => 'Raincoat', 'is_active' => true]);
        $variant = $product->variants()->create(['name' => 'M/Double', 'layer' => 'double', 'is_active' => true]);

        $variant->recipeMaterials()->create([
            'raw_material_variant_id' => $clothV->id, 'quantity' => 2.0, 'unit' => 'meter',
        ]);
        $variant->recipeParts()->create(['part_id' => $body->id, 'quantity_per_garment' => 2]);

        if ($partStockQuantity > 0) {
            app(PartStockService::class)->receiveGood($variant->id, $body->id, $partStockQuantity);
        }

        return [$variant, $clothV, $body];
    }

    private function staffForStage(string $name, int $priority, array $attributes = []): Staff
    {
        $designation = Designation::firstOrCreate(
            ['name' => "Stage {$priority}"],
            ['priority_level' => $priority, 'is_active' => true],
        );

        return Staff::create([
            'name' => $name,
            'designation_id' => $designation->id,
            'designation' => $designation->name,
            'salary_type' => $attributes['salary_type'] ?? 'piece_rate',
            'monthly_salary' => $attributes['monthly_salary'] ?? null,
            'is_active' => $attributes['is_active'] ?? true,
        ]);
    }

    public function test_cutting_batch_consumes_raw_material_and_creates_good_recoverable_and_scrap_part_stock(): void
    {
        $user = $this->pm();

        $cloth = RawMaterial::create(['name' => 'Tapata', 'unit' => 'roll', 'is_active' => true]);
        $clothV = $cloth->variants()->create(['name' => 'Blue Roll', 'is_active' => true]);
        app(StockService::class)->receive($clothV->id, 10, 500);

        $product = Product::create(['name' => 'Rain Coat', 'is_active' => true]);
        $large = $product->variants()->create(['name' => 'Large', 'is_active' => true]);
        $arm = Part::create(['name' => 'Arm', 'is_active' => true]);
        $body = Part::create(['name' => 'Body', 'is_active' => true]);
        $staff = $this->staffForStage('Cutting Staff', 1);

        $this->actingAs($user)
            ->post(route('cutting-batches.store'), [
                'raw_material_variant_id' => $clothV->id,
                'material_quantity' => 1,
                'staff_id' => $staff->id,
                'cut_on' => '2026-06-24',
                'outputs' => [
                    [
                        'product_variant_id' => $large->id,
                        'part_id' => $arm->id,
                        'yield_per_material_unit' => 80,
                        'expected_quantity' => 40,
                        'good_quantity' => 35,
                        'recoverable_quantity' => 5,
                        'scrap_quantity' => 0,
                    ],
                    [
                        'product_variant_id' => $large->id,
                        'part_id' => $body->id,
                        'yield_per_material_unit' => 40,
                        'expected_quantity' => 20,
                        'good_quantity' => 18,
                        'recoverable_quantity' => 1,
                        'scrap_quantity' => 1,
                    ],
                ],
            ])
            ->assertRedirect();

        $batch = CuttingBatch::first();
        $this->assertNotNull($batch);
        $this->assertEquals('CUT-00001', $batch->code);
        $this->assertEquals(9, (float) StockBalance::where('raw_material_variant_id', $clothV->id)->value('quantity'));
        $this->assertEquals(35, (int) PartStockBalance::where('product_variant_id', $large->id)->where('part_id', $arm->id)->value('quantity'));
        $this->assertEquals(18, (int) PartStockBalance::where('product_variant_id', $large->id)->where('part_id', $body->id)->value('quantity'));
        $this->assertEquals(5, (int) RecoverablePartBalance::where('product_variant_id', $large->id)->where('part_id', $arm->id)->value('quantity'));
        $this->assertEquals(1, (int) RecoverablePartBalance::where('product_variant_id', $large->id)->where('part_id', $body->id)->value('quantity'));
        $this->assertDatabaseHas('part_stock_movements', [
            'product_variant_id' => $large->id,
            'part_id' => $body->id,
            'stock_type' => 'scrap',
            'direction' => 'scrap',
            'quantity' => 1,
            'reference_type' => CuttingBatch::class,
        ]);
    }

    public function test_recovery_cutting_converts_recoverable_parts_into_smaller_good_parts(): void
    {
        $user = $this->pm();
        $product = Product::create(['name' => 'Rain Coat', 'is_active' => true]);
        $large = $product->variants()->create(['name' => 'Large', 'is_active' => true]);
        $small = $product->variants()->create(['name' => 'Small', 'is_active' => true]);
        $arm = Part::create(['name' => 'Arm', 'is_active' => true]);
        $staff = $this->staffForStage('Recovery Staff', 1);

        app(PartStockService::class)->receiveRecoverable($large->id, $arm->id, 5);
        PartConversionRule::create([
            'from_product_variant_id' => $large->id,
            'from_part_id' => $arm->id,
            'to_product_variant_id' => $small->id,
            'to_part_id' => $arm->id,
            'output_per_input' => 2,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->post(route('recovery-cuttings.store'), [
                'from_product_variant_id' => $large->id,
                'from_part_id' => $arm->id,
                'input_quantity' => 5,
                'to_product_variant_id' => $small->id,
                'to_part_id' => $arm->id,
                'expected_quantity' => 10,
                'good_quantity' => 8,
                'scrap_quantity' => 2,
                'staff_id' => $staff->id,
                'cut_on' => '2026-06-24',
            ])
            ->assertRedirect();

        $recovery = RecoveryCutting::first();
        $this->assertNotNull($recovery);
        $this->assertEquals('RCV-00001', $recovery->code);
        $this->assertEquals(0, (int) RecoverablePartBalance::where('product_variant_id', $large->id)->where('part_id', $arm->id)->value('quantity'));
        $this->assertEquals(8, (int) PartStockBalance::where('product_variant_id', $small->id)->where('part_id', $arm->id)->value('quantity'));
        $this->assertDatabaseHas('part_stock_movements', [
            'product_variant_id' => $large->id,
            'part_id' => $arm->id,
            'stock_type' => 'recoverable',
            'direction' => 'out',
            'quantity' => 5,
            'reference_type' => RecoveryCutting::class,
        ]);
        $this->assertDatabaseHas('part_stock_movements', [
            'product_variant_id' => $large->id,
            'part_id' => $arm->id,
            'stock_type' => 'scrap',
            'direction' => 'scrap',
            'quantity' => 2,
            'reference_type' => RecoveryCutting::class,
        ]);
    }

    public function test_cutting_batch_can_be_edited_and_deleted_with_stock_reversal(): void
    {
        $user = $this->pm();

        $cloth = RawMaterial::create(['name' => 'Tapata', 'unit' => 'roll', 'is_active' => true]);
        $clothV = $cloth->variants()->create(['name' => 'Blue Roll', 'is_active' => true]);
        app(StockService::class)->receive($clothV->id, 10, 500);

        $product = Product::create(['name' => 'Rain Coat', 'is_active' => true]);
        $large = $product->variants()->create(['name' => 'Large', 'is_active' => true]);
        $arm = Part::create(['name' => 'Arm', 'is_active' => true]);

        $payload = [
            'raw_material_variant_id' => $clothV->id,
            'material_quantity' => 1,
            'cut_on' => '2026-06-24',
            'outputs' => [[
                'product_variant_id' => $large->id,
                'part_id' => $arm->id,
                'yield_per_material_unit' => 40,
                'expected_quantity' => 40,
                'good_quantity' => 35,
                'recoverable_quantity' => 5,
                'scrap_quantity' => 0,
            ]],
        ];

        $this->actingAs($user)->post(route('cutting-batches.store'), $payload)->assertRedirect();
        $batch = CuttingBatch::first();

        $this->assertEquals(9, (float) StockBalance::where('raw_material_variant_id', $clothV->id)->value('quantity'));
        $this->assertEquals(35, (int) PartStockBalance::where('product_variant_id', $large->id)->where('part_id', $arm->id)->value('quantity'));

        $payload['material_quantity'] = 2;
        $payload['outputs'][0]['good_quantity'] = 60;
        $payload['outputs'][0]['recoverable_quantity'] = 3;
        $payload['outputs'][0]['scrap_quantity'] = 2;
        $this->actingAs($user)->put(route('cutting-batches.update', $batch), $payload)->assertRedirect();

        $this->assertEquals(8, (float) StockBalance::where('raw_material_variant_id', $clothV->id)->value('quantity'));
        $this->assertEquals(60, (int) PartStockBalance::where('product_variant_id', $large->id)->where('part_id', $arm->id)->value('quantity'));
        $this->assertEquals(3, (int) RecoverablePartBalance::where('product_variant_id', $large->id)->where('part_id', $arm->id)->value('quantity'));
        $this->assertDatabaseHas('part_stock_movements', [
            'reference_type' => CuttingBatch::class,
            'reference_id' => $batch->id,
            'stock_type' => 'scrap',
            'direction' => 'scrap',
            'quantity' => 2,
        ]);

        $this->actingAs($user)->delete(route('cutting-batches.destroy', $batch))->assertRedirect();

        $this->assertDatabaseMissing('cutting_batches', ['id' => $batch->id]);
        $this->assertEquals(10, (float) StockBalance::where('raw_material_variant_id', $clothV->id)->value('quantity'));
        $this->assertEquals(0, (int) PartStockBalance::where('product_variant_id', $large->id)->where('part_id', $arm->id)->value('quantity'));
        $this->assertEquals(0, (int) RecoverablePartBalance::where('product_variant_id', $large->id)->where('part_id', $arm->id)->value('quantity'));
        $this->assertDatabaseHas('part_stock_movements', [
            'reference_type' => CuttingBatch::class,
            'reference_id' => $batch->id,
            'stock_type' => 'scrap',
            'direction' => 'scrap_reversal',
            'quantity' => 2,
        ]);
    }

    public function test_recovery_cutting_can_be_edited_and_deleted_with_stock_reversal(): void
    {
        $user = $this->pm();
        $product = Product::create(['name' => 'Rain Coat', 'is_active' => true]);
        $large = $product->variants()->create(['name' => 'Large', 'is_active' => true]);
        $small = $product->variants()->create(['name' => 'Small', 'is_active' => true]);
        $arm = Part::create(['name' => 'Arm', 'is_active' => true]);

        app(PartStockService::class)->receiveRecoverable($large->id, $arm->id, 10);

        $payload = [
            'from_product_variant_id' => $large->id,
            'from_part_id' => $arm->id,
            'input_quantity' => 5,
            'to_product_variant_id' => $small->id,
            'to_part_id' => $arm->id,
            'expected_quantity' => 10,
            'good_quantity' => 8,
            'scrap_quantity' => 2,
            'cut_on' => '2026-06-24',
        ];

        $this->actingAs($user)->post(route('recovery-cuttings.store'), $payload)->assertRedirect();
        $recovery = RecoveryCutting::first();

        $this->assertEquals(5, (int) RecoverablePartBalance::where('product_variant_id', $large->id)->where('part_id', $arm->id)->value('quantity'));
        $this->assertEquals(8, (int) PartStockBalance::where('product_variant_id', $small->id)->where('part_id', $arm->id)->value('quantity'));

        $payload['input_quantity'] = 6;
        $payload['expected_quantity'] = 12;
        $payload['good_quantity'] = 9;
        $payload['scrap_quantity'] = 3;
        $this->actingAs($user)->put(route('recovery-cuttings.update', $recovery), $payload)->assertRedirect();

        $this->assertEquals(4, (int) RecoverablePartBalance::where('product_variant_id', $large->id)->where('part_id', $arm->id)->value('quantity'));
        $this->assertEquals(9, (int) PartStockBalance::where('product_variant_id', $small->id)->where('part_id', $arm->id)->value('quantity'));

        $this->actingAs($user)->delete(route('recovery-cuttings.destroy', $recovery))->assertRedirect();

        $this->assertDatabaseMissing('recovery_cuttings', ['id' => $recovery->id]);
        $this->assertEquals(10, (int) RecoverablePartBalance::where('product_variant_id', $large->id)->where('part_id', $arm->id)->value('quantity'));
        $this->assertEquals(0, (int) PartStockBalance::where('product_variant_id', $small->id)->where('part_id', $arm->id)->value('quantity'));
        $this->assertDatabaseHas('part_stock_movements', [
            'reference_type' => RecoveryCutting::class,
            'reference_id' => $recovery->id,
            'stock_type' => 'scrap',
            'direction' => 'scrap_reversal',
            'quantity' => 3,
        ]);
    }

    public function test_full_production_flow_consumes_stock_and_records_wages(): void
    {
        $user = $this->pm();
        [$variant, $clothV, $body] = $this->seedRecipe();

        // Stock the cloth: 100 m @ 10.
        app(StockService::class)->receive($clothV->id, 100, 10);

        // Default stitching rate.
        PieceRate::create(['stage' => 'stitching', 'product_variant_id' => null, 'rate' => 5]);

        // Create a work order for 10 garments.
        $this->actingAs($user)->post(route('work-orders.store'), [
            'product_variant_id' => $variant->id,
            'quantity' => 10,
        ])->assertRedirect();
        $wo = WorkOrder::firstWhere('product_variant_id', $variant->id);
        $this->assertEquals('draft', $wo->status);

        // Release: needs 2 * 10 = 20 pre-cut body pieces.
        $this->actingAs($user)->post(route('work-orders.release', $wo))->assertRedirect();
        $wo->refresh();
        $this->assertEquals('in_production', $wo->status);
        $this->assertEquals(0.00, (float) $wo->material_cost);

        // Raw material is untouched by work orders; part stock dropped 200 -> 180.
        $this->assertEquals(100, (float) StockBalance::where('raw_material_variant_id', $clothV->id)->value('quantity'));
        $this->assertEquals(180, (int) PartStockBalance::where('product_variant_id', $variant->id)->where('part_id', $body->id)->value('quantity'));
        // Parts snapshot.
        $this->assertDatabaseHas('work_order_parts', ['work_order_id' => $wo->id, 'quantity' => 20]);

        // Issue a stitching job card for 10 pieces @ 5.
        $staff = $this->staffForStage('Cutter A', 2);
        $this->actingAs($user)->post(route('job-cards.store', $wo), [
            'stage' => 'stitching', 'staff_id' => $staff->id, 'quantity_issued' => 10, 'piece_rate' => 5,
        ])->assertRedirect();
        $card = $wo->jobCards()->first();

        // Receive it: 10 good -> no pending balance, wage 10*5 = 50.
        $this->actingAs($user)->post(route('job-cards.complete', $card), [
            'quantity_received' => 10,
            'quantity_damaged' => 0,
        ])->assertRedirect();
        $card->refresh();
        $this->assertEquals(50.00, (float) $card->wage_amount);
        $this->assertEquals(0, $card->quantity_damaged);
        $this->assertEquals(0, $card->pending_quantity);
        $this->assertDatabaseHas('work_order_parts', ['work_order_id' => $wo->id, 'quantity' => 20]);

        // Complete the work order.
        $this->actingAs($user)->post(route('work-orders.complete', $wo), [
            'completed_quantity' => 10,
            'rejected_quantity' => 0,
        ])->assertRedirect();
        $this->assertEquals('completed', $wo->fresh()->status);
        $this->assertDatabaseHas('finished_good_movements', [
            'product_variant_id' => $variant->id,
            'direction' => 'in',
            'quantity' => 10,
            'balance_quantity' => 10,
        ]);
    }

    public function test_completion_records_rejections_and_blocks_over_completion(): void
    {
        $user = $this->pm();
        [$variant, $clothV] = $this->seedRecipe();
        app(StockService::class)->receive($clothV->id, 100, 10);

        $wo = WorkOrder::create([
            'product_variant_id' => $variant->id,
            'quantity' => 10,
            'status' => 'draft',
            'created_by' => $user->id,
        ]);
        $this->actingAs($user)->post(route('work-orders.release', $wo))->assertRedirect();

        $this->actingAs($user)->post(route('work-orders.complete', $wo), [
            'completed_quantity' => 9,
            'rejected_quantity' => 2,
        ])->assertSessionHasErrors('completed_quantity');

        $staff = $this->staffForStage('Cutter B', 2);
        $this->actingAs($user)->post(route('job-cards.store', $wo), [
            'stage' => 'stitching',
            'staff_id' => $staff->id,
            'quantity_issued' => 10,
            'piece_rate' => 5,
        ])->assertRedirect();
        $card = $wo->jobCards()->first();
        $this->actingAs($user)->post(route('job-cards.complete', $card), [
            'quantity_received' => 10,
        ])->assertRedirect();

        $this->actingAs($user)->post(route('work-orders.complete', $wo), [
            'completed_quantity' => 8,
            'rejected_quantity' => 1,
            'completion_notes' => 'One damaged during stitching.',
        ])->assertRedirect();

        $wo->refresh();
        $this->assertEquals('completed', $wo->status);
        $this->assertEquals(8, $wo->completed_quantity);
        $this->assertEquals(1, $wo->rejected_quantity);
        $this->assertEquals('One damaged during stitching.', $wo->completion_notes);
    }

    public function test_job_card_can_be_received_in_multiple_collections_with_wage_tracking(): void
    {
        $user = $this->pm();
        [$variant] = $this->seedRecipe();

        $wo = WorkOrder::create([
            'product_variant_id' => $variant->id,
            'quantity' => 10,
            'status' => 'in_production',
            'created_by' => $user->id,
        ]);
        $staff = $this->staffForStage('Stitcher A', 2);

        $this->actingAs($user)->post(route('job-cards.store', $wo), [
            'stage' => 'stitching',
            'staff_id' => $staff->id,
            'quantity_issued' => 10,
            'piece_rate' => 100,
            'started_at' => '2026-06-24 07:00:00',
        ])->assertRedirect();

        $card = $wo->jobCards()->first();
        $this->assertNotNull($card);

        $this->actingAs($user)->post(route('job-cards.complete', $card), [
            'quantity_received' => 5,
            'quantity_damaged' => 0,
            'wage_paid_amount' => 300,
            'completed_at' => '2026-06-24 08:00:00',
        ])->assertRedirect();

        $card->refresh();
        $this->assertEquals('partial', $card->status);
        $this->assertEquals(5, $card->quantity_received);
        $this->assertEquals(5, $card->pending_quantity);
        $this->assertEquals(500.00, (float) $card->wage_amount);
        $this->assertEquals(300.00, (float) $card->wage_paid_amount);
        $this->assertEquals('pending', $card->wage_status);
        $this->assertEquals(1, $card->receipts()->count());
        $this->assertEquals(1, $card->payments()->count());

        $this->actingAs($user)->post(route('job-cards.complete', $card), [
            'quantity_received' => 5,
            'quantity_damaged' => 0,
            'wage_paid_amount' => 700,
            'completed_at' => '2026-06-24 10:30:00',
        ])->assertRedirect();

        $card->refresh();
        $this->assertEquals('completed', $card->status);
        $this->assertEquals(10, $card->quantity_received);
        $this->assertEquals(0, $card->pending_quantity);
        $this->assertEquals(1000.00, (float) $card->wage_amount);
        $this->assertEquals(1000.00, (float) $card->wage_paid_amount);
        $this->assertEquals('paid', $card->wage_status);
        $this->assertEquals(2, $card->receipts()->count());
        $this->assertEquals(2, $card->payments()->count());
        $this->assertEquals([60, 150], $card->receipts()->orderBy('received_at')->pluck('duration_minutes')->all());
    }

    public function test_job_card_payment_can_be_recorded_without_receiving_more_pieces(): void
    {
        $user = $this->pm();
        [$variant] = $this->seedRecipe();

        $wo = WorkOrder::create([
            'product_variant_id' => $variant->id,
            'quantity' => 10,
            'status' => 'in_production',
            'created_by' => $user->id,
        ]);
        $staff = $this->staffForStage('Stitcher B', 2);

        $this->actingAs($user)->post(route('job-cards.store', $wo), [
            'stage' => 'stitching',
            'staff_id' => $staff->id,
            'quantity_issued' => 10,
            'piece_rate' => 100,
            'started_at' => '2026-06-24 07:00:00',
        ])->assertRedirect();

        $card = $wo->jobCards()->first();

        $this->actingAs($user)->post(route('job-cards.complete', $card), [
            'quantity_received' => 5,
            'wage_paid_amount' => 0,
            'completed_at' => '2026-06-24 08:00:00',
        ])->assertRedirect();

        $this->actingAs($user)->post(route('job-cards.payments.store', $card), [
            'paid_on' => '2026-06-24',
            'amount' => 250,
            'method' => 'Cash',
            'reference' => 'PAY-1',
            'notes' => 'Advance payment',
        ])->assertRedirect();

        $card->refresh();
        $this->assertEquals(500.00, (float) $card->wage_amount);
        $this->assertEquals(250.00, (float) $card->wage_paid_amount);
        $this->assertEquals('pending', $card->wage_status);
        $this->assertDatabaseHas('job_card_payments', [
            'job_card_id' => $card->id,
            'amount' => 250,
            'method' => 'Cash',
            'reference' => 'PAY-1',
            'source' => 'manual',
        ]);

        $payment = $card->payments()->first();

        $this->actingAs($user)->put(route('job-card-payments.update', $payment), [
            'paid_on' => '2026-06-25',
            'amount' => 300,
            'method' => 'Bank',
            'reference' => 'PAY-2',
            'notes' => 'Adjusted payment',
        ])->assertRedirect();

        $card->refresh();
        $this->assertEquals(300.00, (float) $card->wage_paid_amount);
        $this->assertDatabaseHas('job_card_payments', [
            'id' => $payment->id,
            'amount' => 300,
            'method' => 'Bank',
            'reference' => 'PAY-2',
        ]);

        $this->actingAs($user)->delete(route('job-card-payments.destroy', $payment))->assertRedirect();

        $card->refresh();
        $this->assertEquals(0.00, (float) $card->wage_paid_amount);
        $this->assertDatabaseMissing('job_card_payments', ['id' => $payment->id]);
    }

    public function test_monthly_salary_staff_do_not_generate_piece_wages(): void
    {
        $user = $this->pm();
        [$variant] = $this->seedRecipe();

        $wo = WorkOrder::create([
            'product_variant_id' => $variant->id,
            'quantity' => 10,
            'status' => 'in_production',
            'created_by' => $user->id,
        ]);
        $staff = $this->staffForStage('Supervisor A', 2, [
            'salary_type' => 'monthly',
            'monthly_salary' => 50000,
        ]);

        $this->actingAs($user)->post(route('job-cards.store', $wo), [
            'stage' => 'stitching',
            'staff_id' => $staff->id,
            'quantity_issued' => 10,
            'piece_rate' => 100,
            'started_at' => '2026-06-24 09:00:00',
        ])->assertRedirect();

        $card = $wo->jobCards()->first();
        $this->actingAs($user)->post(route('job-cards.complete', $card), [
            'quantity_received' => 10,
            'wage_paid_amount' => 0,
            'completed_at' => '2026-06-24 11:00:00',
        ])->assertRedirect();

        $card->refresh();
        $this->assertEquals(0.00, (float) $card->wage_amount);
        $this->assertEquals('paid', $card->wage_status);
        $this->assertEquals(0.00, (float) $card->receipts()->first()->wage_amount);
    }

    public function test_final_stage_receipt_completes_work_order_and_adds_finished_goods(): void
    {
        $user = $this->pm();
        [$variant] = $this->seedRecipe();

        $wo = WorkOrder::create([
            'product_variant_id' => $variant->id,
            'quantity' => 10,
            'status' => 'in_production',
            'created_by' => $user->id,
        ]);
        $staff = $this->staffForStage('Packer A', 3);
        $stitcher = $this->staffForStage('Stitcher E', 2);

        $this->actingAs($user)->post(route('job-cards.store', $wo), [
            'stage' => 'stitching',
            'staff_id' => $stitcher->id,
            'quantity_issued' => 10,
            'piece_rate' => 5,
        ])->assertRedirect();
        $stitchingCard = $wo->jobCards()->where('stage', 'stitching')->first();
        $this->actingAs($user)->post(route('job-cards.complete', $stitchingCard), [
            'quantity_received' => 10,
        ])->assertRedirect();

        $this->actingAs($user)->post(route('job-cards.store', $wo), [
            'stage' => 'packing',
            'staff_id' => $staff->id,
            'quantity_issued' => 10,
            'piece_rate' => 20,
        ])->assertRedirect();

        $card = $wo->jobCards()->where('stage', 'packing')->first();
        $this->actingAs($user)->post(route('job-cards.complete', $card), [
            'quantity_received' => 8,
            'quantity_damaged' => 2,
        ])->assertRedirect();

        $wo->refresh();
        $this->assertEquals('completed', $wo->status);
        $this->assertEquals(8, $wo->completed_quantity);
        $this->assertEquals(2, $wo->rejected_quantity);
        $this->assertDatabaseHas('finished_goods', [
            'product_variant_id' => $variant->id,
            'quantity' => 8,
        ]);
        $this->assertDatabaseHas('finished_good_movements', [
            'product_variant_id' => $variant->id,
            'direction' => 'in',
            'quantity' => 8,
            'reference_type' => WorkOrder::class,
            'reference_id' => $wo->id,
        ]);
    }

    public function test_final_stage_cannot_be_issued_before_previous_stage_has_good_output(): void
    {
        $user = $this->pm();
        [$variant] = $this->seedRecipe();

        $wo = WorkOrder::create([
            'product_variant_id' => $variant->id,
            'quantity' => 10,
            'status' => 'in_production',
            'created_by' => $user->id,
        ]);
        $packer = $this->staffForStage('Packer B', 3);

        $this->actingAs($user)->post(route('job-cards.store', $wo), [
            'stage' => 'packing',
            'staff_id' => $packer->id,
            'quantity_issued' => 10,
            'piece_rate' => 20,
        ])->assertSessionHasErrors('quantity_issued');

        $this->assertDatabaseMissing('job_cards', [
            'work_order_id' => $wo->id,
            'stage' => 'packing',
        ]);
    }

    public function test_first_stage_receipt_tracks_stage_progress_without_cutting_parts(): void
    {
        $user = $this->pm();
        [$variant, $clothV] = $this->seedRecipe();
        app(StockService::class)->receive($clothV->id, 100, 10);

        $wo = WorkOrder::create([
            'product_variant_id' => $variant->id,
            'quantity' => 10,
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->post(route('work-orders.release', $wo))->assertRedirect();

        $staff = $this->staffForStage('Cutter D', 2);
        $this->actingAs($user)->post(route('job-cards.store', $wo), [
            'stage' => 'stitching',
            'staff_id' => $staff->id,
            'quantity_issued' => 10,
            'piece_rate' => 5,
        ])->assertRedirect();

        $card = $wo->jobCards()->first();
        $this->actingAs($user)->post(route('job-cards.complete', $card), [
            'quantity_received' => 6,
            'quantity_damaged' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('work_order_parts', ['work_order_id' => $wo->id, 'quantity' => 20]);

        $this->actingAs($user)->get(route('work-orders.show', $wo))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('stageProgress.0.label', 'Stitching')
                ->where('stageProgress.0.good', 6)
                ->where('stageProgress.0.ready_for_next', 6)
                ->where('stageProgress.1.label', 'Packing')
                ->where('stageProgress.1.available', 6)
            );
    }

    public function test_job_card_requires_staff_assignment(): void
    {
        $user = $this->pm();
        [$variant] = $this->seedRecipe();
        $wrongStageStaff = $this->staffForStage('Packing Staff', 3);

        $wo = WorkOrder::create([
            'product_variant_id' => $variant->id,
            'quantity' => 10,
            'status' => 'in_production',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->post(route('job-cards.store', $wo), [
            'stage' => 'stitching',
            'staff_id' => '',
            'quantity_issued' => 10,
            'piece_rate' => 5,
        ])->assertSessionHasErrors('staff_id');

        $this->actingAs($user)->post(route('job-cards.store', $wo), [
            'stage' => 'stitching',
            'staff_id' => $wrongStageStaff->id,
            'quantity_issued' => 10,
            'piece_rate' => 5,
        ])->assertSessionHasErrors('staff_id');

        $this->assertDatabaseMissing('job_cards', [
            'work_order_id' => $wo->id,
            'stage' => 'stitching',
        ]);
    }

    public function test_job_card_issue_quantity_cannot_exceed_work_order_quantity_for_stage(): void
    {
        $user = $this->pm();
        [$variant] = $this->seedRecipe();

        $wo = WorkOrder::create([
            'product_variant_id' => $variant->id,
            'quantity' => 10,
            'status' => 'in_production',
            'created_by' => $user->id,
        ]);
        $staff = $this->staffForStage('Stitcher B', 2);
        $packer = $this->staffForStage('Packer B', 3);

        $this->actingAs($user)->post(route('job-cards.store', $wo), [
            'stage' => 'stitching',
            'staff_id' => $staff->id,
            'quantity_issued' => 15,
            'piece_rate' => 5,
        ])->assertSessionHasErrors('quantity_issued');

        $this->actingAs($user)->post(route('job-cards.store', $wo), [
            'stage' => 'stitching',
            'staff_id' => $staff->id,
            'quantity_issued' => 6,
            'piece_rate' => 5,
        ])->assertRedirect();

        $this->actingAs($user)->post(route('job-cards.store', $wo), [
            'stage' => 'stitching',
            'staff_id' => $staff->id,
            'quantity_issued' => 5,
            'piece_rate' => 5,
        ])->assertSessionHasErrors('quantity_issued');

        $this->actingAs($user)->post(route('job-cards.store', $wo), [
            'stage' => 'packing',
            'staff_id' => $packer->id,
            'quantity_issued' => 10,
            'piece_rate' => 5,
        ])->assertSessionHasErrors('quantity_issued');

        $cuttingCard = $wo->jobCards()->where('stage', 'stitching')->first();
        $this->actingAs($user)->post(route('job-cards.complete', $cuttingCard), [
            'quantity_received' => 6,
        ])->assertRedirect();

        $this->actingAs($user)->post(route('job-cards.store', $wo), [
            'stage' => 'packing',
            'staff_id' => $packer->id,
            'quantity_issued' => 10,
            'piece_rate' => 5,
        ])->assertSessionHasErrors('quantity_issued');

        $this->actingAs($user)->post(route('job-cards.store', $wo), [
            'stage' => 'packing',
            'staff_id' => $packer->id,
            'quantity_issued' => 6,
            'piece_rate' => 5,
        ])->assertRedirect();
    }

    public function test_job_card_can_be_edited_and_recalculates_receipt_wages(): void
    {
        $user = $this->pm();
        [$variant] = $this->seedRecipe();

        $wo = WorkOrder::create([
            'product_variant_id' => $variant->id,
            'quantity' => 10,
            'status' => 'in_production',
            'created_by' => $user->id,
        ]);
        $staff = $this->staffForStage('Cutter C', 2);
        $stitcher = $this->staffForStage('Stitcher C', 2);

        $this->actingAs($user)->post(route('job-cards.store', $wo), [
            'stage' => 'stitching',
            'staff_id' => $staff->id,
            'quantity_issued' => 10,
            'piece_rate' => 100,
            'started_at' => '2026-06-24 07:00:00',
        ])->assertRedirect();

        $card = $wo->jobCards()->first();
        $this->actingAs($user)->post(route('job-cards.complete', $card), [
            'quantity_received' => 5,
            'wage_paid_amount' => 0,
            'completed_at' => '2026-06-24 08:00:00',
        ])->assertRedirect();

        $this->actingAs($user)->put(route('job-cards.update', $card), [
            'stage' => 'stitching',
            'staff_id' => $stitcher->id,
            'quantity_issued' => 12,
            'piece_rate' => 120,
            'started_at' => '2026-06-24 07:15:00',
            'notes' => 'Moved to stitching team.',
        ])->assertSessionHasErrors('quantity_issued');

        $this->actingAs($user)->put(route('job-cards.update', $card), [
            'stage' => 'stitching',
            'staff_id' => $stitcher->id,
            'quantity_issued' => 5,
            'piece_rate' => 120,
            'started_at' => '2026-06-24 07:15:00',
            'notes' => 'Moved to stitching team.',
        ])->assertRedirect();

        $card->refresh();
        $this->assertEquals('stitching', $card->stage);
        $this->assertEquals(5, $card->quantity_issued);
        $this->assertEquals(0, $card->pending_quantity);
        $this->assertEquals(600.00, (float) $card->wage_amount);
        $this->assertEquals('Moved to stitching team.', $card->notes);
        $this->assertEquals(600.00, (float) $card->receipts()->first()->wage_amount);

        $this->actingAs($user)->put(route('job-cards.update', $card), [
            'stage' => 'stitching',
            'staff_id' => $stitcher->id,
            'quantity_issued' => 4,
            'piece_rate' => 120,
        ])->assertSessionHasErrors('quantity_issued');
    }

    public function test_in_production_work_order_edit_and_delete_reconcile_part_stock(): void
    {
        $user = $this->pm();
        [$variant, $clothV, $body] = $this->seedRecipe();
        app(StockService::class)->receive($clothV->id, 100, 10);

        $wo = WorkOrder::create([
            'product_variant_id' => $variant->id,
            'quantity' => 10,
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->post(route('work-orders.release', $wo))->assertRedirect();
        $this->assertEquals(180, (int) PartStockBalance::where('product_variant_id', $variant->id)->where('part_id', $body->id)->value('quantity'));

        $this->actingAs($user)->put(route('work-orders.update', $wo), [
            'product_variant_id' => $variant->id,
            'quantity' => 20,
            'target_delivery_date' => '2026-06-30',
            'notes' => 'Increased order.',
        ])->assertRedirect();

        $wo->refresh();
        $this->assertEquals('in_production', $wo->status);
        $this->assertEquals(20, $wo->quantity);
        $this->assertEquals(0.00, (float) $wo->material_cost);
        $this->assertEquals(40, (float) $wo->parts()->first()->quantity);
        $this->assertEquals(160, (int) PartStockBalance::where('product_variant_id', $variant->id)->where('part_id', $body->id)->value('quantity'));

        $this->actingAs($user)->delete(route('work-orders.destroy', $wo))->assertRedirect(route('work-orders.index'));

        $this->assertDatabaseMissing('work_orders', ['id' => $wo->id]);
        $this->assertEquals(200, (int) PartStockBalance::where('product_variant_id', $variant->id)->where('part_id', $body->id)->value('quantity'));
    }

    public function test_work_order_allocation_cannot_be_changed_or_deleted_after_job_cards_exist(): void
    {
        $user = $this->pm();
        [$variant, $clothV, $body] = $this->seedRecipe();
        app(StockService::class)->receive($clothV->id, 100, 10);

        $wo = WorkOrder::create([
            'product_variant_id' => $variant->id,
            'quantity' => 10,
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->post(route('work-orders.release', $wo))->assertRedirect();

        $wo->jobCards()->create([
            'stage' => 'stitching',
            'staff_id' => $this->staffForStage('Stitcher', 2)->id,
            'quantity_issued' => 5,
            'piece_rate' => 100,
            'status' => 'issued',
        ]);

        $this->actingAs($user)->put(route('work-orders.update', $wo), [
            'product_variant_id' => $variant->id,
            'quantity' => 20,
            'target_delivery_date' => '2026-06-30',
            'notes' => 'Try to change allocation.',
        ])->assertSessionHasErrors('work_order');

        $this->actingAs($user)->delete(route('work-orders.destroy', $wo))
            ->assertSessionHasErrors('work_order');

        $wo->refresh();
        $this->assertEquals(10, $wo->quantity);
        $this->assertDatabaseHas('work_orders', ['id' => $wo->id]);
        $this->assertEquals(180, (int) PartStockBalance::where('product_variant_id', $variant->id)->where('part_id', $body->id)->value('quantity'));
    }

    public function test_release_blocks_when_stock_is_short(): void
    {
        $user = $this->pm();
        [$variant, $clothV, $body] = $this->seedRecipe(5); // only 5 parts, need 20
        app(StockService::class)->receive($clothV->id, 5, 10);

        $this->actingAs($user)->post(route('work-orders.store'), [
            'product_variant_id' => $variant->id, 'quantity' => 10,
        ]);
        $wo = WorkOrder::firstWhere('product_variant_id', $variant->id);

        $this->actingAs($user)->post(route('work-orders.release', $wo))->assertSessionHasErrors('release');
        $this->assertEquals('draft', $wo->fresh()->status);
        // Stock untouched.
        $this->assertEquals(5, (float) StockBalance::where('raw_material_variant_id', $clothV->id)->value('quantity'));
        $this->assertEquals(5, (int) PartStockBalance::where('product_variant_id', $variant->id)->where('part_id', $body->id)->value('quantity'));
    }
}



