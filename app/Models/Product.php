<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\LogsActivity;

// app/Models/Product.php
class Product extends Model
{
    use HasFactory;

    use SoftDeletes;

    use LogsActivity;

    protected $fillable = ['name', 'category', 'size', 'quantity', 'price', 'status'];

    public function services()
    {
        return $this->belongsToMany(Service::class, 'product_service')
            ->withPivot('quantity_used')
            ->withTimestamps();
    }
}
