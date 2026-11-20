<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StockItem extends Model
{
    public const STATUSES = [
        'ordered' => 'В пути',
        'in_stock' => 'В наличии',
        'processing' => 'В обработке',
        'reserved' => 'Забронирован',
        'sold' => 'Продан',
    ];

    protected $fillable = [
        'product_id', 'purchase_id', 'purchase_item_id', 'size', 'color', 'buy_price', 'sell_price',
        'status', 'reserved_for', 'arrived_at', 'sold_at', 'note',
    ];

    protected $casts = [
        'arrived_at' => 'date:Y-m-d',
        'sold_at' => 'date:Y-m-d',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function purchaseItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseItem::class);
    }

    public function sale(): HasOne
    {
        return $this->hasOne(Sale::class);
    }
}
