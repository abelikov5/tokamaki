<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Generation extends Model
{
    protected $fillable = [
        'status',
        'original_name',
        'settings',
        'result_id',
        'previous_result_id',
        'palette',
        'previous_palette',
        'diagnostics',
        'previous_diagnostics',
        'error',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'palette' => 'array',
            'previous_palette' => 'array',
            'diagnostics' => 'array',
            'previous_diagnostics' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Generation $generation): void {
            $generation->resume_token ??= (string) Str::uuid();
        });
    }
}
