<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\LogsActivity;

// app/Models/Employee.php
class Employee extends Model
{
    use HasFactory;

    use LogsActivity;

    protected $fillable = [
        'user_id',
        'personal_email',
        'job_role_id',
        'first_name',
        'last_name',
        'birthdate',
        'mobile_num',
        'gender',
        'hire_date',
        'image'
    ];

    protected $casts = [
        'birthdate' => 'date',
        'hire_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function jobRole()
    {
        return $this->belongsTo(JobRole::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }
}
