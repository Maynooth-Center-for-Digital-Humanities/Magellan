<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Uploadedfile;
use App\EntryTopic;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class Entry extends Model
{
    protected $table = 'entry';
    protected $fillable = ['element'];

    protected static function boot() {
        static::created(function($entry) {
          $entry->afterSave($entry);
        });

        static::updating(function($entry) {
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
        $format = json_decode($entry->element)->type;
        $entry_format =  EntryFormats\Factory::create($format);
        $error = $entry_format->saveCollateralEntities($entry);
        return $error;
    }

    public function deleteEntry($entry, $auth_user_id) {
      if (intval($auth_user_id)===0) {
        $error = "You do not have permission to delete this letter";
        return $error;
      }
      $entry_id = $entry->id;
      $current_user_id = $auth_user_id;
      $create_user_id = $entry->user_id;
      if ($current_user_id!==$create_user_id) {
        $error = "You do not have permission to delete this letter";
        return $error;
      }
      $element = json_decode($entry->element, true);
      // delete pages
      $pages = $element['pages'];
      foreach($pages as $page) {
        // delete images
        Storage::disk('fullsize')->delete($page['archive_filename']);
        Storage::disk('thumbnails')->delete($page['archive_filename']);
      }

      // delete xml files
      $file_id = $entry->uploadedfile_id;
      if ($file_id!==null) {
        $uploaded_file = Uploadedfile::where('id',$file_id);
        Storage::disk('xml_public')->delete($uploaded_file['filename']);
        $uploaded_file->delete();
      }
      // delete entry
      $entry->delete();

      return "Entry deleted successfully";
    }

    public function transcriptionUsers() {
        return $this->belongsToMany('App\User', 'user_transcriptions', 'entry_id', 'user_id')->withTimestamps();
    }

    public function entryLock()
    {
        return $this->belongsToMany('App\User','entry_locks')->withTimestamps();
    }

    public function handleEntryLock() {
      // check entryLock
      $userId = Auth::user()->id;
      $lock = $this->entryLock;
      if (!empty(sizeof($lock)) && $lock!==null && $lock[0]->pivot->user_id!==$userId) {
        $locked_time = strtotime($lock[0]->pivot->updated_at);
        $ten_minutes_pause = strtotime("+15 minutes", $locked_time);
        $now = strtotime(date("Y-m-d H:i:s"));

        if ($now<$ten_minutes_pause) {
          return false;
        }
        else {
          $this->entryLock()->sync([$userId]);
        }
      }
      else {
        $this->entryLock()->sync([$userId]);
      }
      return true;
    }

}
