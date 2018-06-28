<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class EntryLock extends Model
{
  protected $table = 'entry_locks';

  public function entry()
  {
     return $this->belongsTo('App\Entry');
  }
}
