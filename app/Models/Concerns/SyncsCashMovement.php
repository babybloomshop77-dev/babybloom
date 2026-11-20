<?php

namespace App\Models\Concerns;

use App\Models\CashMovement;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Новые расходы / закупки / продажи автоматически попадают в кассу.
 *
 * Модель отдаёт cashMovementParts(): ['часть' => данные|null], например
 * закупка = ['goods' => ..., 'shipping' => ...]. null или сумма 0 — движения нет.
 *
 * Старые записи (созданные до появления кассы) в кассу не попадают:
 * при изменении движение создаётся, только если cashTracksUpdates() = true.
 */
trait SyncsCashMovement
{
    /** @return array<string, array|null> */
    abstract public function cashMovementParts(): array;

    public static function bootSyncsCashMovement(): void
    {
        static::created(fn ($model) => $model->syncCashMovement(true));
        static::updated(fn ($model) => $model->syncCashMovement($model->cashTracksUpdates()));
        static::deleted(fn ($model) => $model->cashMovements()->delete());
    }

    public function cashMovements(): MorphMany
    {
        return $this->morphMany(CashMovement::class, 'source');
    }

    /** Можно ли создавать новые движения при изменении записи */
    public function cashTracksUpdates(): bool
    {
        return false;
    }

    public function syncCashMovement(bool $create): void
    {
        $existing = $this->cashMovements()->get()->keyBy('part');

        foreach ($this->cashMovementParts() as $part => $data) {
            $movement = $existing->get($part);

            if (! $data || $data['amount'] <= 0) {
                $movement?->delete();
            } elseif ($movement) {
                $movement->update($data);
            } elseif ($create || $existing->isNotEmpty()) {
                $this->cashMovements()->create($data + ['part' => $part]);
            }
        }
    }
}
