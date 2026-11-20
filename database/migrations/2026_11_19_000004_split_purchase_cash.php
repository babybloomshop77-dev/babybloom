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
            $table->boolean('goods_paid_before')->default(false)->after('shipping_cost'); // товар уже учтён в кассе
            $table->date('shipping_paid_at')->nullable()->after('goods_paid_before');      // когда оплатили доставку
            $table->boolean('cash_tracked')->default(false)->after('shipping_paid_at');   // закупка ведётся в кассе
        });

        // Одна запись источника может дать несколько движений: товар / доставка
        Schema::table('cash_movements', function (Blueprint $table) {
            $table->string('part', 20)->default('main')->after('source_id');
        });

        $purchase = 'App\\Models\\Purchase';
        DB::table('cash_movements')->where('source_type', $purchase)->update(['part' => 'goods']);
        DB::table('purchases')
            ->whereIn('id', DB::table('cash_movements')->where('source_type', $purchase)->pluck('source_id'))
            ->update(['cash_tracked' => true]);
    }

    public function down(): void
    {
        Schema::table('cash_movements', fn (Blueprint $table) => $table->dropColumn('part'));
        Schema::table('purchases', fn (Blueprint $table) => $table->dropColumn(['goods_paid_before', 'shipping_paid_at', 'cash_tracked']));
    }
};
