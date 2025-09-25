<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'establishment_id','category_id','name','slug','type','active',
        'sku','barcode','price','promo_price','track_stock','stock_qty','min_stock',
        'visible_pos','image_path'
    ];

    public function category() { return $this->belongsTo(Category::class); }

    // composição (receita)
    public function items() { return $this->hasMany(ProductItem::class, 'product_id'); }
    public function components() {
        return $this->belongsToMany(Product::class, 'product_items', 'product_id', 'child_product_id')
                    ->withPivot('quantity');
    }
}
