<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\LogsActivity;

// app/Models/JobRole.php
class JobRole extends Model
{
    use HasFactory;

    use LogsActivity;

    protected $fillable = ['title', 'description'];

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }
}
