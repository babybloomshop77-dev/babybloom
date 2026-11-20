<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Касса: каждое движение денег (приход / расход) с датой
        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->string('type', 3);              // in | out
            $table->unsignedInteger('amount');
            $table->string('title');
            $table->string('category')->nullable();
            $table->date('moved_at');
            $table->text('note')->nullable();
            $table->nullableMorphs('source');       // Expense / Purchase / Sale, если создано автоматически
            $table->timestamps();
            $table->index(['moved_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
    }
};
