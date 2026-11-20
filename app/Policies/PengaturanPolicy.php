<?php

namespace App\Policies;

use App\Models\Properti;
use App\Models\User;

class PengaturanPolicy
{
    /**
     * Hanya DevAdmin, Owner (properti miliknya), dan Admin (properti penempatannya) yang boleh mengelola pengaturan.
     */
    public function manage(User $user, Properti $properti): bool
    {
        return $user->bisaAksesProperti($properti) && ! $user->isPenjaga();
    }
}
