<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Session extends Model
{
    use HasFactory;

    public const STATE_CREATED = 'created';
    public const STATE_PENDING_SECOND_USER = 'pending_second_user';
    public const STATE_READY_TO_LOCK = 'ready_to_lock';
    public const STATE_ACTIVE = 'active';
    public const STATE_SUCCESS = 'success';
    public const STATE_FAILED = 'failed';
    public const STATE_CANCELLED = 'cancelled';

    public const FAILURE_PROTECTION_DISABLED = 'protection_disabled';
    public const FAILURE_HEARTBEAT_TIMEOUT = 'heartbeat_timeout';
    public const FAILURE_DEVICE_MISMATCH = 'device_mismatch';
    public const FAILURE_EMERGENCY_EXIT = 'emergency_exit';
    public const FAILURE_PERMISSION_REVOKED = 'permission_revoked';
    public const FAILURE_CANCELLED_BEFORE_START = 'cancelled_before_start';

    protected $fillable = [
        'uuid',
        'state',
        'host_user_id',
        'started_at',
        'ended_at',
        'failed_at',
        'failure_reason',
        'failed_by_user_id',
        'end_requested_by_user_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'failed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_user_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(SessionParticipant::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(SessionEvent::class)->orderByDesc('id');
    }

    public function isTerminal(): bool
    {
        return in_array($this->state, [
            self::STATE_SUCCESS,
            self::STATE_FAILED,
            self::STATE_CANCELLED,
        ], true);
    }

    public function durationSeconds(): int
    {
        if (! $this->started_at) {
            return 0;
        }
        $end = $this->ended_at ?? $this->failed_at ?? now();

        return max(0, $end->diffInSeconds($this->started_at));
    }
}
