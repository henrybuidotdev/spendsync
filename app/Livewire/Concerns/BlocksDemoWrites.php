<?php

namespace App\Livewire\Concerns;

use App\Exceptions\DemoDataIsReadOnly;
use Throwable;

/** Turns a refused write on demo data into the read-only warning toast (see layouts/app). */
trait BlocksDemoWrites
{
    public function exception(Throwable $e, callable $stopPropagation): void
    {
        if (! $e instanceof DemoDataIsReadOnly) {
            return;
        }

        $this->dispatch('demo-read-only');

        $stopPropagation();
    }
}
