<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\StockItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class OrderController extends Controller
{
    use Concerns;

    /** Какие товары склада можно добавить в заказ */
    private const PICKABLE = ['in_stock', 'processing', 'reserved'];

    public function index(Request $request)
    {
        $status = $request->input('status', 'open');

        return Inertia::render('Orders/Index', [
            'orders' => Order::query()
                ->when($status === 'open', fn ($q) => $q->where('is_done', false))
                ->when($status === 'done', fn ($q) => $q->where('is_done', true))
                ->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q
                    ->where('customer_name', 'like', $this->like($s))
                    ->orWhere('items', 'like', $this->like($s))
                    ->orWhere('phone', 'like', $this->like($s))))
                ->with(['sale:id,order_id,sold_at', 'stockItems.product:id,name,gender', 'stockItems.product.images'])
                ->latest('ordered_at')->latest('id')
                ->paginate(50)->withQueryString(),
            // Свободные товары склада для выбора в заказ
            'stock' => StockItem::query()
                ->with(['product:id,name,gender', 'product.images'])
                ->whereNull('order_id')
                ->whereIn('status', self::PICKABLE)
                ->orderBy('product_id')->orderBy('size')
                ->get(['id', 'product_id', 'size', 'color', 'sell_price', 'buy_price', 'status', 'reserved_for']),
            'filters' => ['status' => $status, 'search' => $request->search],
            'channels' => Order::CHANNELS,
        ]);
    }

    public function store(Request $request)
    {
        [$data, $ids] = $this->validated($request);

        DB::transaction(function () use ($data, $ids) {
            $order = Order::create($data);
            $this->syncItems($order, $ids);
        });

        return back()->with('success', 'Заказ добавлен');
    }

    public function update(Request $request, Order $order)
    {
        [$data, $ids] = $this->validated($request, $order);

        DB::transaction(function () use ($order, $data, $ids) {
            $order->update($data);
            $this->syncItems($order, $ids);
        });

        return back()->with('success', 'Заказ сохранён');
    }

    public function toggle(Order $order)
    {
        $hadSale = $order->sale()->exists();
        $order->update(['is_done' => ! $order->is_done]);

        $money = number_format($order->price, 0, '', ' ');

        return back()->with('success', match (true) {
            $order->is_done => "Заказ выполнен: +{$money} ֏ в кассу",
            $hadSale => "Заказ снова открыт: −{$money} ֏ из кассы",
            default => 'Заказ снова открыт',
        });
    }

    public function destroy(Order $order)
    {
        $order->delete();

        return back()->with('success', 'Заказ удалён, товары вернулись на склад');
    }

    /** Привязать к заказу ровно эти товары: лишние вернуть на склад, новые забронировать */
    private function syncItems(Order $order, array $ids): void
    {
        $order->stockItems()->whereNotIn('id', $ids)
            ->update(['order_id' => null, 'status' => 'in_stock', 'sold_at' => null, 'reserved_for' => null]);

        if ($ids) {
            StockItem::whereIn('id', $ids)->update(['order_id' => $order->id]);
        }

        $order->syncSale();
    }

    /** @return array{0: array, 1: int[]} */
    private function validated(Request $request, ?Order $order = null): array
    {
        $data = $request->validate([
            'items' => 'required|string|max:255',
            'customer_name' => 'required|string|max:255',
            'channel' => 'nullable|in:'.implode(',', array_keys(Order::CHANNELS)),
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'price' => 'required|integer|min:0',
            'ordered_at' => 'nullable|date',
            'is_done' => 'boolean',
            'notes' => 'nullable|string',
            'stock_item_ids' => 'nullable|array',
            'stock_item_ids.*' => 'integer|exists:stock_items,id',
        ]);

        $ids = array_values(array_unique(array_map('intval', $data['stock_item_ids'] ?? [])));
        unset($data['stock_item_ids']);

        if ($ids) {
            $available = StockItem::whereIn('id', $ids)
                ->where(function ($q) use ($order) {
                    $q->where(fn ($q) => $q->whereNull('order_id')->whereIn('status', self::PICKABLE));
                    if ($order) {
                        $q->orWhere('order_id', $order->id);
                    }
                })
                ->count();

            if ($available !== count($ids)) {
                throw ValidationException::withMessages([
                    'stock_item_ids' => 'Часть товаров уже недоступна (продана или в другом заказе). Обновите страницу.',
                ]);
            }
        }

        return [$data, $ids];
    }
}
