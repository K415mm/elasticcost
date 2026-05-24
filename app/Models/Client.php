<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    protected $fillable = ['name', 'description'];

    public function clientAssets(): HasMany
    {
        return $this->hasMany(ClientAsset::class);
    }
}
