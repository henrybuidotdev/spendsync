<?php

namespace App\Models;

use App\Enums\CategoryType;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use Notifiable;

    public const DEFAULT_CATEGORIES = [
        ['Ăn uống', '🍜', CategoryType::Expense],
        ['Đi lại', '🛵', CategoryType::Expense],
        ['Mua sắm', '🛍️', CategoryType::Expense],
        ['Hóa đơn', '🧾', CategoryType::Expense],
        ['Nhà cửa', '🏠', CategoryType::Expense],
        ['Giải trí', '🎮', CategoryType::Expense],
        ['Sức khỏe', '💊', CategoryType::Expense],
        ['Giáo dục', '📚', CategoryType::Expense],
        ['Chuyển khoản', '🔁', CategoryType::Expense],
        ['Khác', '📦', CategoryType::Expense],
        ['Lương', '💰', CategoryType::Income],
        ['Thu khác', '➕', CategoryType::Income],
    ];

    protected static function booted(): void
    {
        static::created(fn (User $user) => $user->seedDefaultCategories());
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function seedDefaultCategories(): void
    {
        $this->categories()->createMany(array_map(fn (array $category) => [
            'name' => $category[0],
            'icon' => $category[1],
            'type' => $category[2],
        ], self::DEFAULT_CATEGORIES));
    }

    public function mailAccount(): HasOne
    {
        return $this->hasOne(MailAccount::class)->latestOfMany();
    }

    public function mailAccounts(): HasMany
    {
        return $this->hasMany(MailAccount::class);
    }

    public function rawEmails(): HasMany
    {
        return $this->hasMany(RawEmail::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function categoryRules(): HasMany
    {
        return $this->hasMany(CategoryRule::class);
    }
}
