<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TranslationOverride extends Model
{
    protected $table = 'translation_overrides';

    protected $fillable = ['locale', 'key', 'value'];
}
