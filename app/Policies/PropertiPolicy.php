<?php

namespace App\Policies;

use App\Models\Properti;
use App\Models\User;

class PropertiPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isDevAdmin() || $user->isOwner();
    }

    public function view(User $user, Properti $properti): bool
    {
        return $user->bisaAksesProperti($properti);
    }

    public function create(User $user): bool
    {
        return $user->isDevAdmin() || $user->isOwner();
    }

    public function update(User $user, Properti $properti): bool
    {
        if ($user->isDevAdmin()) {
            return true;
        }

        return $user->isOwner() && $properti->owner_id === $user->id;
    }

    public function delete(User $user, Properti $properti): bool
    {
        return $this->update($user, $properti);
    }
}
