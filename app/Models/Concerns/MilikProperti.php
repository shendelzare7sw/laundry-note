<?php

namespace App\Models\Concerns;

use App\Models\Properti;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait MilikProperti
{
    public function properti(): BelongsTo
    {
        return $this->belongsTo(Properti::class, 'properti_id');
    }

    public function scopeUntukProperti(Builder $query, Properti|int $properti): Builder
    {
        $propertiId = $properti instanceof Properti ? $properti->id : $properti;

        return $query->where($this->getTable().'.properti_id', $propertiId);
    }
}
