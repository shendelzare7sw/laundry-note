<?php

namespace App\Models;

use App\Models\Concerns\MilikProperti;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'properti_id',
    'kunci',
    'nilai',
    'berlaku_mulai',
    'diubah_oleh',
])]
class Pengaturan extends Model
{
    use HasFactory, MilikProperti;

    protected $table = 'pengaturan';

    protected function casts(): array
    {
        return [
            'berlaku_mulai' => 'date',
        ];
    }

    public function pengubah(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diubah_oleh');
    }
}
