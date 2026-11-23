<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    public const CHANNELS = ['instagram' => 'Instagram', 'facebook' => 'Facebook', 'phone' => 'Телефон', 'other' => 'Другое'];

    protected $fillable = [
        'items', 'customer_name', 'channel', 'address', 'phone', 'price', 'ordered_at', 'is_done', 'notes',
    ];

    protected $casts = [
        'ordered_at' => 'date:Y-m-d',
        'is_done' => 'boolean',
    ];

    /**
     * Заказ выполнен -> создаётся продажа (она сама добавляет деньги в кассу),
     * товары заказа становятся проданными.
     * Снята галочка -> продажа удаляется, деньги уходят из кассы, товары снова в брони.
     * Заказ удалён -> товары возвращаются на склад.
     */
    protected static function booted(): void
    {
        static::saved(fn (Order $order) => $order->syncSale());
        static::deleting(function (Order $order) {
            $order->sale?->delete();
            $order->stockItems()->update(['order_id' => null, 'status' => 'in_stock', 'sold_at' => null, 'reserved_for' => null]);
        });
    }

    public function sale(): HasOne
    {
        return $this->hasOne(Sale::class);
    }

    public function stockItems(): HasMany
    {
        return $this->hasMany(StockItem::class);
    }

    public function syncSale(): void
    {
        $justDone = $this->is_done && ($this->wasRecentlyCreated || $this->wasChanged('is_done'));
        $sale = $this->sale()->first();

        // Себестоимость заказа = сумма себестоимости его товаров (если она известна)
        $items = $this->stockItems()->get();
        $cost = $items->whereNotNull('buy_price')->isEmpty() ? null : (int) $items->sum('buy_price');

        if (! $this->is_done) {
            $sale?->delete();
        } elseif ($sale) {
            $sale->update([
                'product' => $this->items,
                'price' => $this->price,
                'cost' => $cost,
                'customer_name' => $this->customer_name,
            ]);
        } elseif ($justDone) {
            $this->sale()->create([
                'product' => $this->items,
                'price' => $this->price,
                'cost' => $cost,
                'sold_at' => now()->toDateString(),
                'customer_name' => $this->customer_name,
                'note' => 'Заказ клиента',
            ]);
        }

        $this->applyItemStates();
    }

    /** Выполнен -> товары проданы; не выполнен -> в брони за клиентом */
    public function applyItemStates(): void
    {
        if ($this->is_done) {
            foreach ($this->stockItems()->get() as $item) {
                $item->update(['status' => 'sold', 'reserved_for' => null, 'sold_at' => $item->sold_at ?? now()->toDateString()]);
            }
        } else {
            $this->stockItems()->update(['status' => 'reserved', 'sold_at' => null, 'reserved_for' => $this->customer_name]);
        }
    }
}
