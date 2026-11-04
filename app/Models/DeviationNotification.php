<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviationNotification extends Model
{
    protected $fillable = [
        'category',
        'subject_type',
        'subject_id',
        'notified_on',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'notified_on' => 'date',
            'payload' => 'array',
        ];
    }
}
