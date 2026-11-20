<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Каталог товаров (тип товара)
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('source', 20)->default('pinduoduo'); // pinduoduo | wholesale | other
            $table->text('pinduoduo_url')->nullable();
            $table->decimal('buy_price_yuan', 10, 2)->nullable();   // за 1 шт
            $table->unsignedInteger('buy_price_amd')->nullable();   // за 1 шт
            $table->unsignedInteger('shipping_cost')->nullable();   // доставка за 1 шт
            $table->unsignedInteger('sell_price')->nullable();
            $table->json('sizes')->nullable();
            $table->json('colors')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        // Закупки (заказы в Pinduoduo / у оптовика)
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('source', 20)->default('pinduoduo');
            $table->text('url')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('price_amd')->default(0);      // всего, AMD
            $table->decimal('price_yuan', 10, 2)->nullable();      // всего, ¥
            $table->unsignedInteger('shipping_cost')->default(0);  // доставка всего, AMD
            $table->date('purchased_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Склад: каждая единица товара (размер/цвет)
        Schema::create('stock_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_id')->nullable()->constrained()->nullOnDelete();
            $table->string('size')->nullable();
            $table->string('color')->nullable();
            $table->unsignedInteger('buy_price')->nullable();  // себестоимость (цена + доставка)
            $table->unsignedInteger('sell_price')->nullable();
            $table->string('status', 20)->default('in_stock'); // in_stock | processing | reserved | sold
            $table->string('reserved_for')->nullable();
            $table->date('arrived_at')->nullable();
            $table->date('sold_at')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
            $table->index('status');
        });

        // Заказы клиентов
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('items');                 // что заказали
            $table->string('customer_name');
            $table->string('channel', 20)->nullable(); // instagram | facebook | phone | other
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->unsignedInteger('price')->default(0);
            $table->date('ordered_at')->nullable();
            $table->boolean('is_done')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Продажи
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('product');
            $table->foreignId('stock_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('price')->nullable();
            $table->unsignedInteger('cost')->nullable(); // себестоимость
            $table->date('sold_at');
            $table->string('customer_name')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
            $table->index('sold_at');
        });

        // Расходы
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('category', 30)->default('other');
            $table->unsignedInteger('amount');
            $table->date('spent_at');
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index('spent_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('stock_items');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('products');
    }
};
