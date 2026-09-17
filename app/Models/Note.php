<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Note extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'client_id',
        'title',
        'content',
        'tags',
        'is_favorite',
        'is_pinned',
        'updated_at',
        'sync_attempts',
        'last_sync_error',
        'last_synced_at',
        'deleted_at',
    ];

    protected $casts = [
        'tags' => 'array',
        'is_favorite' => 'boolean',
        'is_pinned' => 'boolean',
        'updated_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
}