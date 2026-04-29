<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'email',
        'phone_e164',
        'phone_hash',
        'email_hash',
        'password',
        'share_successes',
        'share_failures',
        'share_streaks',
        'discoverable_by_contacts',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'phone_hash',
        'email_hash',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'share_successes' => 'boolean',
            'share_failures' => 'boolean',
            'share_streaks' => 'boolean',
            'discoverable_by_contacts' => 'boolean',
        ];
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function activeDevice(): HasOne
    {
        return $this->hasOne(Device::class)->where('is_active', true);
    }

    public function stats(): HasOne
    {
        return $this->hasOne(UserStats::class);
    }

    public function hostedSessions(): HasMany
    {
        return $this->hasMany(Session::class, 'host_user_id');
    }

    public function participations(): HasMany
    {
        return $this->hasMany(SessionParticipant::class);
    }

    public function feedItems(): HasMany
    {
        return $this->hasMany(FeedItem::class);
    }
}
