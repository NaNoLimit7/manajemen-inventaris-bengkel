<?php

use App\Http\Controllers\ItemController;
use App\Http\Controllers\ItemImportController;
use App\Http\Controllers\StockDocumentController;
use App\Http\Controllers\StockInController;
use App\Http\Controllers\StockOutController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Dashboard');
})->name('dashboard');

Route::get('/items/import', [ItemImportController::class, 'create'])->name('items.import.create');
Route::get('/items/import/template', [ItemImportController::class, 'template'])->name('items.import.template');
Route::post('/items/import/preview', [ItemImportController::class, 'preview'])->name('items.import.preview');
Route::post('/items/import', [ItemImportController::class, 'store'])->name('items.import.store');

Route::resource('items', ItemController::class);

Route::get('/stock/in', [StockInController::class, 'create'])->name('stock.in.create');
Route::post('/stock/in', [StockInController::class, 'store'])->name('stock.in.store');

Route::get('/stock/out', [StockOutController::class, 'create'])->name('stock.out.create');
Route::post('/stock/out', [StockOutController::class, 'store'])->name('stock.out.store');

Route::get('/stock/documents', [StockDocumentController::class, 'index'])->name('stock.documents.index');
Route::get('/stock/documents/{stockDocument}', [StockDocumentController::class, 'show'])->name('stock.documents.show');
