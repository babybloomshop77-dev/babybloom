<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Фото для конкретного цвета (null — общее фото товара)
        Schema::table('product_images', function (Blueprint $table) {
            $table->string('color')->nullable()->after('path');
        });
    }

    public function down(): void
    {
        Schema::table('product_images', fn (Blueprint $table) => $table->dropColumn('color'));
    }
};
