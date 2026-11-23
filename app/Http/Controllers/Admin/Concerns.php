<?php

namespace App\Http\Controllers\Admin;

use Carbon\Carbon;
use Illuminate\Http\Request;

trait Concerns
{
    /** Возвращает [месяц 'Y-m' | 'all', начало, конец] */
    protected function monthRange(Request $request): array
    {
        $month = $request->input('month', now()->format('Y-m'));

        if ($month === 'all' || ! preg_match('/^\d{4}-\d{2}$/', $month)) {
            return ['all', null, null];
        }

        $start = Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();

        return [$month, $start, $start->copy()->endOfMonth()];
    }

    /** Все виды тире и дефисов: - ‐ ‑ ‒ – — ― − */
    private const DASHES = '/[\x{002D}\x{2010}-\x{2015}\x{2212}]/u';

    /**
     * Шаблон для поиска через LIKE: «2-3» находит и «2–3», и «2—3».
     * Любое тире заменяется на «_» (один любой символ).
     */
    protected function like(string $search): string
    {
        $escaped = addcslashes(trim($search), '\\%_');

        return '%'.preg_replace(self::DASHES, '_', $escaped).'%';
    }

    /** Привести все тире к обычному дефису (для поиска в PHP-коллекциях) */
    protected function plainDashes(?string $text): string
    {
        return preg_replace(self::DASHES, '-', (string) $text);
    }

    /** Страница, с которой открыли форму (для «Назад» и возврата после сохранения) */
    protected function backUrl(string $fallback): string
    {
        $previous = url()->previous();
        $path = (string) parse_url($previous, PHP_URL_PATH);

        $isForm = str_ends_with($path, '/edit') || str_ends_with($path, '/create');

        return $this->isOurUrl($previous) && ! $isForm ? $previous : $fallback;
    }

    /** Куда вернуться после сохранения: только адрес этого же сайта */
    protected function safeBack(?string $url, string $fallback): string
    {
        return $url && $this->isOurUrl($url) ? $url : $fallback;
    }

    private function isOurUrl(string $url): bool
    {
        $base = url('/');

        return $url === $base || str_starts_with($url, $base.'/') || str_starts_with($url, $base.'?');
    }
}
