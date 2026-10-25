<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailAttachment extends Model
{
    protected $fillable = [
        'email_id',
        'name',
        'path',
        'size',
        'mime',
        'sha256',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    public function email(): BelongsTo
    {
        return $this->belongsTo(Email::class);
    }
}
