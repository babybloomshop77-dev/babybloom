<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ProductController extends Controller
{
    use Concerns;

    public function index(Request $request)
    {
        $products = Product::query()
            ->with('images')
            ->withCount([
                'stockItems as in_stock_count' => fn ($q) => $q->whereNotIn('status', ['sold', 'ordered']),
                'stockItems as ordered_count' => fn ($q) => $q->where('status', 'ordered'),
                'stockItems as sold_count' => fn ($q) => $q->where('status', 'sold'),
            ])
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->when($request->source, fn ($q, $s) => $q->where('source', $s))
            ->latest()
            ->paginate(24)
            ->withQueryString();

        return Inertia::render('Products/Index', [
            'products' => $products,
            'filters' => $request->only('search', 'source'),
            'sources' => Product::SOURCES,
        ]);
    }

    public function create()
    {
        return Inertia::render('Products/Form', [
            'product' => null,
            'sources' => Product::SOURCES,
            'backUrl' => $this->backUrl(route('products.index')),
        ]);
    }

    public function store(Request $request)
    {
        $product = Product::create($this->validated($request));
        $this->saveImages($request, $product);

        return redirect($this->safeBack($request->input('back'), route('products.index')))->with('success', 'Товар добавлен');
    }

    public function edit(Product $product)
    {
        return Inertia::render('Products/Form', [
            'product' => $product->load('images'),
            'sources' => Product::SOURCES,
            'backUrl' => $this->backUrl(route('products.index')),
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $product->update($this->validated($request));

        foreach ($product->images()->whereIn('id', $request->input('delete_images', []))->get() as $image) {
            Storage::disk('public')->delete($image->path);
            $image->delete();
        }
        // Цвет у уже загруженных фото
        foreach ((array) $request->input('image_colors', []) as $id => $color) {
            $product->images()->whereKey($id)->update(['color' => $color ?: null]);
        }
        $this->saveImages($request, $product);

        return redirect($this->safeBack($request->input('back'), route('products.index')))->with('success', 'Товар сохранён');
    }

    public function destroy(Product $product)
    {
        foreach ($product->images as $image) {
            Storage::disk('public')->delete($image->path);
        }
        $product->delete();

        return back()->with('success', 'Товар удалён');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'source' => 'required|in:'.implode(',', array_keys(Product::SOURCES)),
            'pinduoduo_url' => 'nullable|string',
            'buy_price_yuan' => 'nullable|numeric|min:0',
            'buy_price_amd' => 'nullable|integer|min:0',
            'shipping_cost' => 'nullable|integer|min:0',
            'sell_price' => 'nullable|integer|min:0',
            'sizes' => 'nullable|array',
            'sizes.*' => 'string|max:255',
            'colors' => 'nullable|array',
            'colors.*' => 'string|max:100',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
            'images' => 'nullable|array',
            'images.*' => 'image|max:10240',
            'image_colors' => 'nullable|array',
            'image_colors.*' => 'nullable|string|max:100',
            'new_image_colors' => 'nullable|array',
            'new_image_colors.*' => 'nullable|string|max:100',
        ]);
        unset($data['images'], $data['image_colors'], $data['new_image_colors']);
        $data['sizes'] = array_values(array_filter($data['sizes'] ?? []));
        $data['colors'] = array_values(array_filter($data['colors'] ?? []));

        return $data;
    }

    private function saveImages(Request $request, Product $product): void
    {
        $sort = (int) $product->images()->max('sort');
        $colors = (array) $request->input('new_image_colors', []);
        foreach ($request->file('images', []) as $i => $file) {
            ProductImage::create([
                'product_id' => $product->id,
                'path' => $file->store("products/{$product->id}", 'public'),
                'color' => ($colors[$i] ?? null) ?: null,
                'sort' => ++$sort,
            ]);
        }
    }
}
