<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionParticipant extends Model
{
    use HasFactory;

    public const ROLE_HOST = 'host';
    public const ROLE_GUEST = 'guest';

    protected $fillable = [
        'session_id',
        'user_id',
        'device_id',
        'role',
        'lock_confirmed_at',
        'end_confirmed_at',
        'last_heartbeat_at',
        'last_integrity_state',
        'protection_active',
    ];

    protected function casts(): array
    {
        return [
            'lock_confirmed_at' => 'datetime',
            'end_confirmed_at' => 'datetime',
            'last_heartbeat_at' => 'datetime',
            'last_integrity_state' => 'array',
            'protection_active' => 'boolean',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
