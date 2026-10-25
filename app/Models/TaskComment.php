<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaskComment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'task_id',
        'parent_id',
        'body_md',
        'author_type',
        'author_id',
        'edited_at',
    ];

    protected function casts(): array
    {
        return [
            'edited_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(TaskComment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(TaskComment::class, 'parent_id');
    }

    public function author(): MorphTo
    {
        return $this->morphTo();
    }

    public function mentions(): HasMany
    {
        return $this->hasMany(CommentMention::class, 'comment_id');
    }

    public function notifications(): MorphMany
    {
        return $this->morphMany(Notification::class, 'subject');
    }

    public function activityLogSubjects(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject');
    }
}
