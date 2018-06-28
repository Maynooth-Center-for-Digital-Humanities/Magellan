<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Rights extends Model
{
    protected $table = 'rights';
    protected $fillable = [
        'can_update', 'label', 'text', 'status'
    ];
}
