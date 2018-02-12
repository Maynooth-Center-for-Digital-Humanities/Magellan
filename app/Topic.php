<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Topic extends Model
{
    protected $table = 'topic';

    public function entries()
    {
        return $this->belongsToMany('App\Entry')->using('App\EntryTopic');
    }
}
