<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\StockItem;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PurchaseController extends Controller
{
    use Concerns;

    public function index(Request $request)
    {
        [$month, $start, $end] = $this->monthRange($request->merge(['month' => $request->input('month', 'all')]));

        $query = Purchase::query()
            ->when($start, fn ($q) => $q->whereBetween('purchased_at', [$start, $end]))
            ->when($request->source, fn ($q, $s) => $q->where('source', $s))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->search, fn ($q, $s) => $q->where('title', 'like', "%{$s}%"));

        return Inertia::render('Purchases/Index', [
            'purchases' => (clone $query)
                ->with(['product:id,name', 'items.product:id,name', 'items.product.images'])
                ->withCount(['stockItems', 'stockItems as sold_count' => fn ($q) => $q->where('status', 'sold')])
                ->latest('purchased_at')->latest('id')
                ->paginate(50)->withQueryString(),
            'totals' => [
                'amd' => (int) (clone $query)->sum('price_amd'),
                'shipping' => (int) (clone $query)->sum('shipping_cost'),
                'yuan' => (float) (clone $query)->sum('price_yuan'),
                'in_transit' => (clone $query)->where('status', 'ordered')->count(),
            ],
            'filters' => ['month' => $month, 'source' => $request->source, 'status' => $request->status, 'search' => $request->search],
            'sources' => Product::SOURCES,
            'statuses' => Purchase::STATUSES,
        ]);
    }

    public function create()
    {
        return Inertia::render('Purchases/Form', $this->formProps(null));
    }

    public function edit(Purchase $purchase)
    {
        $purchase->load(['items.product.images', 'items.stockItems:id,purchase_item_id,size,color,status']);

        // Варианты строки: размер/цвет -> сколько штук, сколько продано
        $purchase->items->each(function ($item) {
            $item->variants = $item->stockItems
                ->groupBy(fn ($s) => $s->size.'|'.$s->color)
                ->map(fn ($g) => [
                    'size' => $g->first()->size,
                    'color' => $g->first()->color,
                    'qty' => $g->count(),
                    'sold' => $g->where('status', 'sold')->count(),
                ])->values();
            $item->unsetRelation('stockItems');
        });

        return Inertia::render('Purchases/Form', $this->formProps($purchase));
    }

    private function formProps(?Purchase $purchase): array
    {
        return [
            'purchase' => $purchase,
            'products' => Product::with('images')->orderBy('name')
                ->get(['id', 'name', 'sizes', 'colors', 'sell_price', 'buy_price_amd', 'buy_price_yuan', 'pinduoduo_url']),
            'sources' => Product::SOURCES,
            'statuses' => Purchase::STATUSES,
            'lastRate' => Purchase::whereNotNull('yuan_rate')->latest('id')->value('yuan_rate'),
            'backUrl' => $this->backUrl(route('purchases.index')),
        ];
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $purchase = DB::transaction(function () use ($data, $request) {
            // без событий: движение в кассе создадим после подсчёта итогов
            $purchase = Purchase::withoutEvents(fn () => Purchase::create($this->header($data) + [
                'quantity' => 0, 'price_amd' => 0, 'cash_tracked' => true,
            ]));

            foreach ($data['lines'] ?? [] as $i => $line) {
                $this->saveLine($purchase, $line, $request->file("lines.$i.new_product.images", []));
            }

            if (empty($data['lines'])) {
                $purchase->update($this->legacy($data));
            }

            $purchase->recalculate();
            $purchase->refresh()->syncCashMovement(true);

            return $purchase;
        });

        return redirect($this->safeBack($request->input('back'), route('purchases.index')))
            ->with('success', "Закупка сохранена: {$purchase->quantity} шт.");
    }

    public function update(Request $request, Purchase $purchase)
    {
        $data = $this->validated($request, $purchase);

        DB::transaction(function () use ($data, $request, $purchase) {
            $oldStatus = $purchase->status;
            $purchase->update(collect($this->header($data))->except('status', 'delivered_at')->all());

            foreach ($data['lines'] ?? [] as $i => $line) {
                $this->saveLine($purchase, $line, $request->file("lines.$i.new_product.images", []));
            }

            if (! $purchase->items()->exists()) {
                $purchase->update($this->legacy($data));
            }

            if ($data['status'] !== $oldStatus) {
                $purchase->setStatus($data['status'], $data['delivered_at'] ?? null);
            } elseif ($data['status'] === 'delivered' && ! empty($data['delivered_at'])) {
                $purchase->update(['delivered_at' => $data['delivered_at']]);
            }

            $purchase->recalculate();
        });

        return redirect($this->safeBack($request->input('back'), route('purchases.index')))->with('success', 'Закупка сохранена');
    }

    public function destroy(Purchase $purchase)
    {
        if ($purchase->items()->exists() && $purchase->stockItems()->where('status', 'sold')->exists()) {
            return back()->with('error', 'Нельзя удалить: часть товара уже продана');
        }

        DB::transaction(function () use ($purchase) {
            // штуки из строк закупки удаляются вместе с ней, старые — остаются на складе
            $purchase->stockItems()->whereNotNull('purchase_item_id')->delete();
            $purchase->delete();
        });

        return back()->with('success', 'Закупка удалена');
    }

    /** Массово: В пути / Доставлено */
    public function bulkStatus(Request $request)
    {
        $data = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:purchases,id',
            'status' => ['required', Rule::in(array_keys(Purchase::STATUSES))],
            'date' => 'nullable|date',
        ]);

        DB::transaction(function () use ($data) {
            foreach (Purchase::whereIn('id', $data['ids'])->get() as $purchase) {
                $purchase->setStatus($data['status'], $data['date'] ?? null);
            }
        });

        $label = Purchase::STATUSES[$data['status']];

        return back()->with('success', count($data['ids'])." закуп. → «{$label}»");
    }

    /* ------------------------------------------------------------------ */

    private function header(array $data): array
    {
        $delivered = $data['status'] === 'delivered';

        return [
            'title' => $data['title'],
            'source' => $data['source'],
            'status' => $data['status'],
            'url' => $data['url'] ?? null,
            'yuan_rate' => $data['yuan_rate'] ?? null,
            'shipping_cost' => $shipping = (int) ($data['shipping_cost'] ?? 0),
            'shipping_paid_at' => $shipping > 0 ? ($data['shipping_paid_at'] ?? now()->toDateString()) : null,
            'goods_paid_before' => (bool) ($data['goods_paid_before'] ?? false),
            'purchased_at' => $data['purchased_at'],
            'delivered_at' => $delivered ? ($data['delivered_at'] ?? now()->toDateString()) : null,
            'notes' => $data['notes'] ?? null,
        ];
    }

    /** Старая закупка без строк товаров — суммы вводятся вручную */
    private function legacy(array $data): array
    {
        return [
            'quantity' => (int) ($data['quantity'] ?? 1),
            'price_amd' => (int) ($data['price_amd'] ?? 0),
            'price_yuan' => $data['price_yuan'] ?? null,
        ];
    }

    /** Создать/обновить строку закупки + товар + штуки на складе */
    private function saveLine(Purchase $purchase, array $line, array $images): void
    {
        $item = ! empty($line['id']) ? $purchase->items()->findOrFail($line['id']) : null;

        // Удаление строки
        if ($item && ! empty($line['delete'])) {
            if ($item->stockItems()->where('status', 'sold')->exists()) {
                throw ValidationException::withMessages(['lines' => "«{$item->product->name}»: часть уже продана, удалить нельзя"]);
            }
            $item->stockItems()->delete();
            $item->delete();

            return;
        }
        if (! empty($line['delete'])) {
            return;
        }

        // Товар: существующий или новый
        if ($item) {
            $product = $item->product;
        } elseif (($line['mode'] ?? 'existing') === 'new') {
            $np = $line['new_product'];
            $product = Product::create([
                'name' => $np['name'],
                'description' => $np['description'] ?? null,
                'source' => $purchase->source,
                'pinduoduo_url' => $np['pinduoduo_url'] ?? null,
                'sell_price' => $line['sell_price'] ?? null,
            ]);
        } else {
            $product = Product::findOrFail($line['product_id']);
        }

        $sort = (int) $product->images()->max('sort');
        foreach ($images as $file) {
            if ($file instanceof UploadedFile) {
                ProductImage::create([
                    'product_id' => $product->id,
                    'path' => $file->store("products/{$product->id}", 'public'),
                    'sort' => ++$sort,
                ]);
            }
        }

        $prices = [
            'price_amd' => (int) $line['price_amd'],
            'price_yuan' => $line['price_yuan'] ?? null,
            'sell_price' => $line['sell_price'] ?? null,
        ];

        if ($item) {
            $item->update($prices);
            if ($prices['sell_price'] !== null) {
                $item->stockItems()->where('status', '!=', 'sold')->update(['sell_price' => $prices['sell_price']]);
            }
        } else {
            $item = $purchase->items()->create($prices + ['product_id' => $product->id]);
        }

        if ($prices['sell_price'] !== null && ! $product->sell_price) {
            $product->update(['sell_price' => $prices['sell_price']]);
        }

        // Новые штуки на склад
        $delivered = $purchase->status === 'delivered';
        foreach ($line['variants'] ?? [] as $variant) {
            for ($n = 0; $n < (int) $variant['qty']; $n++) {
                StockItem::create([
                    'product_id' => $product->id,
                    'purchase_id' => $purchase->id,
                    'purchase_item_id' => $item->id,
                    'size' => $variant['size'] ?? null,
                    'color' => $variant['color'] ?? null,
                    'buy_price' => $prices['price_amd'],
                    'sell_price' => $prices['sell_price'] ?? $product->sell_price,
                    'status' => $delivered ? 'in_stock' : 'ordered',
                    'arrived_at' => $delivered ? $purchase->delivered_at : null,
                ]);
            }
        }

        // Размеры и цвета товара = всё, что есть на складе
        $product->update([
            'sizes' => array_values(array_unique(array_filter(array_merge($product->sizes ?? [], array_column($line['variants'] ?? [], 'size'))))),
            'colors' => array_values(array_unique(array_filter(array_merge($product->colors ?? [], array_column($line['variants'] ?? [], 'color'))))),
        ]);
    }

    private function validated(Request $request, ?Purchase $purchase = null): array
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'source' => ['required', Rule::in(array_keys(Product::SOURCES))],
            'status' => ['required', Rule::in(array_keys(Purchase::STATUSES))],
            'url' => 'nullable|string',
            'yuan_rate' => 'nullable|numeric|min:0',
            'shipping_cost' => 'nullable|integer|min:0',
            'shipping_paid_at' => 'nullable|date',
            'goods_paid_before' => 'nullable|boolean',
            'purchased_at' => 'required|date',
            'delivered_at' => 'nullable|date',
            'notes' => 'nullable|string',

            // старая закупка без строк
            'quantity' => 'nullable|integer|min:1',
            'price_amd' => 'nullable|integer|min:0',
            'price_yuan' => 'nullable|numeric|min:0',

            'lines' => 'nullable|array',
            'lines.*.id' => 'nullable|integer',
            'lines.*.delete' => 'nullable|boolean',
            'lines.*.mode' => 'nullable|in:existing,new',
            'lines.*.product_id' => 'nullable|required_if:lines.*.mode,existing|exists:products,id',
            'lines.*.new_product.name' => 'nullable|required_if:lines.*.mode,new|string|max:255',
            'lines.*.new_product.description' => 'nullable|string',
            'lines.*.new_product.pinduoduo_url' => 'nullable|string',
            'lines.*.new_product.images' => 'nullable|array',
            'lines.*.new_product.images.*' => 'image|max:10240',
            'lines.*.price_amd' => 'required|integer|min:0',
            'lines.*.price_yuan' => 'nullable|numeric|min:0',
            'lines.*.sell_price' => 'nullable|integer|min:0',
            'lines.*.variants' => 'nullable|array',
            'lines.*.variants.*.size' => 'nullable|string|max:255',
            'lines.*.variants.*.color' => 'nullable|string|max:255',
            'lines.*.variants.*.qty' => 'required|integer|min:1|max:200',
        ], [
            'lines.*.product_id.required_if' => 'Выберите товар',
            'lines.*.new_product.name.required_if' => 'Введите название товара',
            'lines.*.price_amd.required' => 'Укажите цену',
        ]);

        // Новые строки должны иметь хотя бы одну штуку
        foreach ($data['lines'] ?? [] as $i => $line) {
            if (empty($line['id']) && empty($line['delete']) && empty($line['variants'])) {
                throw ValidationException::withMessages(["lines.$i.variants" => 'Добавьте размер и количество']);
            }
        }

        return $data;
    }
}
