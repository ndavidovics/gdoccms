<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedItem extends Model
{
    use HasFactory;

    public const TYPE_SESSION_SUCCESS = 'session_success';
    public const TYPE_SESSION_FAILED = 'session_failed';
    public const TYPE_STREAK_MILESTONE = 'streak_milestone';
    public const TYPE_FRIEND_JOINED = 'friend_joined';

    public const VISIBILITY_FRIENDS = 'friends';
    public const VISIBILITY_PRIVATE = 'private';

    public $timestamps = false;

    protected $fillable = ['user_id', 'session_id', 'type', 'visibility', 'payload', 'created_at'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }
}
