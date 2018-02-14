<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Entry extends Model
{
    protected $table = 'entry';
    protected $fillable = ['element'];

    protected static function boot() {
        static::created(function($entry) {
            $entry->afterSave($entry);
        });
    }

    public function user()
    {
        return $this->belongsTo('App\User');
    }

    public function topic()
    {
        return $this->belongsToMany('App\Topic')->using('App\EntryTopic')->withTimestamps();
    }

    public function pages()
    {
        return $this->hasMany('App\Pages');
    }

    public function afterSave($entry)
    {
        $entry_format =  EntryFormats\Factory::create($entry);
        $error = $entry_format->saveCollateralEntities($entry);
        return $error;
    }

}
