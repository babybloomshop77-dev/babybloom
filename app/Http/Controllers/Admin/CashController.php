<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CashMovement;
use App\Models\Expense;
use App\Models\Purchase;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CashController extends Controller
{
    use Concerns;

    private const SOURCES = [
        Expense::class => 'expense',
        Purchase::class => 'purchase',
        Sale::class => 'sale',
    ];

    public function index(Request $request)
    {
        [$month, $start, $end] = $this->monthRange($request->merge(['month' => $request->input('month', 'all')]));

        // Считаем остаток после каждой операции по всей истории
        $running = 0;
        $all = CashMovement::orderBy('moved_at')->orderBy('id')->get()->map(function ($m) use (&$running) {
            $running += $m->type === 'in' ? $m->amount : -$m->amount;
            $m->balance_after = $running;
            $m->source_kind = self::SOURCES[$m->source_type] ?? null;
            $m->category_key = $m->source instanceof Expense ? $m->source->category : null;

            return $m;
        });

        $period = $all
            ->when($start, fn ($c) => $c->filter(fn ($m) => $m->moved_at->between($start, $end)))
            ->when($request->type, fn ($c, $t) => $c->where('type', $t))
            ->when($request->search, fn ($c, $s) => $c->filter(fn ($m) => mb_stripos($m->title.' '.$m->category.' '.$m->note, $s) !== false));

        return Inertia::render('Cash/Index', [
            'movements' => $period->reverse()->values(),
            'balance' => $running,
            'totals' => [
                'in' => (int) $period->where('type', 'in')->sum('amount'),
                'out' => (int) $period->where('type', 'out')->sum('amount'),
            ],
            'filters' => ['month' => $month, 'type' => $request->type, 'search' => $request->search],
            'categories' => Expense::CATEGORIES,
        ]);
    }

    /** Расход -> создаётся запись в «Расходах» (и автоматически в кассе). Приход -> только касса. */
    public function store(Request $request)
    {
        $data = $this->validated($request);

        if ($data['type'] === 'out') {
            Expense::create([
                'title' => $data['title'],
                'category' => $data['category'] ?? 'other',
                'amount' => $data['amount'],
                'spent_at' => $data['moved_at'],
                'note' => $data['note'] ?? null,
            ]);
        } else {
            CashMovement::create($data + ['category' => null]);
        }

        return back()->with('success', $data['type'] === 'out' ? 'Расход записан' : 'Приход записан');
    }

    public function update(Request $request, CashMovement $cash)
    {
        $data = $this->validated($request);

        if ($cash->source instanceof Expense) {
            $cash->source->update([
                'title' => $data['title'],
                'category' => $data['category'] ?? $cash->source->category,
                'amount' => $data['amount'],
                'spent_at' => $data['moved_at'],
                'note' => $data['note'] ?? null,
            ]);
        } elseif ($cash->source_type === null) {
            $cash->update(collect($data)->except('category')->all());
        } else {
            return back()->with('error', 'Эту запись меняйте в разделе «Продажи» или «Закупки»');
        }

        return back()->with('success', 'Сохранено');
    }

    public function destroy(CashMovement $cash)
    {
        if ($cash->source instanceof Expense) {
            $cash->source->delete();   // удалит и движение в кассе
        } elseif ($cash->source_type === null) {
            $cash->delete();
        } else {
            return back()->with('error', 'Эту запись удаляйте в разделе «Продажи» или «Закупки»');
        }

        return back()->with('success', 'Удалено');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'type' => ['required', Rule::in(['in', 'out'])],
            'amount' => 'required|integer|min:1',
            'title' => 'required|string|max:255',
            'category' => ['nullable', Rule::in(array_keys(Expense::CATEGORIES))],
            'moved_at' => 'required|date',
            'note' => 'nullable|string',
        ]);
    }
}
