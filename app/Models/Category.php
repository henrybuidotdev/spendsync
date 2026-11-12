<?php

namespace App\Models;

use App\Enums\CategoryType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'name', 'icon', 'type', 'order'])]
class Category extends Model
{
    use HasFactory;

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

    public function isSystem(): bool
    {
        return $this->user_id === null;
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
