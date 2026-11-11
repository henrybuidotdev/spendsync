<?php

namespace App\Livewire\Transactions;

use App\Categorization\CategoryMatcher;
use App\Models\Transaction;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public Transaction $transaction;

    public bool $applyToFuture = true;

    public string $note = '';

    public bool $showRaw = false;

    public function mount(Transaction $transaction): void
    {
        $this->authorize('view', $transaction);

        $this->note = (string) $transaction->note;
    }

    public function setCategory(int $categoryId, CategoryMatcher $matcher): void
    {
        $this->authorize('update', $this->transaction);

        $category = auth()->user()->categories()->findOrFail($categoryId);

        $this->transaction->update(['category_id' => $category->id]);

        $message = __('Đã lưu danh mục.');

        if ($this->applyToFuture && $this->ruleTarget() !== null) {
            $count = $matcher->backfill($matcher->remember($this->transaction, $category));
            $message = __('Đã lưu danh mục và áp dụng cho :count giao dịch khác.', ['count' => $count]);
        }

        $this->transaction->load('category');
        session()->flash('status', $message);
    }

    public function saveNote(): void
    {
        $this->authorize('update', $this->transaction);

        $this->validate(['note' => ['nullable', 'string', 'max:1000']]);

        $this->transaction->update(['note' => $this->note !== '' ? $this->note : null]);

        session()->flash('status', __('Đã lưu ghi chú.'));
    }

    public function toggleRaw(): void
    {
        $this->showRaw = ! $this->showRaw;
    }

    /** The counterparty (or, without one, the description) a rule would be created for. */
    public function ruleTarget(): ?string
    {
        return $this->transaction->counterparty ?: $this->transaction->description;
    }

    public function render()
    {
        $rawEmail = $this->transaction->rawEmail;

        return view('livewire.transactions.show', [
            'categories' => auth()->user()->categories()->orderBy('type')->orderBy('id')->get(),
            'bankName' => config("banks.{$this->transaction->bank_code}.name", $this->transaction->bank_code),
            'rawText' => $this->showRaw && $rawEmail ? $rawEmail->toParserInput()->text : null,
            'rawEmail' => $rawEmail,
        ])->title(__('Chi tiết giao dịch'));
    }
}
