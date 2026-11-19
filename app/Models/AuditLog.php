<?php

namespace App\Models;

use App\Models\Concerns\MilikProperti;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'properti_id',
    'user_id',
    'aksi',
    'subjek_type',
    'subjek_id',
    'sebelum',
    'sesudah',
    'ip',
    'created_at',
])]
class AuditLog extends Model
{
    use MilikProperti;

    protected $table = 'audit_log';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'sebelum' => 'array',
            'sesudah' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function subjek(): MorphTo
    {
        return $this->morphTo();
    }
}
