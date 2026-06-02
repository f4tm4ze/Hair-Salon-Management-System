<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Traits\LogsActivity;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;


class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    use LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
        'invitation_token',
        'invitation_sent_at',
        'invitation_accepted_at',
        'invitation_expires_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
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
            'password' => 'hashed',
        ];
    }

    // app/Models/User.php
    public function customer()
    {
        return $this->hasOne(Customer::class);
    }

    public function employee()
    {
        return $this->hasOne(Employee::class);
    }

    protected $casts = [
        'invitation_sent_at' => 'datetime',
        'invitation_accepted_at' => 'datetime',
        'invitation_expires_at' => 'datetime',
    ];

    public function generateInvitationToken()
    {
        $this->invitation_token = Str::random(64);
        $this->invitation_sent_at = now();
        $this->invitation_expires_at = now()->addDays(7); // Link valid for 7 days
        $this->status = 'invited'; // Add 'invited' to possible statuses
        $this->save();
    }

    public function isInvitationValid()
    {
        return $this->invitation_token &&
            !$this->invitation_accepted_at &&
            $this->invitation_expires_at?->isFuture();
    }

    public function acceptInvitation($password)
    {
        $this->password = Hash::make($password);
        $this->invitation_accepted_at = now();
        $this->invitation_token = null;
        $this->status = 'active';
        $this->save();
    }
}
