<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\LogsActivity;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

// app/Models/Appointment.php
class Appointment extends Model
{
    use HasFactory;

    use SoftDeletes;

    use LogsActivity;

    protected $fillable = [
        'customer_id',
        'employee_id',
        'discount_id',
        'appointment_date',
        'appointment_time',
        'status',
        'payment_method',
        'payment_status',
        'total_amount'
    ];

    protected $casts = [
        'appointment_date' => 'date',
        'appointment_time' => 'datetime:H:i',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function discount()
    {
        return $this->belongsTo(Discount::class);
    }

    public function services()
    {
        return $this->belongsToMany(Service::class)->withPivot('quantity')->withTimestamps();
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    /**
     * Check and deduct inventory for all services in this appointment.
     * @throws \Exception
     */
    public function deductInventory()
    {
        $productsToDeduct = [];

        // Sum required quantities per product from all services in this appointment
        foreach ($this->services as $service) {
            foreach ($service->products as $product) {
                $productId = $product->id;
                $required = $product->pivot->quantity_used;
                $productsToDeduct[$productId] = ($productsToDeduct[$productId] ?? 0) + $required;
            }
        }

        if (empty($productsToDeduct)) {
            return; // no products used, nothing to deduct
        }

        // Check stock sufficiency
        foreach ($productsToDeduct as $productId => $neededQty) {
            $product = Product::find($productId);
            if (!$product) {
                throw new \Exception("Product ID {$productId} not found.");
            }
            if ($product->quantity < $neededQty) {
                throw new \Exception("Insufficient stock for product: {$product->name}. Available: {$product->quantity}, Required: {$neededQty}");
            }
        }

        // Perform deduction inside a transaction (caller will wrap in DB::transaction)
        foreach ($productsToDeduct as $productId => $neededQty) {
            Product::where('id', $productId)->decrement('quantity', $neededQty);
        }
    }
}
