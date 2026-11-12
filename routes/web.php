<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\ItemImportController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReportCsvController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StockAdjustmentController;
use App\Http\Controllers\StockDocumentController;
use App\Http\Controllers\StockInController;
use App\Http\Controllers\StockOutController;
use App\Http\Controllers\SupplierController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

Route::get('/items/import', [ItemImportController::class, 'create'])->name('items.import.create');
Route::get('/items/import/template', [ItemImportController::class, 'template'])->name('items.import.template');
Route::post('/items/import/preview', [ItemImportController::class, 'preview'])->name('items.import.preview');
Route::post('/items/import', [ItemImportController::class, 'store'])->name('items.import.store');

Route::resource('items', ItemController::class);

Route::get('/stock/in', [StockInController::class, 'create'])->name('stock.in.create');
Route::post('/stock/in', [StockInController::class, 'store'])->name('stock.in.store');

Route::get('/stock/out', [StockOutController::class, 'create'])->name('stock.out.create');
Route::post('/stock/out', [StockOutController::class, 'store'])->name('stock.out.store');

Route::get('/stock/adjustments', [StockAdjustmentController::class, 'create'])->name('stock.adjustments.create');
Route::post('/stock/adjustments', [StockAdjustmentController::class, 'store'])->name('stock.adjustments.store');

Route::get('/stock/documents', [StockDocumentController::class, 'index'])->name('stock.documents.index');
Route::get('/stock/documents/{stockDocument}', [StockDocumentController::class, 'show'])->name('stock.documents.show');
Route::post('/stock/documents/{stockDocument}/reverse', [StockDocumentController::class, 'reverse'])->name('stock.documents.reverse');

Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');
Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');

Route::get('/reports/audit', [AuditLogController::class, 'index'])->name('reports.audit');
Route::get('/reports/stock', [ReportController::class, 'stock'])->name('reports.stock');
Route::get('/reports/stock-card/{item}', [ReportController::class, 'stockCard'])
    ->where('item', '[0-9]+')
    ->name('reports.stock-card');
Route::get('/reports/sales', [ReportController::class, 'sales'])->name('reports.sales');
Route::get('/reports/alerts', [ReportController::class, 'alerts'])->name('reports.alerts');

Route::get('/reports/stock.csv', [ReportCsvController::class, 'stock'])->name('reports.stock.csv');
Route::get('/reports/stock-card/{item}.csv', [ReportCsvController::class, 'stockCard'])
    ->where('item', '[0-9]+')
    ->name('reports.stock-card.csv');
Route::get('/reports/sales.csv', [ReportCsvController::class, 'sales'])->name('reports.sales.csv');
Route::get('/reports/alerts.csv', [ReportCsvController::class, 'alerts'])->name('reports.alerts.csv');

Route::resource('suppliers', SupplierController::class)->except('show');
Route::resource('customers', CustomerController::class);

Route::get('/purchase-orders', [PurchaseOrderController::class, 'index'])->name('purchase-orders.index');
Route::get('/purchase-orders/create', [PurchaseOrderController::class, 'create'])->name('purchase-orders.create');
Route::post('/purchase-orders', [PurchaseOrderController::class, 'store'])->name('purchase-orders.store');
Route::get('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->name('purchase-orders.show');
Route::get('/purchase-orders/{purchaseOrder}/edit', [PurchaseOrderController::class, 'edit'])->name('purchase-orders.edit');
Route::put('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'update'])->name('purchase-orders.update');
Route::post('/purchase-orders/{purchaseOrder}/order', [PurchaseOrderController::class, 'markOrdered'])->name('purchase-orders.order');
Route::get('/purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receiveForm'])->name('purchase-orders.receive.form');
Route::post('/purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive'])->name('purchase-orders.receive.store');
Route::post('/purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])->name('purchase-orders.cancel');
