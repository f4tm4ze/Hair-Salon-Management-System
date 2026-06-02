<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\LogsActivity;

// app/Models/Service.php
class Service extends Model
{
    use HasFactory;

    use SoftDeletes;

    use LogsActivity;

    protected $dates = ['deleted_at'];

    protected $fillable = ['name', 'description', 'price', 'duration', 'category', 'image', 'status'];

    public function appointments()
    {
        return $this->belongsToMany(Appointment::class)->withPivot('quantity')->withTimestamps();
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_service')
            ->withPivot('quantity_used')
            ->withTimestamps();
    }
}
