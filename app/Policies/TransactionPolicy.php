<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;
use App\Support\Demo;

class TransactionPolicy
{
    public function view(User $user, Transaction $transaction): bool
    {
        return $this->canReach($user, $transaction);
    }

    public function update(User $user, Transaction $transaction): bool
    {
        return $this->canReach($user, $transaction);
    }

    public function delete(User $user, Transaction $transaction): bool
    {
        return $this->canReach($user, $transaction);
    }

    /** Demo transactions open in demo mode; saving or deleting them is refused by Demo::ensureWritable(). */
    private function canReach(User $user, Transaction $transaction): bool
    {
        if ($user->id === $transaction->user_id) {
            return true;
        }

        return Demo::isActive() && Demo::owns($transaction);
    }
}
