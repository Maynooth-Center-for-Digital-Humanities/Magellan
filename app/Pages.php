<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Pages extends Model
{
    public function entry()
    {
        return $this->belongsTo('App\Entry');
    }
}
