<?php

use App\Http\Controllers\Admin\CashController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ExpenseController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\PurchaseController;
use App\Http\Controllers\Admin\SaleController;
use App\Http\Controllers\Admin\StockItemController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::resource('products', ProductController::class)->except('show');
    Route::post('purchases/bulk-status', [PurchaseController::class, 'bulkStatus'])->name('purchases.bulk-status');
    Route::resource('purchases', PurchaseController::class)->except('show');
    Route::resource('stock', StockItemController::class)->only('index', 'store', 'update', 'destroy');
    Route::post('stock/bulk-status', [StockItemController::class, 'bulkStatus'])->name('stock.bulk-status');
    Route::post('stock/{stock}/sell', [StockItemController::class, 'sell'])->name('stock.sell');
    Route::resource('orders', OrderController::class)->only('index', 'store', 'update', 'destroy');
    Route::patch('orders/{order}/toggle', [OrderController::class, 'toggle'])->name('orders.toggle');
    Route::resource('sales', SaleController::class)->only('index', 'store', 'update', 'destroy');
    Route::resource('expenses', ExpenseController::class)->only('index', 'store', 'update', 'destroy');
    Route::resource('cash', CashController::class)->only('index', 'store', 'update', 'destroy');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
