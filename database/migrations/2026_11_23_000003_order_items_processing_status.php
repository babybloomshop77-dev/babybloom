<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Товары в открытых заказах: «Бронь» -> «В обработке»
        DB::table('stock_items')->whereNotNull('order_id')->where('status', 'reserved')->update(['status' => 'processing']);
    }

    public function down(): void
    {
        DB::table('stock_items')->whereNotNull('order_id')->where('status', 'processing')->update(['status' => 'reserved']);
    }
};
