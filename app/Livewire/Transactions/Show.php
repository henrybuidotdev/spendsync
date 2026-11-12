<?php

namespace App\Livewire\Transactions;

use App\Categorization\CategoryMatcher;
use App\Enums\MatchField;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public Transaction $transaction;

    public bool $applyToFuture = true;

    public string $note = '';

    public bool $showRaw = false;

    /** The list tab this transaction was opened from, so "back" returns to it. */
    #[Url]
    public string $filter = 'all';

    public function mount(Transaction $transaction): void
    {
        $this->authorize('view', $transaction);

        $this->note = (string) $transaction->note;
    }

    public function setCategory(int $categoryId, CategoryMatcher $matcher): void
    {
        $this->authorize('update', $this->transaction);

        $category = $this->selectableCategories()->findOrFail($categoryId);

        $this->transaction->update(['category_id' => $category->id]);

        $message = __('Category saved.');

        if ($this->applyToFuture && $this->ruleTarget() !== null) {
            $count = $matcher->backfill($matcher->remember($this->transaction, $category));
            $message = __('Category saved and applied to :count other transactions.', ['count' => $count]);
        }

        $this->transaction->load('category');
        session()->flash('status', $message);
    }

    public function updatedNote(): void
    {
        $this->authorize('update', $this->transaction);

        $this->validate(['note' => ['nullable', 'string', 'max:1000']]);

        $note = trim($this->note);

        $this->transaction->update(['note' => $note !== '' ? $note : null]);

        $this->dispatch('note-saved');
    }

    public function toggleRaw(): void
    {
        $this->showRaw = ! $this->showRaw;
    }

    /** What a rule would be created for: the account number (with the name), else the name, else the description. */
    public function ruleTarget(): ?string
    {
        $transaction = $this->transaction;

        return match (CategoryMatcher::ruleField($transaction)) {
            MatchField::Account => trim("{$transaction->counterparty} · ".__('Acct :account', ['account' => $transaction->counterparty_account]), ' ·'),
            MatchField::Counterparty => $transaction->counterparty,
            MatchField::Description => $transaction->description,
        };
    }

    /** @return Builder<Category> */
    private function selectableCategories(): Builder
    {
        return auth()->user()->availableCategories();
    }

    public function render()
    {
        $rawEmail = $this->transaction->rawEmail;

        return view('livewire.transactions.show', [
            'categories' => $this->selectableCategories()->orderByRaw('user_id is null desc')->orderBy('id')->get(),
            'defaultTab' => ($this->transaction->category?->type ?? $this->transaction->direction->categoryTypes()[0])->value,
            'bankName' => config("banks.{$this->transaction->bank_code}.name", $this->transaction->bank_code),
            'rawText' => $this->showRaw && $rawEmail ? $rawEmail->toParserInput()->text : null,
            'rawEmail' => $rawEmail,
            'backFilter' => in_array($this->filter, Index::FILTERS, true) && $this->filter !== 'all' ? $this->filter : null,
        ])->title(__('Transaction details'));
    }
}
