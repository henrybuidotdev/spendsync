<?php

namespace App\Support;

use App\Exceptions\DemoDataIsReadOnly;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Users can switch to viewing the seeded demo account's transactions and reports. Demo data is read-only for everyone signed in. */
class Demo
{
    public static function user(): ?User
    {
        return once(fn () => User::firstWhere('email', config('services.demo.email')));
    }

    public static function isActive(): bool
    {
        if (! auth()->user()?->viewing_demo) {
            return false;
        }

        return self::user() !== null;
    }

    /** Whose transactions the current user is looking at. */
    public static function owner(): User
    {
        return self::isActive() ? self::user() : auth()->user();
    }

    public static function owns(Model $model): bool
    {
        $demoId = self::user()?->id;

        if ($demoId === null) {
            return false;
        }

        if ($model instanceof User) {
            return $model->id === $demoId;
        }

        return $model->getAttribute('user_id') === $demoId;
    }

    /** Seeding, imports and queued syncs run without a signed-in user and may still write demo data. */
    public static function ensureWritable(Model $model): void
    {
        if (! auth()->hasUser()) {
            return;
        }

        if (self::isSessionBookkeeping($model)) {
            return;
        }

        if (self::owns($model)) {
            throw new DemoDataIsReadOnly;
        }
    }

    /** Login with "remember me" and logout rotate the remember token, even on the demo account. */
    private static function isSessionBookkeeping(Model $model): bool
    {
        if (! $model instanceof User) {
            return false;
        }

        if (! $model->exists) {
            return false;
        }

        return array_keys($model->getDirty()) === ['remember_token'];
    }
}
