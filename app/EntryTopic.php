<?php

namespace App;

use Illuminate\Database\Eloquent\Relations\Pivot;

class EntryTopic extends Pivot
{
    protected $table = 'entrytopic';
}
