<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserStats extends Model
{
    use HasFactory;

    protected $table = 'user_stats';

    protected $fillable = [
        'user_id',
        'total_success_seconds',
        'best_session_seconds',
        'current_streak_count',
        'longest_streak_count',
        'failed_session_count',
        'successful_session_count',
        'last_success_at',
    ];

    protected function casts(): array
    {
        return [
            'last_success_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
