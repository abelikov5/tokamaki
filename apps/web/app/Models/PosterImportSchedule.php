<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PosterImportSchedule extends Model
{
    protected $fillable = [
        'enabled', 'source_user_id', 'scheduled_time', 'timezone', 'random_count',
        'image_limit', 'only_new', 'generate_ai', 'last_run_at',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'source_user_id' => 'integer',
            'random_count' => 'integer',
            'image_limit' => 'integer',
            'only_new' => 'boolean',
            'generate_ai' => 'boolean',
            'last_run_at' => 'datetime',
        ];
    }

    public static function current(): self
    {
        return self::query()->firstOrCreate(['id' => 1], [
            'enabled' => true,
            'source_user_id' => 1,
            'scheduled_time' => '03:30',
            'timezone' => 'Europe/Moscow',
            'random_count' => 20,
            'image_limit' => 3,
            'only_new' => true,
            'generate_ai' => true,
        ]);
    }
}
