<?php

namespace App\Models;

use App\Enums\MatchField;
use App\Parsing\Support\TextNormalizer;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'match_field', 'match_value', 'category_id'])]
class CategoryRule extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'match_field' => MatchField::class,
        ];
    }

    protected function matchValue(): Attribute
    {
        return Attribute::set(fn (string $value) => TextNormalizer::fold($value));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
