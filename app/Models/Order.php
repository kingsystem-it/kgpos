<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = ['customer_id','anchor','status','subtotal','discount','total','closed_at','canceled_at'];
    public function items(){ return $this->hasMany(OrderItem::class); }
}
