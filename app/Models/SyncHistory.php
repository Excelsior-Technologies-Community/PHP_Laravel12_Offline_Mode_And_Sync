<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncHistory extends Model
{
    protected $fillable = [
        'operation',
        'total',
        'synced',
        'failed',
        'conflicts',
        'deleted',
        'status',
        'error_message',
    ];
}