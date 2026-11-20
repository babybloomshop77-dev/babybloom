<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CashMovement extends Model
{
    protected $fillable = ['type', 'amount', 'title', 'category', 'moved_at', 'note', 'source_type', 'source_id', 'part'];

    protected $casts = [
        'moved_at' => 'date:Y-m-d',
    ];

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /** Текущий остаток в кассе */
    public static function balance(): int
    {
        return (int) static::query()->selectRaw("COALESCE(SUM(CASE WHEN type = 'in' THEN amount ELSE -amount END), 0) as b")->value('b');
    }
}
