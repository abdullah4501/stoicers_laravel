<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
protected $fillable = [
    'customer_id',
    'order_number',
    'total_price',
    'status',
    'payment_status',
    'customer_name',
    'customer_email',
    'customer_phone',
    'customer_address',
    'customer_area',
    'customer_city',
];



    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
}
