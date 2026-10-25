<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'title',
        'description_md',
        'status',
        'priority',
        'assignee_session_id',
        'project_id',
        'due_date',
        'start_date',
        'done_at',
        'source',
        'external_ref',
        'created_by_type',
        'created_by_id',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'start_date' => 'date',
            'done_at' => 'datetime',
        ];
    }

    public function assigneeSession(): BelongsTo
    {
        return $this->belongsTo(Session::class, 'assignee_session_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function createdBy(): MorphTo
    {
        return $this->morphTo('created_by');
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class, 'task_labels');
    }

    public function taskLabels(): HasMany
    {
        return $this->hasMany(TaskLabel::class);
    }

    public function updates(): HasMany
    {
        return $this->hasMany(TaskUpdate::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function emailLinks(): HasMany
    {
        return $this->hasMany(EmailTaskLink::class);
    }

    public function emails(): BelongsToMany
    {
        return $this->belongsToMany(Email::class, 'email_task_links')
            ->withPivot('linked_at');
    }

    public function activityLogSubjects(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject');
    }
}
