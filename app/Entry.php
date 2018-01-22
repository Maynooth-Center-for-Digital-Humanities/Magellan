<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Entry extends Model
{
    protected $table = 'entry';
    protected $fillable = ['element'];

    public function user()
    {
        return $this->belongsTo('App\User');
    }



}
