<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\LogsActivity;

// app/Models/Discount.php
class Discount extends Model
{
    use HasFactory;

    use SoftDeletes;

    use LogsActivity;

    protected $fillable = [
        'name',
        'code',
        'type',
        'value',
        'start_date',
        'end_date',
        'status',
        'usage_limit'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function isAvailable()
    {
        if ($this->status !== 'active') return false;
        if ($this->start_date && $this->start_date > now()) return false;
        if ($this->end_date && $this->end_date < now()) return false;
        return true;
    }

    public function customers()
    {
        return $this->belongsToMany(Customer::class)->withPivot('used_count')->withTimestamps();
    }

    // Get usage count for a specific customer
    public function usageForCustomer(Customer $customer)
    {
        $record = $this->customers()->where('customer_id', $customer->id)->first();
        return $record ? $record->pivot->used_count : 0;
    }

    // Check if discount is available for a customer
    public function isAvailableForCustomer(Customer $customer)
    {
        if ($this->status !== 'active') return false;
        if ($this->start_date && $this->start_date > now()) return false;
        if ($this->end_date && $this->end_date < now()) return false;
        if ($this->usage_limit && $this->usageForCustomer($customer) >= $this->usage_limit) return false;
        return true;
    }

    // Increment usage for a customer
    public function incrementUsageForCustomer(Customer $customer)
    {
        $record = $this->customers()->where('customer_id', $customer->id)->first();
        if ($record) {
            $record->pivot->increment('used_count');
        } else {
            $this->customers()->attach($customer->id, ['used_count' => 1]);
        }
    }

    // Decrement usage for a customer
    public function decrementUsageForCustomer(Customer $customer)
    {
        $record = $this->customers()->where('customer_id', $customer->id)->first();
        if ($record && $record->pivot->used_count > 0) {
            $record->pivot->decrement('used_count');
            if ($record->pivot->used_count == 0) {
                $this->customers()->detach($customer->id);
            }
        }
    }
}
