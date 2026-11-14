<?php

namespace App\Livewire;

use App\Enums\Direction;
use App\Livewire\Concerns\WithMonth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Reports extends Component
{
    use WithMonth;

    public function render()
    {
        $user = auth()->user();
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

        return view('livewire.reports', [
            'monthStart' => $this->monthStart(),
            'byCategory' => $byCategory,
            'total' => $total,
            'topCounterparties' => $topCounterparties,
        ])->title(__('Reports'));
    }
}
