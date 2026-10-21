<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Generation extends Model
{
    protected $fillable = [
        'status',
        'original_name',
        'settings',
        'result_id',
        'palette',
        'diagnostics',
        'error',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'palette' => 'array',
            'diagnostics' => 'array',
            'completed_at' => 'datetime',
        ];
    }
}
