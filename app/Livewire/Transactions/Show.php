<?php

namespace App\Livewire\Transactions;

use App\Categorization\CategoryMatcher;
use App\Enums\MatchField;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
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

        $category = $this->selectableCategories()->findOrFail($categoryId);

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

    /** What a rule would be created for: the account number (with the name), else the name, else the description. */
    public function ruleTarget(): ?string
    {
        $transaction = $this->transaction;

        return match (CategoryMatcher::ruleField($transaction)) {
            MatchField::Account => trim("{$transaction->counterparty} · ".__('STK :account', ['account' => $transaction->counterparty_account]), ' ·'),
            MatchField::Counterparty => $transaction->counterparty,
            MatchField::Description => $transaction->description,
        };
    }

    /** @return Builder<Category> */
    private function selectableCategories(): Builder
    {
        return auth()->user()
            ->availableCategories()
            ->where('type', $this->transaction->direction->categoryType());
    }

    public function render()
    {
        $rawEmail = $this->transaction->rawEmail;

        return view('livewire.transactions.show', [
            'categories' => $this->selectableCategories()->orderByRaw('user_id is null desc')->orderBy('id')->get(),
            'bankName' => config("banks.{$this->transaction->bank_code}.name", $this->transaction->bank_code),
            'rawText' => $this->showRaw && $rawEmail ? $rawEmail->toParserInput()->text : null,
            'rawEmail' => $rawEmail,
        ])->title(__('Chi tiết giao dịch'));
    }
}
