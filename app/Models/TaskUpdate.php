<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TaskUpdate extends Model
{
    protected $fillable = [
        'task_id',
        'from_status',
        'to_status',
        'note',
        'evidence',
        'actor_type',
        'actor_id',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function actor(): MorphTo
    {
        return $this->morphTo();
    }
}
