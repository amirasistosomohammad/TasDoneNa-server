<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at', // allow email verification to be saved via update()
        'role',
        'status',
        'is_active',
        'rejection_reason',
        'approval_remarks',
        'deactivation_reason',
        'approved_at',
        'rejected_at',
        'deactivated_at',
        'activated_at',
        'activation_remarks',
        'employee_id',
        'position',
        'division',
        'school_name',
        'avatar_url',
        'profile_avatar_url',
        'school_logo_url',
        'otp',
        'otp_expires_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'rejection_reason',
        'deactivation_reason',
        'otp',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'activated_at' => 'datetime',
            'otp_expires_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Check if user is approved (can log in).
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved' || $this->role === 'admin';
    }
}
