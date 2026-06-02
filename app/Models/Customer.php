<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\LogsActivity;

// app/Models/Customer.php
class Customer extends Model
{
    use HasFactory;

    use LogsActivity;

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'birthdate',
        'mobile_num',
        'gender',
        'image',
        'loyalty_discount_available'
    ];

    protected $casts = [
        'birthdate' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function discounts()
    {
        return $this->belongsToMany(Discount::class)->withPivot('used_count')->withTimestamps();
    }
}
