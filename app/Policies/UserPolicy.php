<?php

namespace App\Policies;

use App\Enums\Peran;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return ! $user->isPenjaga();
    }

    public function create(User $user): bool
    {
        return ! $user->isPenjaga();
    }

    public function kelolaPeran(User $actor, Peran $targetRole, ?int $targetPropertiId): bool
    {
        if ($actor->isDevAdmin()) {
            return true;
        }

        if ($actor->isOwner()) {
            if (! in_array($targetRole, [Peran::Admin, Peran::Penjaga], true)) {
                return false;
            }

            if ($targetPropertiId === null) {
                return false;
            }

            return $actor->propertiDimiliki()->whereKey($targetPropertiId)->exists();
        }

        if ($actor->isAdmin()) {
            if ($targetRole !== Peran::Penjaga) {
                return false;
            }

            return $actor->properti_id !== null && $targetPropertiId === $actor->properti_id;
        }

        return false;
    }

    public function update(User $actor, User $target): bool
    {
        if ($actor->id === $target->id) {
            return false;
        }

        if ($actor->isDevAdmin()) {
            return true;
        }

        return $this->kelolaPeran($actor, $target->role, $target->properti_id);
    }
}
