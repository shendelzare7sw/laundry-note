<?php

namespace App\Policies;

use App\Models\Laundry;
use App\Models\Properti;
use App\Models\User;
use App\Services\PengaturanService;

class LaundryPolicy
{
    public function __construct(
        private readonly PengaturanService $pengaturanService,
    ) {}

    public function viewAny(User $user, Properti $properti): bool
    {
        return $user->bisaAksesProperti($properti);
    }

    public function create(User $user, Properti $properti): bool
    {
        return $user->bisaAksesProperti($properti);
    }

    /**
     * Owner/Admin bebas mengubah; Penjaga hanya catatan miliknya dalam batas `jam_edit_penjaga`.
     */
    public function update(User $user, Laundry $laundry): bool
    {
        if (! $user->bisaAksesProperti($laundry->properti_id)) {
            return false;
        }

        if (! $user->isPenjaga()) {
            return true;
        }

        return $this->masihDalamBatasPenjaga($user, $laundry);
    }

    public function delete(User $user, Laundry $laundry): bool
    {
        return $this->update($user, $laundry);
    }

    public function restore(User $user, Laundry $laundry): bool
    {
        return $user->bisaAksesProperti($laundry->properti_id) && ! $user->isPenjaga();
    }

    public function tetapkanPemilik(User $user, Laundry $laundry): bool
    {
        return $this->update($user, $laundry);
    }

    public function lengkapiHarga(User $user, Properti $properti): bool
    {
        return $user->bisaAksesProperti($properti) && ! $user->isPenjaga();
    }

    public function lihatTerhapus(User $user, Properti $properti): bool
    {
        return $this->lengkapiHarga($user, $properti);
    }

    private function masihDalamBatasPenjaga(User $user, Laundry $laundry): bool
    {
        if ($laundry->created_at === null) {
            return false;
        }

        $bolehCatatanLain = $this->pengaturanService->penjagaBolehUbahCatatanLain($laundry->properti_id);

        if ($laundry->dicatat_oleh !== $user->id && ! $bolehCatatanLain) {
            return false;
        }

        $jamBatas = max(1, (int) $this->pengaturanService->nilai($laundry->properti_id, 'jam_edit_penjaga', null, '24'));

        return $laundry->created_at->copy()->addHours($jamBatas)->isFuture();
    }
}
