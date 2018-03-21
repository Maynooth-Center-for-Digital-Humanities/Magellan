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

    static function getTopicsChildren($pid) {
      $results = array();
      $children = Topic::select('id', 'name', 'count')->where([
        ['parent_id', '=', $pid],
        ['count', '>', '0'],
        ])->get();

      foreach ($children as $child) {
        $child['children'] = Topic::getTopicsChildren($child['id']);
        $results[]=$child;
      }

      return $results;

    }
}
