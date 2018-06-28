<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserRights extends Model
{
  protected $table = 'user_rights';

  public function user() {
    return $this->belongsTo('App\User');
  }
}
