<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\LogsActivity;

// app/Models/Payment.php
class Payment extends Model
{
    use HasFactory;

    use LogsActivity;

    protected $fillable = ['appointment_id', 'amount', 'payment_method', 'reference_number', 'validated_at', 'status'];

    protected $casts = [
        'validated_at' => 'datetime',
    ];

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }
}
