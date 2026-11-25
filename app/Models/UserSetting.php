<?php

namespace App\Models;

use App\Enums\Locale;
use App\Enums\Theme;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['locale', 'theme'])]
class UserSetting extends Model
{
    protected $attributes = [
        'locale' => 'vi',
        'theme' => 'system',
    ];

    protected function casts(): array
    {
        return [
            'locale' => Locale::class,
            'theme' => Theme::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
