<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id','product_id','name_snapshot','price_snapshot','quantity',
        'status','sent_at','prepared_at','served_at','route_id_snapshot'
    ];
    public function order(){ return $this->belongsTo(Order::class); }
}
