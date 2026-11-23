<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SaleController extends Controller
{
    use Concerns;

    public function index(Request $request)
    {
        [$month, $start, $end] = $this->monthRange($request);

        $query = Sale::query()
            ->when($start, fn ($q) => $q->whereBetween('sold_at', [$start, $end]))
            ->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q
                ->where('product', 'like', $this->like($s))->orWhere('customer_name', 'like', $this->like($s))));

        return Inertia::render('Sales/Index', [
            'sales' => (clone $query)->latest('sold_at')->latest('id')->paginate(100)->withQueryString(),
            'totals' => [
                'count' => (clone $query)->count(),
                'revenue' => (int) (clone $query)->sum('price'),
                'cost' => (int) (clone $query)->sum('cost'),
            ],
            'filters' => ['month' => $month, 'search' => $request->search],
        ]);
    }

    public function store(Request $request)
    {
        Sale::create($this->validated($request));

        return back()->with('success', 'Продажа добавлена');
    }

    public function update(Request $request, Sale $sale)
    {
        $sale->update($this->validated($request));

        return back()->with('success', 'Продажа сохранена');
    }

    public function destroy(Sale $sale)
    {
        // Если продажа была со склада — вернуть товар в наличие
        $sale->stockItem?->update(['status' => 'in_stock', 'sold_at' => null]);
        // Если продажа из заказа — заказ снова «не выполнен»
        if ($order = $sale->order) {
            $order->updateQuietly(['is_done' => false]);
            $order->applyItemStates();
        }
        $sale->delete();

        return back()->with('success', 'Продажа удалена');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'product' => 'required|string|max:255',
            'price' => 'nullable|integer|min:0',
            'cost' => 'nullable|integer|min:0',
            'sold_at' => 'required|date',
            'customer_name' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:255',
        ]);
    }
}
