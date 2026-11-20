<?php

namespace App\Models;

use App\Models\Concerns\SyncsCashMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    use SyncsCashMovement;

    public const STATUSES = ['ordered' => 'В пути', 'delivered' => 'Доставлено'];

    protected $fillable = [
        'product_id', 'title', 'source', 'status', 'url', 'quantity', 'price_amd', 'price_yuan', 'yuan_rate',
        'shipping_cost', 'goods_paid_before', 'shipping_paid_at', 'cash_tracked', 'purchased_at', 'delivered_at', 'notes',
    ];

    protected $casts = [
        'purchased_at' => 'date:Y-m-d',
        'delivered_at' => 'date:Y-m-d',
        'shipping_paid_at' => 'date:Y-m-d',
        'goods_paid_before' => 'boolean',
        'cash_tracked' => 'boolean',
        'price_yuan' => 'float',
        'yuan_rate' => 'float',
    ];

    protected $appends = ['total', 'unit_cost', 'cash_total'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function stockItems(): HasMany
    {
        return $this->hasMany(StockItem::class);
    }

    /** В кассе закупка = товар (если не оплачен раньше) + доставка (в день её оплаты) */
    public function cashMovementParts(): array
    {
        return [
            'goods' => $this->goods_paid_before ? null : [
                'type' => 'out',
                'amount' => (int) $this->price_amd,
                'title' => "{$this->title} — товар ({$this->quantity} шт.)",
                'category' => 'Закупка товара',
                'moved_at' => $this->purchased_at ?? now(),
                'note' => null,
            ],
            'shipping' => [
                'type' => 'out',
                'amount' => (int) $this->shipping_cost,
                'title' => "{$this->title} — доставка",
                'category' => 'Доставка',
                'moved_at' => $this->shipping_paid_at ?? $this->purchased_at ?? now(),
                'note' => null,
            ],
        ];
    }

    public function cashTracksUpdates(): bool
    {
        return (bool) $this->cash_tracked;
    }

    /** Сколько эта закупка списывает из кассы */
    public function getCashTotalAttribute(): int
    {
        return ($this->goods_paid_before ? 0 : (int) $this->price_amd) + (int) $this->shipping_cost;
    }

    public function getTotalAttribute(): int
    {
        return (int) $this->price_amd + (int) $this->shipping_cost;
    }

    public function getUnitCostAttribute(): int
    {
        return (int) round($this->total / max(1, (int) $this->quantity));
    }

    /** Доля доставки на 1 штуку */
    public function shippingShare(): int
    {
        $pieces = $this->stockItems()->whereNotNull('purchase_item_id')->count();

        return $pieces ? (int) round($this->shipping_cost / $pieces) : 0;
    }

    /**
     * Пересчитать итоги закупки и себестоимость каждой штуки:
     * себестоимость = цена строки + доставка / кол-во штук.
     */
    public function recalculate(): void
    {
        if (! $this->items()->exists()) {
            return; // старая закупка без строк — считаем вручную, как раньше
        }

        $share = $this->shippingShare();
        $pieces = $this->stockItems()->whereNotNull('purchase_item_id')->with('purchaseItem', 'sale')->get();

        foreach ($pieces as $piece) {
            $cost = $piece->purchaseItem->price_amd + $share;
            if ((int) $piece->buy_price !== $cost) {
                $piece->update(['buy_price' => $cost]);
            }
            if ($piece->sale && (int) $piece->sale->cost !== $cost) {
                $piece->sale->updateQuietly(['cost' => $cost]);
            }
        }

        foreach ($this->items()->with('product')->get() as $item) {
            $item->product->update([
                'buy_price_amd' => $item->price_amd,
                'buy_price_yuan' => $item->price_yuan,
                'shipping_cost' => $share,
            ]);
        }

        $this->quantity = $pieces->count();
        $this->price_amd = (int) $pieces->sum(fn ($p) => $p->purchaseItem->price_amd);
        $this->price_yuan = round($pieces->sum(fn ($p) => (float) $p->purchaseItem->price_yuan), 2) ?: null;
        $this->save();
    }

    /** Сменить статус (В пути / Доставлено) вместе со всеми штуками на складе */
    public function setStatus(string $status, ?string $date = null): void
    {
        $date ??= now()->toDateString();

        if ($status === 'delivered') {
            $this->update(['status' => 'delivered', 'delivered_at' => $this->delivered_at ?? $date]);
            $this->stockItems()->where('status', 'ordered')->update(['status' => 'in_stock', 'arrived_at' => $date]);
        } else {
            $this->update(['status' => 'ordered', 'delivered_at' => null]);
            $this->stockItems()->where('status', 'in_stock')->update(['status' => 'ordered', 'arrived_at' => null]);
        }
    }
}
