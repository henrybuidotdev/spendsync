<?php

namespace App\Livewire\Transactions;

use App\Enums\Direction;
use App\Enums\MailAccountStatus;
use App\Livewire\Concerns\WithMonth;
use App\Models\Transaction;
use App\Support\Format;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\RateLimiter;
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

    public function setFilter(string $filter): void
    {
        $this->filter = in_array($filter, self::FILTERS, true) ? $filter : 'all';
    }

    public function syncNow(): void
    {
        $account = auth()->user()->mailAccount;

        if ($account?->status !== MailAccountStatus::Active) {
            return;
        }

        $key = 'sync-now:'.auth()->id();

        if (RateLimiter::tooManyAttempts($key, 1)) {
            session()->flash('status', __('Vừa đồng bộ xong. Thử lại sau :seconds giây.', ['seconds' => RateLimiter::availableIn($key)]));

            return;
        }

        RateLimiter::hit($key, 60);
        $account->queueSync();
    }

    public function render()
    {
        $user = auth()->user();
        [$start, $end] = $this->monthRange();

        $monthQuery = $user->transactions()->where('occurred_at', '>=', $start)->where('occurred_at', '<', $end);

        $totals = (clone $monthQuery)
            ->selectRaw("coalesce(sum(case when direction = 'out' then amount else 0 end), 0) as spent")
            ->selectRaw("coalesce(sum(case when direction = 'in' then amount else 0 end), 0) as income")
            ->toBase()
            ->first();

        $transactions = $monthQuery
            ->with('category')
            ->when($this->filter === 'uncategorized', fn ($query) => $query->whereNull('category_id'))
            ->when($this->filter === 'in', fn ($query) => $query->where('direction', Direction::In))
            ->when($this->filter === 'out', fn ($query) => $query->where('direction', Direction::Out))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->get();

        return view('livewire.transactions.index', [
            'account' => $user->mailAccount,
            'spent' => (int) $totals->spent,
            'income' => (int) $totals->income,
            'days' => $transactions->groupBy(fn (Transaction $transaction) => Format::local($transaction->occurred_at)->toDateString()),
            'monthStart' => $this->monthStart(),
            'latestMonth' => $this->latestMonthWithData($transactions->isEmpty()),
            'hasAnyTransactions' => $transactions->isNotEmpty() || $user->transactions()->exists(),
        ])->title(__('Giao dịch'));
    }

    private function latestMonthWithData(bool $currentIsEmpty): ?string
    {
        if (! $currentIsEmpty) {
            return null;
        }

        $latest = auth()->user()->transactions()->max('occurred_at');

        if ($latest === null) {
            return null;
        }

        $month = Format::local(CarbonImmutable::parse($latest, 'UTC'))->format('Y-m');

        return $month === $this->monthStart()->format('Y-m') ? null : $month;
    }
}
