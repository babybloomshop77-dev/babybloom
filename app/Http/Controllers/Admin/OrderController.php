<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->input('status', 'open');

        return Inertia::render('Orders/Index', [
            'orders' => Order::query()
                ->when($status === 'open', fn ($q) => $q->where('is_done', false))
                ->when($status === 'done', fn ($q) => $q->where('is_done', true))
                ->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q
                    ->where('customer_name', 'like', "%{$s}%")
                    ->orWhere('items', 'like', "%{$s}%")
                    ->orWhere('phone', 'like', "%{$s}%")))
                ->with('sale:id,order_id,sold_at')
                ->latest('ordered_at')->latest('id')
                ->paginate(50)->withQueryString(),
            'filters' => ['status' => $status, 'search' => $request->search],
            'channels' => Order::CHANNELS,
        ]);
    }

    public function store(Request $request)
    {
        Order::create($this->validated($request));

        return back()->with('success', 'Заказ добавлен');
    }

    public function update(Request $request, Order $order)
    {
        $order->update($this->validated($request));

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

        return back()->with('success', 'Заказ удалён');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'items' => 'required|string|max:255',
            'customer_name' => 'required|string|max:255',
            'channel' => 'nullable|in:'.implode(',', array_keys(Order::CHANNELS)),
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'price' => 'required|integer|min:0',
            'ordered_at' => 'nullable|date',
            'is_done' => 'boolean',
            'notes' => 'nullable|string',
        ]);
    }
}
