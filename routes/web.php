<?php

use App\Http\Controllers\Masters\PartController;
use App\Http\Controllers\Masters\DesignationController;
use App\Http\Controllers\Masters\ProductController;
use App\Http\Controllers\Masters\ProductCategoryController;
use App\Http\Controllers\Masters\ProductGradeController;
use App\Http\Controllers\Masters\ProductLayerController;
use App\Http\Controllers\Masters\ProductSizeController;
use App\Http\Controllers\Masters\ProductVariantController;
use App\Http\Controllers\Masters\RawMaterialController;
use App\Http\Controllers\Masters\RawMaterialVariantController;
use App\Http\Controllers\Masters\StaffController;
use App\Http\Controllers\Masters\SupplierController;
use App\Http\Controllers\Masters\CustomerController;
use App\Http\Controllers\BusinessSettings\ProductionStageController;
use App\Http\Controllers\BusinessSettings\ExpenseCategoryController;
use App\Http\Controllers\BusinessSettings\GeneralSettingController;
use App\Http\Controllers\BusinessSettings\PartConversionRuleController;
use App\Http\Controllers\BusinessSettings\PaymentMethodController;
use App\Http\Controllers\BusinessSettings\CuttingYieldRuleController;
use App\Http\Controllers\BusinessSettings\SystemUserController;
use App\Http\Controllers\BusinessSettings\UnitOfMeasureController;
use App\Http\Controllers\AccountingController;
use App\Http\Controllers\CostingController;
use App\Http\Controllers\CuttingBatchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FinishedGoodController;
use App\Http\Controllers\JobCardController;
use App\Http\Controllers\PartStockController;
use App\Http\Controllers\PieceRateController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\PurchasePaymentController;
use App\Http\Controllers\PurchaseReturnController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SaleReturnController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\WageController;
use App\Http\Controllers\WorkOrderController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

/*
 | Masters — manageable by admin, stock manager and production manager.
 */
Route::middleware(['auth', 'role:admin|stock_manager|production_manager'])
    ->prefix('masters')
    ->name('masters.')
    ->group(function () {
        // Raw materials + their variants
        Route::get('raw-materials', [RawMaterialController::class, 'index'])->name('raw-materials.index');
        Route::post('raw-materials', [RawMaterialController::class, 'store'])->name('raw-materials.store');
        Route::put('raw-materials/{rawMaterial}', [RawMaterialController::class, 'update'])->name('raw-materials.update');
        Route::delete('raw-materials/{rawMaterial}', [RawMaterialController::class, 'destroy'])->name('raw-materials.destroy');
        Route::get('raw-materials/{rawMaterial}/variants', [RawMaterialController::class, 'variants'])->name('raw-materials.variants');
        Route::post('raw-materials/{rawMaterial}/variants', [RawMaterialVariantController::class, 'store'])->name('raw-material-variants.store');
        Route::put('raw-material-variants/{variant}', [RawMaterialVariantController::class, 'update'])->name('raw-material-variants.update');
        Route::delete('raw-material-variants/{variant}', [RawMaterialVariantController::class, 'destroy'])->name('raw-material-variants.destroy');

        // Products + their variants
        Route::get('product-categories', [ProductCategoryController::class, 'index'])->name('product-categories.index');
        Route::post('product-categories', [ProductCategoryController::class, 'store'])->name('product-categories.store');
        Route::put('product-categories/{category}', [ProductCategoryController::class, 'update'])->name('product-categories.update');
        Route::delete('product-categories/{category}', [ProductCategoryController::class, 'destroy'])->name('product-categories.destroy');

        Route::get('products', [ProductController::class, 'index'])->name('products.index');
        Route::post('products', [ProductController::class, 'store'])->name('products.store');
        Route::put('products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');

        Route::get('product-sizes', [ProductSizeController::class, 'index'])->name('product-sizes.index');
        Route::post('product-sizes', [ProductSizeController::class, 'store'])->name('product-sizes.store');
        Route::put('product-sizes/{size}', [ProductSizeController::class, 'update'])->name('product-sizes.update');
        Route::delete('product-sizes/{size}', [ProductSizeController::class, 'destroy'])->name('product-sizes.destroy');
        Route::get('product-layers', [ProductLayerController::class, 'index'])->name('product-layers.index');
        Route::post('product-layers', [ProductLayerController::class, 'store'])->name('product-layers.store');
        Route::put('product-layers/{layer}', [ProductLayerController::class, 'update'])->name('product-layers.update');
        Route::delete('product-layers/{layer}', [ProductLayerController::class, 'destroy'])->name('product-layers.destroy');
        Route::get('product-grades', [ProductGradeController::class, 'index'])->name('product-grades.index');
        Route::post('product-grades', [ProductGradeController::class, 'store'])->name('product-grades.store');
        Route::put('product-grades/{grade}', [ProductGradeController::class, 'update'])->name('product-grades.update');
        Route::delete('product-grades/{grade}', [ProductGradeController::class, 'destroy'])->name('product-grades.destroy');

        Route::get('products/{product}/variants', [ProductController::class, 'variants'])->name('products.variants');
        Route::post('products/{product}/variants', [ProductVariantController::class, 'store'])->name('product-variants.store');
        Route::put('product-variants/{variant}', [ProductVariantController::class, 'update'])->name('product-variants.update');
        Route::post('product-variants/{variant}/opening-stock', [ProductVariantController::class, 'openingStock'])->name('product-variants.opening-stock');
        Route::delete('product-variants/{variant}', [ProductVariantController::class, 'destroy'])->name('product-variants.destroy');

        // Parts
        Route::get('parts', [PartController::class, 'index'])->name('parts.index');
        Route::post('parts', [PartController::class, 'store'])->name('parts.store');
        Route::put('parts/{part}', [PartController::class, 'update'])->name('parts.update');
        Route::delete('parts/{part}', [PartController::class, 'destroy'])->name('parts.destroy');

        // Staff designations + staff
        Route::get('designations', [DesignationController::class, 'index'])->name('designations.index');
        Route::post('designations', [DesignationController::class, 'store'])->name('designations.store');
        Route::put('designations/{designation}', [DesignationController::class, 'update'])->name('designations.update');
        Route::delete('designations/{designation}', [DesignationController::class, 'destroy'])->name('designations.destroy');

        Route::get('staff', [StaffController::class, 'index'])->name('staff.index');
        Route::post('staff', [StaffController::class, 'store'])->name('staff.store');
        Route::put('staff/{staff}', [StaffController::class, 'update'])->name('staff.update');
        Route::delete('staff/{staff}', [StaffController::class, 'destroy'])->name('staff.destroy');

        // Suppliers
        Route::get('suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
        Route::post('suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
        Route::put('suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
        Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');

        // Customers
        Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::put('customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::delete('customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');
    });

/*
 | Business settings - production/business rules used by operations.
 */
Route::middleware(['auth', 'role:admin|production_manager'])
    ->prefix('business-settings')
    ->name('business-settings.')
    ->group(function () {
        Route::get('general', [GeneralSettingController::class, 'edit'])->name('general.edit');
        Route::put('general', [GeneralSettingController::class, 'update'])->name('general.update');

        Route::get('unit-of-measures', [UnitOfMeasureController::class, 'index'])->name('unit-of-measures.index');
        Route::post('unit-of-measures', [UnitOfMeasureController::class, 'store'])->name('unit-of-measures.store');
        Route::put('unit-of-measures/{unitOfMeasure}', [UnitOfMeasureController::class, 'update'])->name('unit-of-measures.update');
        Route::delete('unit-of-measures/{unitOfMeasure}', [UnitOfMeasureController::class, 'destroy'])->name('unit-of-measures.destroy');

        Route::get('payment-methods', [PaymentMethodController::class, 'index'])->name('payment-methods.index');
        Route::post('payment-methods', [PaymentMethodController::class, 'store'])->name('payment-methods.store');
        Route::put('payment-methods/{paymentMethod}', [PaymentMethodController::class, 'update'])->name('payment-methods.update');
        Route::delete('payment-methods/{paymentMethod}', [PaymentMethodController::class, 'destroy'])->name('payment-methods.destroy');

        Route::get('expense-categories', [ExpenseCategoryController::class, 'index'])->name('expense-categories.index');
        Route::post('expense-categories', [ExpenseCategoryController::class, 'store'])->name('expense-categories.store');
        Route::put('expense-categories/{expenseCategory}', [ExpenseCategoryController::class, 'update'])->name('expense-categories.update');
        Route::delete('expense-categories/{expenseCategory}', [ExpenseCategoryController::class, 'destroy'])->name('expense-categories.destroy');

        Route::get('system-users', [SystemUserController::class, 'index'])->middleware('role:admin')->name('system-users.index');
        Route::post('system-users', [SystemUserController::class, 'store'])->middleware('role:admin')->name('system-users.store');
        Route::put('system-users/{user}', [SystemUserController::class, 'update'])->middleware('role:admin')->name('system-users.update');
        Route::delete('system-users/{user}', [SystemUserController::class, 'destroy'])->middleware('role:admin')->name('system-users.destroy');

        Route::get('production-stages', [ProductionStageController::class, 'index'])->name('production-stages.index');
        Route::post('production-stages', [ProductionStageController::class, 'store'])->name('production-stages.store');
        Route::put('production-stages/{productionStage}', [ProductionStageController::class, 'update'])->name('production-stages.update');
        Route::delete('production-stages/{productionStage}', [ProductionStageController::class, 'destroy'])->name('production-stages.destroy');

        Route::get('part-conversion-rules', [PartConversionRuleController::class, 'index'])->name('part-conversion-rules.index');
        Route::post('part-conversion-rules', [PartConversionRuleController::class, 'store'])->name('part-conversion-rules.store');
        Route::put('part-conversion-rules/{partConversionRule}', [PartConversionRuleController::class, 'update'])->name('part-conversion-rules.update');
        Route::delete('part-conversion-rules/{partConversionRule}', [PartConversionRuleController::class, 'destroy'])->name('part-conversion-rules.destroy');

        Route::get('cutting-yield-rules', [CuttingYieldRuleController::class, 'index'])->name('cutting-yield-rules.index');
        Route::post('cutting-yield-rules', [CuttingYieldRuleController::class, 'store'])->name('cutting-yield-rules.store');
        Route::put('cutting-yield-rules/{cuttingYieldRule}', [CuttingYieldRuleController::class, 'update'])->name('cutting-yield-rules.update');
        Route::delete('cutting-yield-rules/{cuttingYieldRule}', [CuttingYieldRuleController::class, 'destroy'])->name('cutting-yield-rules.destroy');
    });

/*
 | Recipes (BOM) — per product variant. Defined by admin / production manager.
 */
Route::middleware(['auth', 'role:admin|production_manager'])
    ->prefix('recipes')
    ->name('recipes.')
    ->group(function () {
        Route::get('variants/{variant}', [RecipeController::class, 'edit'])->name('edit');

        Route::post('variants/{variant}/materials', [RecipeController::class, 'storeMaterial'])->name('materials.store');
        Route::put('materials/{material}', [RecipeController::class, 'updateMaterial'])->name('materials.update');
        Route::delete('materials/{material}', [RecipeController::class, 'destroyMaterial'])->name('materials.destroy');

        Route::post('variants/{variant}/parts', [RecipeController::class, 'storePart'])->name('parts.store');
        Route::put('parts/{part}', [RecipeController::class, 'updatePart'])->name('parts.update');
        Route::delete('parts/{part}', [RecipeController::class, 'destroyPart'])->name('parts.destroy');
    });

/*
 | Purchasing & Stock — admin / stock manager.
 */
Route::middleware(['auth', 'role:admin|stock_manager'])->group(function () {
    Route::get('purchases', [PurchaseController::class, 'index'])->name('purchases.index');
    Route::get('purchases/create', [PurchaseController::class, 'create'])->name('purchases.create');
    Route::post('purchases', [PurchaseController::class, 'store'])->name('purchases.store');
    Route::put('purchases/{purchase}', [PurchaseController::class, 'update'])->name('purchases.update');
    Route::patch('purchases/{purchase}/status', [PurchaseController::class, 'updateStatus'])->name('purchases.status.update');
    Route::get('purchases/{purchase}', [PurchaseController::class, 'show'])->name('purchases.show');
    Route::post('purchases/{purchase}/payments', [PurchasePaymentController::class, 'store'])->name('purchases.payments.store');
    Route::get('purchases/{purchase}/returns/create', [PurchaseReturnController::class, 'create'])->name('purchase-returns.create');
    Route::post('purchases/{purchase}/returns', [PurchaseReturnController::class, 'store'])->name('purchase-returns.store');
    Route::get('purchase-returns', [PurchaseReturnController::class, 'index'])->name('purchase-returns.index');
    Route::get('purchase-returns/{purchaseReturn}', [PurchaseReturnController::class, 'show'])->name('purchase-returns.show');

    Route::get('stock', [StockController::class, 'index'])->name('stock.index');
    Route::post('stock/adjust', [StockController::class, 'adjust'])->name('stock.adjust');
    Route::get('stock/movements', [StockController::class, 'movements'])->name('stock.movements');
    Route::get('part-stock', [PartStockController::class, 'index'])->name('part-stock.index');
    Route::post('part-stock/opening', [PartStockController::class, 'opening'])->name('part-stock.opening');
    Route::post('part-stock/adjust', [PartStockController::class, 'adjust'])->name('part-stock.adjust');
    Route::get('part-stock/movements', [PartStockController::class, 'movements'])->name('part-stock.movements');
});

/*
 | Production — admin / production manager.
 */
Route::middleware(['auth', 'role:admin|production_manager'])->group(function () {
    Route::get('cutting-batches', [CuttingBatchController::class, 'index'])->name('cutting-batches.index');
    Route::post('cutting-batches', [CuttingBatchController::class, 'store'])->name('cutting-batches.store');
    Route::put('cutting-batches/{cuttingBatch}', [CuttingBatchController::class, 'update'])->name('cutting-batches.update');
    Route::delete('cutting-batches/{cuttingBatch}', [CuttingBatchController::class, 'destroy'])->name('cutting-batches.destroy');
    Route::post('recovery-cuttings', [CuttingBatchController::class, 'recover'])->name('recovery-cuttings.store');
    Route::put('recovery-cuttings/{recoveryCutting}', [CuttingBatchController::class, 'updateRecovery'])->name('recovery-cuttings.update');
    Route::delete('recovery-cuttings/{recoveryCutting}', [CuttingBatchController::class, 'destroyRecovery'])->name('recovery-cuttings.destroy');

    Route::get('work-orders', [WorkOrderController::class, 'index'])->name('work-orders.index');
    Route::get('work-orders/create', [WorkOrderController::class, 'create'])->name('work-orders.create');
    Route::post('work-orders', [WorkOrderController::class, 'store'])->name('work-orders.store');
    Route::get('work-orders/{workOrder}', [WorkOrderController::class, 'show'])->name('work-orders.show');
    Route::put('work-orders/{workOrder}', [WorkOrderController::class, 'update'])->name('work-orders.update');
    Route::delete('work-orders/{workOrder}', [WorkOrderController::class, 'destroy'])->name('work-orders.destroy');
    Route::post('work-orders/{workOrder}/release', [WorkOrderController::class, 'release'])->name('work-orders.release');
    Route::post('work-orders/{workOrder}/complete', [WorkOrderController::class, 'complete'])->name('work-orders.complete');

    Route::post('work-orders/{workOrder}/job-cards', [JobCardController::class, 'store'])->name('job-cards.store');
    Route::put('job-cards/{jobCard}', [JobCardController::class, 'update'])->name('job-cards.update');
    Route::post('job-cards/{jobCard}/complete', [JobCardController::class, 'complete'])->name('job-cards.complete');
    Route::post('job-cards/{jobCard}/payments', [JobCardController::class, 'storePayment'])->name('job-cards.payments.store');
    Route::put('job-card-payments/{payment}', [JobCardController::class, 'updatePayment'])->name('job-card-payments.update');
    Route::delete('job-card-payments/{payment}', [JobCardController::class, 'destroyPayment'])->name('job-card-payments.destroy');
    Route::post('job-cards/{jobCard}/part-movements', [JobCardController::class, 'storePartMovement'])->name('job-cards.part-movements.store');
    Route::delete('job-cards/{jobCard}', [JobCardController::class, 'destroy'])->name('job-cards.destroy');

    Route::get('piece-rates', [PieceRateController::class, 'index'])->name('piece-rates.index');
    Route::post('piece-rates', [PieceRateController::class, 'store'])->name('piece-rates.store');
    Route::put('piece-rates/{pieceRate}', [PieceRateController::class, 'update'])->name('piece-rates.update');
    Route::delete('piece-rates/{pieceRate}', [PieceRateController::class, 'destroy'])->name('piece-rates.destroy');

    Route::get('wages', [WageController::class, 'index'])->name('wages.index');
});

/*
 | Finished goods & deliveries — admin / production manager.
 */
Route::middleware(['auth', 'role:admin|production_manager|cashier'])->group(function () {
    Route::get('sales', [SaleController::class, 'index'])->name('sales.index');
    Route::get('sales/pos', [SaleController::class, 'pos'])->name('sales.pos');
    Route::get('sales/pos-sessions', [SaleController::class, 'posSessions'])->name('sales.pos-sessions');
    Route::post('sales/pos/open', [SaleController::class, 'openPosSession'])->name('sales.pos.open');
    Route::post('sales/pos/{posSession}/close', [SaleController::class, 'closePosSession'])->name('sales.pos.close');
    Route::post('sales', [SaleController::class, 'store'])->name('sales.store');
    Route::get('sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
    Route::get('sales/{sale}/print', [SaleController::class, 'print'])->name('sales.print');
    Route::get('sales/{sale}/receipt', [SaleController::class, 'receipt'])->name('sales.receipt');
    Route::post('sales/{sale}/payments', [SaleController::class, 'payment'])->name('sales.payments.store');
    Route::put('sale-payments/{payment}', [SaleController::class, 'updatePayment'])->name('sale-payments.update');
    Route::delete('sale-payments/{payment}', [SaleController::class, 'destroyPayment'])->name('sale-payments.destroy');
    Route::get('sales/{sale}/returns/create', [SaleReturnController::class, 'create'])->name('sale-returns.create');
    Route::post('sales/{sale}/returns', [SaleReturnController::class, 'store'])->name('sale-returns.store');
    Route::get('sale-returns', [SaleReturnController::class, 'index'])->name('sale-returns.index');
    Route::post('sales/{sale}/void', [SaleController::class, 'void'])->name('sales.void');
});

Route::middleware(['auth', 'role:admin|production_manager'])->group(function () {
    Route::get('finished-goods', [FinishedGoodController::class, 'index'])->name('finished-goods.index');
    Route::post('finished-goods/opening', [FinishedGoodController::class, 'opening'])->name('finished-goods.opening');
    Route::post('finished-goods/adjust', [FinishedGoodController::class, 'adjust'])->name('finished-goods.adjust');
    Route::get('finished-goods/movements', [FinishedGoodController::class, 'movements'])->name('finished-goods.movements');

    Route::get('deliveries', [DeliveryController::class, 'index'])->name('deliveries.index');
    Route::get('deliveries/create', [DeliveryController::class, 'create'])->name('deliveries.create');
    Route::post('deliveries', [DeliveryController::class, 'store'])->name('deliveries.store');
    Route::post('deliveries/{delivery}/delivered', [DeliveryController::class, 'markDelivered'])->name('deliveries.delivered');

    Route::get('costing', [CostingController::class, 'index'])->name('costing.index');
});

Route::middleware(['auth', 'role:admin|stock_manager|production_manager|accountant'])->group(function () {
    Route::get('accounting', [AccountingController::class, 'index'])->name('accounting.index');
    Route::get('accounting/daily-profit-loss', [AccountingController::class, 'dailyProfitLossPage'])->name('accounting.daily-profit-loss');
    Route::get('accounting/customer-due-invoices', [AccountingController::class, 'customerDueInvoices'])->name('accounting.customer-due-invoices');
    Route::get('accounting/supplier-payables', [AccountingController::class, 'supplierPayables'])->name('accounting.supplier-payables');
    Route::get('accounting/wage-balances', [AccountingController::class, 'wageBalances'])->name('accounting.wage-balances');
    Route::get('accounting/money-movement', [AccountingController::class, 'moneyMovement'])->name('accounting.money-movement');
    Route::get('accounting/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::post('accounting/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
    Route::put('accounting/expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
    Route::delete('accounting/expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/production-flow', [ReportController::class, 'productionFlowPage'])->name('reports.production-flow');
    Route::get('reports/part-flow', [ReportController::class, 'partFlowPage'])->name('reports.part-flow');
    Route::get('reports/finished-good-flow', [ReportController::class, 'finishedGoodFlowPage'])->name('reports.finished-good-flow');
    Route::get('reports/sales-by-product', [ReportController::class, 'salesByProductPage'])->name('reports.sales-by-product');
    Route::get('reports/work-order-status', [ReportController::class, 'workOrderStatusPage'])->name('reports.work-order-status');
    Route::get('reports/stock-alerts', [ReportController::class, 'stockAlertsPage'])->name('reports.stock-alerts');
});

require __DIR__.'/auth.php';
