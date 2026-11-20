<?php

namespace Database\Seeders;

use App\Models\CashMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@babybloom.am')],
            [
                'name' => 'BabyBloom Admin',
                'password' => Hash::make(env('ADMIN_PASSWORD', 'babybloom')),
                'email_verified_at' => now(),
            ],
        );

        // Исторические данные в кассу не попадают — они уже учтены в начальном остатке
        Model::withoutEvents(fn () => $this->call(ShopDataSeeder::class));

        CashMovement::firstOrCreate(
            ['title' => 'Начальный остаток', 'source_type' => null],
            ['type' => 'in', 'amount' => 128984, 'moved_at' => '2026-11-19', 'note' => 'Все прошлые расходы уже учтены'],
        );
    }
}
