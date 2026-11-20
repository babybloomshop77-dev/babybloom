<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\StockItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class StockItemController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->input('status', 'available');

        $query = StockItem::query()
            ->with('product:id,name', 'product.images')
            ->when($status === 'available', fn ($q) => $q->where('status', '!=', 'sold'))
            ->when($status && $status !== 'available' && $status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($request->product_id, fn ($q, $id) => $q->where('product_id', $id))
            ->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q
                ->where('size', 'like', "%{$s}%")
                ->orWhere('color', 'like', "%{$s}%")
                ->orWhereHas('product', fn ($q) => $q->where('name', 'like', "%{$s}%"))));

        return Inertia::render('Stock/Index', [
            'items' => (clone $query)->orderBy('product_id')->orderBy('size')->paginate(100)->withQueryString(),
            'totals' => [
                'count' => (clone $query)->count(),
                'cost' => (int) (clone $query)->sum('buy_price'),
                'value' => (int) (clone $query)->sum('sell_price'),
            ],
            'filters' => ['status' => $status, 'product_id' => $request->product_id, 'search' => $request->search],
            'products' => Product::orderBy('name')->get(['id', 'name', 'sizes', 'colors', 'sell_price', 'buy_price_amd', 'shipping_cost']),
            'purchases' => Purchase::latest('purchased_at')->limit(100)->get(['id', 'title', 'purchased_at', 'product_id', 'quantity', 'price_amd', 'shipping_cost']),
            'statuses' => StockItem::STATUSES,
        ]);
    }

    /** Добавить сразу несколько единиц: rows = [{size, color, qty}] */
    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'purchase_id' => 'nullable|exists:purchases,id',
            'buy_price' => 'nullable|integer|min:0',
            'sell_price' => 'nullable|integer|min:0',
            'arrived_at' => 'nullable|date',
            'rows' => 'required|array|min:1',
            'rows.*.size' => 'nullable|string|max:255',
            'rows.*.color' => 'nullable|string|max:255',
            'rows.*.qty' => 'required|integer|min:1|max:100',
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['rows'] as $row) {
                for ($i = 0; $i < $row['qty']; $i++) {
                    StockItem::create([
                        'product_id' => $data['product_id'],
                        'purchase_id' => $data['purchase_id'] ?? null,
                        'size' => $row['size'] ?? null,
                        'color' => $row['color'] ?? null,
                        'buy_price' => $data['buy_price'] ?? null,
                        'sell_price' => $data['sell_price'] ?? null,
                        'arrived_at' => $data['arrived_at'] ?? null,
                        'status' => 'in_stock',
                    ]);
                }
            }
        });

        return back()->with('success', 'Товар добавлен на склад');
    }

    public function update(Request $request, StockItem $stock)
    {
        $stock->update($request->validate([
            'product_id' => 'required|exists:products,id',
            'purchase_id' => 'nullable|exists:purchases,id',
            'size' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:255',
            'buy_price' => 'nullable|integer|min:0',
            'sell_price' => 'nullable|integer|min:0',
            'status' => 'required|in:'.implode(',', array_keys(StockItem::STATUSES)),
            'reserved_for' => 'nullable|string|max:255',
            'arrived_at' => 'nullable|date',
            'sold_at' => 'nullable|date',
            'note' => 'nullable|string|max:255',
        ]));

        return back()->with('success', 'Сохранено');
    }

    public function destroy(StockItem $stock)
    {
        $stock->delete();

        return back()->with('success', 'Удалено');
    }

    /** Массовая смена статуса (кроме «Продан» — для продажи есть кнопка «Продать») */
    public function bulkStatus(Request $request)
    {
        $data = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:stock_items,id',
            'status' => 'required|in:ordered,in_stock,processing,reserved',
            'date' => 'nullable|date',
        ]);

        $items = StockItem::whereIn('id', $data['ids'])->where('status', '!=', 'sold')->get();

        DB::transaction(function () use ($items, $data) {
            foreach ($items as $item) {
                $item->update([
                    'status' => $data['status'],
                    'arrived_at' => match (true) {
                        $data['status'] === 'ordered' => null,
                        $item->status === 'ordered' => $data['date'] ?? now()->toDateString(),
                        default => $item->arrived_at,
                    },
                    'reserved_for' => $data['status'] === 'reserved' ? $item->reserved_for : null,
                ]);
            }

            // Если у закупки больше нет штук «В пути» — она доставлена
            Purchase::whereIn('id', $items->pluck('purchase_id')->filter()->unique())->get()
                ->each(function (Purchase $p) use ($data) {
                    $inTransit = $p->stockItems()->where('status', 'ordered')->exists();
                    if (! $inTransit && $p->status === 'ordered') {
                        $p->update(['status' => 'delivered', 'delivered_at' => $data['date'] ?? now()->toDateString()]);
                    } elseif ($inTransit && $p->status === 'delivered' && $data['status'] === 'ordered') {
                        $p->update(['status' => 'ordered', 'delivered_at' => null]);
                    }
                });
        });

        return back()->with('success', $items->count().' шт. → «'.StockItem::STATUSES[$data['status']].'»');
    }

    /** Продать единицу: статус -> sold + запись в продажах */
    public function sell(Request $request, StockItem $stock)
    {
        $data = $request->validate([
            'price' => 'required|integer|min:0',
            'sold_at' => 'required|date',
            'customer_name' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($stock, $data) {
            $stock->update(['status' => 'sold', 'sold_at' => $data['sold_at'], 'reserved_for' => null]);
            $stock->load('product');

            Sale::create([
                'product' => trim($stock->product->name.' '.$stock->size.' '.$stock->color),
                'stock_item_id' => $stock->id,
                'price' => $data['price'],
                'cost' => $stock->buy_price,
                'sold_at' => $data['sold_at'],
                'customer_name' => $data['customer_name'] ?? null,
                'note' => $data['note'] ?? null,
            ]);
        });

        return back()->with('success', 'Продажа записана');
    }
}
