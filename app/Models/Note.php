<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Note extends Model
{
    protected $fillable = [
        'client_id',
        'title',
        'content',
        'updated_at',
        'sync_attempts',
        'last_sync_error',
        'last_synced_at',
    ];

    protected $casts = [
        'updated_at' => 'datetime',
        'last_synced_at' => 'datetime',
    ];
}