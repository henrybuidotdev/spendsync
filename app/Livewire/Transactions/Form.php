<?php

namespace App\Livewire\Transactions;

use App\Categorization\CategoryMatcher;
use App\Enums\Direction;
use App\Livewire\Concerns\BlocksDemoWrites;
use App\Models\Category;
use App\Models\Transaction;
use App\Support\Demo;
use App\Support\Format;
use Carbon\CarbonImmutable;
use Closure;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Adds or edits a manual transaction (cash, unsupported banks). Synced transactions are never edited here. */
#[Layout('layouts.app')]
class Form extends Component
{
    use BlocksDemoWrites;

    private const DATE_FORMAT = 'Y-m-d\TH:i';

    public ?Transaction $transaction = null;

    public Direction $direction = Direction::Out;

    /** Digits grouped with dots, the Vietnamese way, e.g. "1.250.000". */
    public string $amount = '';

    /** Local time, as a datetime-local input sends it. */
    public string $occurredAt = '';

    public string $counterparty = '';

    public ?int $categoryId = null;

    public string $note = '';

    public function mount(?Transaction $transaction = null): void
    {
        if ($transaction === null) {
            $this->occurredAt = CarbonImmutable::now(config('app.display_timezone'))->format(self::DATE_FORMAT);

            return;
        }

        $this->authorizeEdit($transaction);

        $this->direction = $transaction->direction;
        $this->amount = number_format($transaction->amount, 0, ',', '.');
        $this->occurredAt = Format::local($transaction->occurred_at)->format(self::DATE_FORMAT);
        $this->counterparty = (string) $transaction->counterparty;
        $this->categoryId = $transaction->category_id;
        $this->note = (string) $transaction->note;
    }

    public function setDirection(string $direction): void
    {
        $this->direction = Direction::tryFrom($direction) ?? Direction::Out;

        if ($this->selectedCategory() === null) {
            $this->categoryId = null;
        }
    }

    /** Tapping the selected category again clears it. */
    public function setCategory(int $categoryId): void
    {
        $this->categoryId = $this->categoryId === $categoryId ? null : $categoryId;
    }

    public function save(CategoryMatcher $matcher): void
    {
        if ($this->transaction) {
            $this->authorizeEdit($this->transaction);
        }

        $this->validate([
            'amount' => ['required', 'string', 'max:20', 'regex:/^[1-9]\d{0,2}(\.?\d{3})*$/'],
            'occurredAt' => ['required', 'date_format:'.self::DATE_FORMAT],
            'counterparty' => ['required', 'string', 'max:255'],
            'categoryId' => ['nullable', 'integer', function (string $attribute, mixed $value, Closure $fail): void {
                if ($this->selectedCategory() === null) {
                    $fail(__('Pick a category that fits the transaction type.'));
                }
            }],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [
            'amount.regex' => __('Enter an amount greater than 0.'),
        ]);

        $isNew = $this->transaction === null;
        $note = trim($this->note);

        $transaction = $this->transaction ?? Demo::owner()->transactions()->make(['bank_code' => Transaction::MANUAL]);

        $transaction->fill([
            'direction' => $this->direction,
            'amount' => (int) str_replace('.', '', $this->amount),
            'occurred_at' => CarbonImmutable::createFromFormat('!'.self::DATE_FORMAT, $this->occurredAt, config('app.display_timezone'))->utc(),
            'counterparty' => trim($this->counterparty),
            'category_id' => $this->categoryId,
            'note' => $note !== '' ? $note : null,
        ])->save();

        if ($isNew && $this->categoryId === null) {
            $matcher->apply($transaction);
        }

        session()->flash('status', $isNew ? __('Transaction added.') : __('Transaction saved.'));

        $this->redirectRoute('transactions.show', $transaction, navigate: true);
    }

    private function authorizeEdit(Transaction $transaction): void
    {
        $this->authorize('update', $transaction);

        abort_unless($transaction->isManual(), 404);
    }

    private function selectedCategory(): ?Category
    {
        if ($this->categoryId === null) {
            return null;
        }

        return Demo::owner()->availableCategories()->fitting($this->direction)->find($this->categoryId);
    }

    public function render()
    {
        return view('livewire.transactions.form', [
            'categories' => Demo::owner()->availableCategories()->fitting($this->direction)->ordered()->get(),
            'backUrl' => $this->transaction ?
                route('transactions.show', $this->transaction) :
                route('transactions'),
        ])->title($this->transaction ? __('Edit transaction') : __('Add transaction'));
    }
}
