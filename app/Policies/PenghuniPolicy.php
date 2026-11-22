<?php

namespace App\Policies;

use App\Models\Penghuni;
use App\Models\Properti;
use App\Models\User;
use App\Services\PengaturanService;

class PenghuniPolicy
{
    public function __construct(
        private readonly PengaturanService $pengaturanService,
    ) {}

    public function viewAny(User $user, Properti $properti): bool
    {
        return $user->bisaAksesProperti($properti);
    }

    public function view(User $user, Penghuni $penghuni): bool
    {
        return $user->bisaAksesProperti($penghuni->properti_id);
    }

    public function kelolaKontrak(User $user, Properti|Penghuni $target): bool
    {
        $propertiId = $target instanceof Properti ? $target->id : $target->properti_id;

        if (! $user->bisaAksesProperti($propertiId)) {
            return false;
        }

        if ($user->isPenjaga()) {
            return $this->pengaturanService->penjagaBolehKelolaKontrak($propertiId);
        }

        return true;
    }

    public function kelolaPenghuni(User $user, Properti|Penghuni $target): bool
    {
        return $this->kelolaKontrak($user, $target);
    }

    public function sesuaikanKuota(User $user, Penghuni $penghuni): bool
    {
        return $user->bisaAksesProperti($penghuni->properti_id);
    }
}
