<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ExpenseController extends Controller
{
    use Concerns;

    public function index(Request $request)
    {
        [$month, $start, $end] = $this->monthRange($request);

        $query = Expense::query()
            ->when($start, fn ($q) => $q->whereBetween('spent_at', [$start, $end]))
            ->when($request->category, fn ($q, $c) => $q->where('category', $c))
            ->when($request->search, fn ($q, $s) => $q->where('title', 'like', $this->like($s)));

        return Inertia::render('Expenses/Index', [
            'expenses' => (clone $query)->latest('spent_at')->latest('id')->paginate(100)->withQueryString(),
            'total' => (int) (clone $query)->sum('amount'),
            'byCategory' => (clone $query)->reorder()->selectRaw('category, SUM(amount) as total')
                ->groupBy('category')->pluck('total', 'category'),
            'filters' => ['month' => $month, 'category' => $request->category, 'search' => $request->search],
            'categories' => Expense::CATEGORIES,
        ]);
    }

    public function store(Request $request)
    {
        Expense::create($this->validated($request));

        return back()->with('success', 'Расход добавлен');
    }

    public function update(Request $request, Expense $expense)
    {
        $expense->update($this->validated($request));

        return back()->with('success', 'Расход сохранён');
    }

    public function destroy(Expense $expense)
    {
        $expense->delete();

        return back()->with('success', 'Расход удалён');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|in:'.implode(',', array_keys(Expense::CATEGORIES)),
            'amount' => 'required|integer|min:0',
            'spent_at' => 'required|date',
            'note' => 'nullable|string',
        ]);
    }
}
