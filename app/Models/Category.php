<?php

namespace App\Models;

use App\Enums\CategoryType;
use App\Enums\Direction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'parent_id', 'name', 'icon', 'type', 'order'])]
class Category extends Model
{
    use HasFactory;

    /** Debt/loan categories are fixed system rows (users can't add them), and each one moves money one way. */
    public const DEBT_LOAN_DIRECTIONS = [
        'Lending' => Direction::Out,
        'Debt repayment' => Direction::Out,
        'Borrowing' => Direction::In,
        'Debt collection' => Direction::In,
    ];

    protected function casts(): array
    {
        return [
            'type' => CategoryType::class,
        ];
    }

    /** Shared system categories (user_id NULL) plus the user's own. */
    #[Scope]
    protected function availableTo(Builder $query, User $user): void
    {
        $query->where(fn (Builder $query) => $query->whereNull('user_id')->orWhere('user_id', $user->id));
    }

    /** Higher order first; ties keep the old order: system categories, then the user's own, oldest first. */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderByDesc('order')->orderByRaw('user_id is null desc')->orderBy('id');
    }

    /** Categories that make sense for the direction: expense or income, plus the debt/loan ones going the same way. */
    #[Scope]
    protected function fitting(Builder $query, Direction $direction): void
    {
        $debtLoanNames = array_keys(array_filter(self::DEBT_LOAN_DIRECTIONS, fn (Direction $debtLoanDirection) => $debtLoanDirection === $direction));

        $query->where(fn (Builder $query) => $query
            ->where('type', $direction->categoryType())
            ->orWhere(fn (Builder $query) => $query->where('type', CategoryType::DebtLoan)->whereNull('user_id')->whereIn('name', $debtLoanNames)));
    }

    public function fits(Direction $direction): bool
    {
        if ($this->type === CategoryType::DebtLoan) {
            return $this->isSystem() && (self::DEBT_LOAN_DIRECTIONS[$this->name] ?? null) === $direction;
        }

        return $this->type === $direction->categoryType();
    }

    #[Scope]
    protected function system(Builder $query): void
    {
        $query->whereNull('user_id');
    }

    /** Display name: system categories are translated, a user's own are shown as typed. */
    protected function label(): Attribute
    {
        return Attribute::get(fn () => $this->isSystem() ? __($this->name) : $this->name);
    }

    /** One of the 8 chart colors, fixed per category so icons and reports match. */
    protected function color(): Attribute
    {
        return Attribute::get(fn () => $this->id % 8 + 1);
    }

    public function isSystem(): bool
    {
        return $this->user_id === null;
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function rules(): HasMany
    {
        return $this->hasMany(CategoryRule::class);
    }
}
