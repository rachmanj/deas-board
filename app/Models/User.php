<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
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
