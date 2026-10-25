<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Email extends Model
{
    protected $fillable = [
        'uid',
        'mailbox',
        'from_name',
        'from_email',
        'to',
        'cc',
        'subject',
        'received_at',
        'summary',
        'has_full_body',
        'body_text',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'has_full_body' => 'boolean',
        ];
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(EmailAttachment::class);
    }

    public function taskLinks(): HasMany
    {
        return $this->hasMany(EmailTaskLink::class);
    }

    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'email_task_links')
            ->withPivot('linked_at');
    }

    public function activityLogSubjects(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject');
    }
}
