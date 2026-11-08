<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IndexNowSubmission extends Model
{
    protected $fillable = [
        'poster_import_id', 'source', 'status', 'urls_count', 'error', 'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'urls_count' => 'integer',
            'submitted_at' => 'datetime',
        ];
    }

    public function posterImport(): BelongsTo
    {
        return $this->belongsTo(PosterImport::class);
    }
}
