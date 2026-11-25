<?php

namespace App\Livewire;

use App\Enums\Direction;
use App\Livewire\Concerns\WithMonth;
use App\Support\Demo;
use App\Support\Format;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Reports extends Component
{
    use WithMonth;

    public function render()
    {
        $user = Demo::owner();
        [$start, $end] = $this->monthRange();

        $spending = $user->transactions()
            ->where('direction', Direction::Out)
            ->where('occurred_at', '>=', $start)
            ->where('occurred_at', '<', $end);

        $categories = $user->availableCategories()->get()->keyBy('id');

        $byCategory = (clone $spending)
            ->selectRaw('category_id, sum(amount) as total, count(*) as count')
            ->groupBy('category_id')
            ->toBase()
            ->get()
            ->groupBy(fn ($row) => $categories->get($row->category_id)?->parent_id ?? $row->category_id)
            ->map(fn ($rows, $categoryId) => (object) [
                'name' => $categories->get($categoryId)?->label ?? __('Uncategorized'),
                'category' => $categories->get($categoryId),
                'total' => (int) $rows->sum('total'),
                'count' => (int) $rows->sum('count'),
            ])
            ->sortByDesc('total')
            ->values();

        $total = $byCategory->sum('total');

        $topCounterparties = (clone $spending)
            ->whereNotNull('counterparty')
            ->selectRaw('counterparty, sum(amount) as total, count(*) as count')
            ->groupBy('counterparty')
            ->orderByDesc('total')
            ->limit(5)
            ->toBase()
            ->get();

        $isCurrentMonth = $this->monthStart()->equalTo(Format::month(null));
        $previousTotal = $this->previousMonthSpending($isCurrentMonth);

        return view('livewire.reports', [
            'monthStart' => $this->monthStart(),
            'byCategory' => $byCategory,
            'total' => $total,
            'change' => $previousTotal > 0 ? (int) round(100 * ($total - $previousTotal) / $previousTotal) : null,
            'comparedTo' => $isCurrentMonth ? __('this time last month') : __('last month'),
            'topCounterparties' => $topCounterparties,
        ])->title(__('Reports'));
    }

    /** A month in progress is compared with the same stretch of the previous month, so early days don't always look cheaper. */
    private function previousMonthSpending(bool $isCurrentMonth): int
    {
        $monthStart = $this->monthStart();
        $previousStart = $monthStart->subMonth();

        $previousEnd = $isCurrentMonth ?
            $previousStart->add($monthStart->diff(CarbonImmutable::now()))->min($monthStart) :
            $monthStart;

        return (int) Demo::owner()->transactions()
            ->where('direction', Direction::Out)
            ->where('occurred_at', '>=', $previousStart->utc())
            ->where('occurred_at', '<', $previousEnd->utc())
            ->sum('amount');
    }
}
