<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserTranscription extends Model
{
  protected $table = 'user_transcriptions';

  public function user() {
    return $this->belongsTo('App\User');
  }
}
