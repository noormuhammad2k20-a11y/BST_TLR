<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TailorCategory extends Model
{
    protected $guarded = ['id'];

    public function rates(): HasMany
    {
        return $this->hasMany(TailorRate::class);
    }
}
