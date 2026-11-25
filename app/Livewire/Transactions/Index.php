<?php

namespace App\Livewire\Transactions;

use App\Enums\Direction;
use App\Livewire\Concerns\WithMonth;
use App\Models\Transaction;
use App\Support\Demo;
use App\Support\Format;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithMonth;

    public const FILTERS = ['all', 'uncategorized', 'in', 'out'];

    #[Url]
    public string $filter = 'all';

    #[Url]
    public ?int $category = null;

    #[Url]
    public string $payee = '';

    public function mount(): void
    {
        if ($this->category === null) {
            return;
        }

        if (Demo::owner()->availableCategories()->whereKey($this->category)->exists()) {
            return;
        }

        $this->redirect(route('transactions', Arr::except(request()->query(), 'category')), navigate: true);
    }

    public function clearScope(): void
    {
        $this->reset('category', 'payee');
    }

    public function setFilter(string $filter): void
    {
        $this->filter = in_array($filter, self::FILTERS, true) ? $filter : 'all';
    }

    public function render()
    {
        $user = Demo::owner();
        [$start, $end] = $this->monthRange();

        $monthQuery = $user->transactions()->where('occurred_at', '>=', $start)->where('occurred_at', '<', $end);

        $totals = (clone $monthQuery)
            ->selectRaw("coalesce(sum(case when direction = 'out' then amount else 0 end), 0) as spent")
            ->selectRaw("coalesce(sum(case when direction = 'in' then amount else 0 end), 0) as income")
            ->toBase()
            ->first();

        $category = $this->category ? $user->availableCategories()->find($this->category) : null;

        $transactions = $monthQuery
            ->withTrashed()
            ->with('category')
            ->when($this->filter === 'uncategorized', fn ($query) => $query->whereNull('category_id'))
            ->when($this->filter === 'in', fn ($query) => $query->where('direction', Direction::In))
            ->when($this->filter === 'out', fn ($query) => $query->where('direction', Direction::Out))
            ->when($category, fn ($query) => $query->whereIn('category_id', $user->availableCategories()->where(fn ($query) => $query->whereKey($category->id)->orWhere('parent_id', $category->id))->pluck('id')))
            ->when($this->payee !== '', fn ($query) => $query->where('counterparty', $this->payee))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->get();

        return view('livewire.transactions.index', [
            'account' => $user->mailAccount,
            'spent' => (int) $totals->spent,
            'income' => (int) $totals->income,
            'days' => $transactions->groupBy(fn (Transaction $transaction) => Format::local($transaction->occurred_at)->toDateString()),
            'monthStart' => $this->monthStart(),
            'scopeLabel' => $category?->label ?? ($this->payee !== '' ? $this->payee : null),
            'latestMonth' => $this->latestMonthWithData($transactions->isEmpty()),
            'hasAnyTransactions' => $transactions->isNotEmpty() || $user->transactions()->withTrashed()->exists(),
        ])->title(__('Transactions'));
    }

    private function latestMonthWithData(bool $currentIsEmpty): ?string
    {
        if (! $currentIsEmpty) {
            return null;
        }

        $latest = Demo::owner()->transactions()->max('occurred_at');

        if ($latest === null) {
            return null;
        }

        $month = Format::local(CarbonImmutable::parse($latest, 'UTC'))->format('Y-m');

        return $month === $this->monthStart()->format('Y-m') ? null : $month;
    }
}
