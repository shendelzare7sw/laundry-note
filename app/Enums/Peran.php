<?php

namespace App\Enums;

enum Peran: string
{
    case DevAdmin = 'dev_admin';
    case Owner = 'owner';
    case Admin = 'admin';
    case Penjaga = 'penjaga';

    public function label(): string
    {
        return match ($this) {
            self::DevAdmin => 'Developer Admin',
            self::Owner => 'Owner',
            self::Admin => 'Admin Properti',
            self::Penjaga => 'Penjaga',
        };
    }

    public function isGlobal(): bool
    {
        return match ($this) {
            self::DevAdmin, self::Owner => true,
            self::Admin, self::Penjaga => false,
        };
    }

    public function isStafProperti(): bool
    {
        return ! $this->isGlobal();
    }
}
