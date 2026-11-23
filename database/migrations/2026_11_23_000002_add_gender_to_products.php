<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Для кого товар: boy | girl | unisex (null — не указано)
        Schema::table('products', function (Blueprint $table) {
            $table->string('gender', 10)->nullable()->after('name')->index();
        });

        // «axjka» (աղջիկ) в названии — это товар для девочки
        DB::table('products')->whereNull('gender')->where('name', 'like', '%axjka%')->update(['gender' => 'girl']);
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('gender'));
    }
};
