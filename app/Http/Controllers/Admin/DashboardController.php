<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CashMovement;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\StockItem;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    use Concerns;

    public function __invoke(Request $request)
    {
        [$month, $start, $end] = $this->monthRange($request);

        $sales = Sale::query()->when($start, fn ($q) => $q->whereBetween('sold_at', [$start, $end]));
        $expenses = Expense::query()->when($start, fn ($q) => $q->whereBetween('spent_at', [$start, $end]));
        $purchases = Purchase::query()->when($start, fn ($q) => $q->whereBetween('purchased_at', [$start, $end]));

        $revenue = (int) (clone $sales)->sum('price');
        $expensesTotal = (int) (clone $expenses)->sum('amount');
        $purchasesTotal = (int) (clone $purchases)->sum('price_amd') + (int) (clone $purchases)->sum('shipping_cost');

        $inStock = StockItem::whereIn('status', ['in_stock', 'processing', 'reserved']);

        return Inertia::render('Dashboard', [
            'month' => $month,
            'stats' => [
                'cash' => CashMovement::balance(),
                'revenue' => $revenue,
                'sales_count' => (clone $sales)->count(),
                'expenses' => $expensesTotal,
                'purchases' => $purchasesTotal,
                'profit' => $revenue - $expensesTotal - $purchasesTotal,
                'stock_count' => (clone $inStock)->count(),
                'in_transit' => StockItem::where('status', 'ordered')->count(),
                'stock_cost' => (int) (clone $inStock)->sum('buy_price'),
                'stock_value' => (int) (clone $inStock)->sum('sell_price'),
                'open_orders' => Order::where('is_done', false)->count(),
            ],
            'expensesByCategory' => (clone $expenses)->selectRaw('category, SUM(amount) as total')
                ->groupBy('category')->pluck('total', 'category'),
            'recentSales' => (clone $sales)->latest('sold_at')->latest('id')->limit(8)->get(),
            'openOrders' => Order::where('is_done', false)->latest('ordered_at')->limit(8)->get(),
            'categories' => Expense::CATEGORIES,
        ]);
    }
}
