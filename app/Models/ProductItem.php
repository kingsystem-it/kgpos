<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductItem extends Model
{
    protected $fillable = ['product_id','child_product_id','quantity'];

    public function product() { return $this->belongsTo(Product::class, 'product_id'); }
    public function child() { return $this->belongsTo(Product::class, 'child_product_id'); }
}
