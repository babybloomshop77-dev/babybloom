<?php

namespace App\Models;

use App\Models\Concerns\SyncsCashMovement;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use SyncsCashMovement;

    public const CATEGORIES = [
        'goods' => 'Закупка товара',
        'delivery' => 'Доставка',
        'ads' => 'Реклама',
        'packaging' => 'Упаковка',
        'other' => 'Другое',
    ];

    protected $fillable = ['title', 'category', 'amount', 'spent_at', 'note'];

    protected $casts = [
        'spent_at' => 'date:Y-m-d',
    ];

    public function cashMovementParts(): array
    {
        return [
            'main' => [
                'type' => 'out',
                'amount' => (int) $this->amount,
                'title' => $this->title,
                'category' => self::CATEGORIES[$this->category] ?? null,
                'moved_at' => $this->spent_at,
                'note' => $this->note,
            ],
        ];
    }
}
