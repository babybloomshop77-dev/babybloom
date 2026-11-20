<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->string('status', 20)->default('delivered')->after('source'); // ordered | delivered
            $table->date('delivered_at')->nullable()->after('purchased_at');
            $table->decimal('yuan_rate', 8, 2)->nullable()->after('price_yuan');   // курс: ֏ за 1 ¥
        });

        // Старые закупки уже доставлены
        DB::table('purchases')->update(['delivered_at' => DB::raw('purchased_at')]);

        // Строки закупки: какой товар и по какой цене (за 1 шт, без доставки)
        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('price_amd')->default(0);
            $table->decimal('price_yuan', 10, 2)->nullable();
            $table->unsignedInteger('sell_price')->nullable();
            $table->timestamps();
        });

        Schema::table('stock_items', function (Blueprint $table) {
            $table->foreignId('purchase_item_id')->nullable()->after('purchase_id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchase_item_id');
        });
        Schema::dropIfExists('purchase_items');
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn(['status', 'delivered_at', 'yuan_rate']);
        });
    }
};
