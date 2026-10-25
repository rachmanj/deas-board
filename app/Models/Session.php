<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Laravel\Sanctum\HasApiTokens;

class Session extends Model
{
    use HasApiTokens;

    protected $fillable = [
        'label',
        'topic_id',
        'chat_id',
        'host',
        'notes',
        'is_active',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assignee_session_id');
    }

    public function notifications(): MorphMany
    {
        return $this->morphMany(Notification::class, 'notifiable');
    }

    public function commentMentions(): MorphMany
    {
        return $this->morphMany(CommentMention::class, 'mentionable');
    }

    public function authoredComments(): MorphMany
    {
        return $this->morphMany(TaskComment::class, 'author');
    }

    public function createdTasks(): MorphMany
    {
        return $this->morphMany(Task::class, 'created_by');
    }

    public function taskUpdates(): MorphMany
    {
        return $this->morphMany(TaskUpdate::class, 'actor');
    }

    public function uploadedAttachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'uploaded_by');
    }

    public function activityLogs(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'actor');
    }

    public function activityLogSubjects(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject');
    }
}
