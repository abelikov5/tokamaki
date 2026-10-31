<?php

namespace App\Models;

use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected $fillable = [
        'generation_id',
        'status',
        'email',
        'city',
        'street',
        'designer_processing',
        'amount',
        'original_name',
        'original_image_path',
        'generated_image_path',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'designer_processing' => 'boolean',
            'amount' => 'integer',
        ];
    }

    public function generation(): BelongsTo
    {
        return $this->belongsTo(Generation::class);
    }

    public function adminImageUrl(string $kind): string
    {
        return route('admin.orders.image', [$this, $kind]);
    }
}
