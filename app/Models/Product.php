<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    public const SOURCES = ['pinduoduo' => 'Pinduoduo', 'wholesale' => 'Оптовик', 'other' => 'Другое'];

    protected $fillable = [
        'name', 'description', 'source', 'pinduoduo_url', 'buy_price_yuan', 'buy_price_amd',
        'shipping_cost', 'sell_price', 'sizes', 'colors', 'notes', 'is_active',
    ];

    protected $casts = [
        'sizes' => 'array',
        'colors' => 'array',
        'is_active' => 'boolean',
        'buy_price_yuan' => 'float',
    ];

    protected $appends = ['cost_price'];

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort');
    }

    public function stockItems(): HasMany
    {
        return $this->hasMany(StockItem::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    /** Себестоимость 1 шт = цена + доставка */
    public function getCostPriceAttribute(): ?int
    {
        if ($this->buy_price_amd === null) {
            return null;
        }

        return (int) $this->buy_price_amd + (int) $this->shipping_cost;
    }
}
