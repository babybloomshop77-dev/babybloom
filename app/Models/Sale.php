<?php

namespace App\Models;

use App\Models\Concerns\SyncsCashMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sale extends Model
{
    use SyncsCashMovement;

    protected $fillable = [
        'product', 'stock_item_id', 'order_id', 'price', 'cost', 'sold_at', 'customer_name', 'note',
    ];

    protected $casts = [
        'sold_at' => 'date:Y-m-d',
    ];

    public function cashMovementParts(): array
    {
        return [
            'main' => [
                'type' => 'in',
                'amount' => (int) $this->price,
                'title' => $this->product.($this->customer_name ? " — {$this->customer_name}" : ''),
                'category' => 'Продажа',
                'moved_at' => $this->sold_at,
                'note' => $this->note,
            ],
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }
}
