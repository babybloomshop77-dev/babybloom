<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Товар на складе может входить в один заказ клиента
        Schema::table('stock_items', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->after('purchase_item_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_items', fn (Blueprint $table) => $table->dropConstrainedForeignId('order_id'));
    }
};
