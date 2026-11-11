<?php

namespace App\Policies;

use App\Models\MailAccount;
use App\Models\User;

class MailAccountPolicy
{
    public function view(User $user, MailAccount $mailAccount): bool
    {
        return $user->id === $mailAccount->user_id;
    }

    public function update(User $user, MailAccount $mailAccount): bool
    {
        return $user->id === $mailAccount->user_id;
    }

    public function delete(User $user, MailAccount $mailAccount): bool
    {
        return $user->id === $mailAccount->user_id;
    }
}
