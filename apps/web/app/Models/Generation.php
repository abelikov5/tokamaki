<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Generation extends Model
{
    protected $fillable = [
        'status',
        'visitor_id',
        'ip_address',
        'user_agent',
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
        'edits_count',
        'last_activity_at',
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
            'last_activity_at' => 'datetime',
        ];
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }

    public function adminPreviewUrl(): ?string
    {
        if ($this->status !== 'completed' || blank($this->result_id)) {
            return null;
        }

        return route('admin.generations.preview', [
            'generation' => $this,
            'v' => $this->result_id,
        ]);
    }

    protected static function booted(): void
    {
        static::creating(function (Generation $generation): void {
            $generation->resume_token ??= (string) Str::uuid();
        });
    }
}
