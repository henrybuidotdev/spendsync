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

    public bool $applyToFuture = false;

    public string $note = '';

    public bool $showRaw = false;

    /** The list tab this transaction was opened from, so "back" returns to it. */
    #[Url]
    public string $filter = 'all';

    public function mount(Transaction $transaction, CategoryMatcher $matcher): void
    {
        $this->authorize('view', $transaction);

        $this->applyToFuture = $transaction->category_id !== null && $matcher->follows($transaction);

        $this->note = (string) $transaction->note;
    }

    public function setCategory(int $categoryId, CategoryMatcher $matcher): void
    {
        $this->authorize('update', $this->transaction);

        $category = $this->selectableCategories()->findOrFail($categoryId);

        $this->transaction->update(['category_id' => $category->id]);

        $this->applyToFuture = $matcher->follows($this->transaction);

        $this->transaction->load('category');
        session()->flash('status', __('Category saved.'));
    }

    public function updatedApplyToFuture(): void
    {
        $this->authorize('update', $this->transaction);

        if ($this->transaction->category_id === null) {
            return;
        }

        if ($this->ruleTarget() === null) {
            return;
        }

        $matcher = app(CategoryMatcher::class);

        if (! $this->applyToFuture) {
            $matcher->forget($this->transaction);
            session()->flash('status', __('Rule removed.'));

            return;
        }

        $matcher->remember($this->transaction, $this->transaction->category);

        session()->flash('status', __('Rule saved.'));
    }

    public function applyToUncategorized(CategoryMatcher $matcher): void
    {
        $this->authorize('update', $this->transaction);

        if ($this->transaction->category_id === null) {
            return;
        }

        if ($this->ruleTarget() === null) {
            return;
        }

        $count = $matcher->backfill($matcher->draft($this->transaction));

        session()->flash('status', __('Applied to :count transactions.', ['count' => $count]));
    }

    public function updatedNote(): void
    {
        $this->authorize('update', $this->transaction);

        $this->validate(['note' => ['nullable', 'string', 'max:1000']]);

        $note = trim($this->note);

        $this->transaction->update(['note' => $note !== '' ? $note : null]);

        $this->dispatch('note-saved');
    }

    public function toggleHidden(): void
    {
        $this->authorize('delete', $this->transaction);

        $this->transaction->trashed()
            ? $this->transaction->restore()
            : $this->transaction->delete();

        session()->flash('visibility', $this->transaction->trashed() ?
            __('Transaction hidden. It no longer counts in totals or reports.') :
            __('Transaction shown again. It counts in totals and reports.'));
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
            'categories' => $this->selectableCategories()->ordered()->get(),
            'defaultTab' => ($this->transaction->category?->type ?? $this->transaction->direction->categoryTypes()[0])->value,
            'bankName' => config("banks.{$this->transaction->bank_code}.name", $this->transaction->bank_code),
            'rawText' => $this->showRaw && $rawEmail ? $rawEmail->toParserInput()->text : null,
            'rawEmail' => $rawEmail,
            'backfillCount' => $this->transaction->category_id && $this->ruleTarget() !== null ?
                app(CategoryMatcher::class)->backfillCount(app(CategoryMatcher::class)->draft($this->transaction)) :
                0,
            'backFilter' => in_array($this->filter, Index::FILTERS, true) && $this->filter !== 'all' ? $this->filter : null,
        ])->title(__('Transaction details'));
    }
}
