<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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
     * Заказ выполнен -> создаётся продажа (а она сама добавляет деньги в кассу).
     * Снята галочка или заказ удалён -> продажа удаляется, деньги уходят из кассы.
     */
    protected static function booted(): void
    {
        static::saved(fn (Order $order) => $order->syncSale());
        static::deleting(fn (Order $order) => $order->sale?->delete());
    }

    public function sale(): HasOne
    {
        return $this->hasOne(Sale::class);
    }

    public function syncSale(): void
    {
        $justDone = $this->is_done && ($this->wasRecentlyCreated || $this->wasChanged('is_done'));
        $sale = $this->sale()->first();

        if (! $this->is_done) {
            $sale?->delete();
        } elseif ($sale) {
            $sale->update([
                'product' => $this->items,
                'price' => $this->price,
                'customer_name' => $this->customer_name,
            ]);
        } elseif ($justDone) {
            $this->sale()->create([
                'product' => $this->items,
                'price' => $this->price,
                'sold_at' => now()->toDateString(),
                'customer_name' => $this->customer_name,
                'note' => 'Заказ клиента',
            ]);
        }
    }
}
