<?php

namespace App\Policies;

use App\Models\CategoryRule;
use App\Models\User;

class CategoryRulePolicy
{
    public function view(User $user, CategoryRule $categoryRule): bool
    {
        return $user->id === $categoryRule->user_id;
    }

    public function update(User $user, CategoryRule $categoryRule): bool
    {
        return $user->id === $categoryRule->user_id;
    }

    public function delete(User $user, CategoryRule $categoryRule): bool
    {
        return $user->id === $categoryRule->user_id;
    }
}
