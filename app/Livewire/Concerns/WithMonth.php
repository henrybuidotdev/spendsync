<?php

namespace App\Livewire\Concerns;

use App\Support\Format;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Url;

trait WithMonth
{
    #[Url]
    public string $month = '';

    public function previousMonth(): void
    {
        $this->month = $this->monthStart()->subMonth()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->month = $this->monthStart()->addMonth()->format('Y-m');
    }

    protected function monthStart(): CarbonImmutable
    {
        return Format::month($this->month);
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} UTC bounds [start, end) */
    protected function monthRange(): array
    {
        $start = $this->monthStart();

        return [$start->utc(), $start->addMonth()->utc()];
    }
}
