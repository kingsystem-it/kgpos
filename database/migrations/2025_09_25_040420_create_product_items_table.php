<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('product_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            // produto composto (pai) -> item (filho)
            $table->unsignedBigInteger('product_id');        // pai
            $table->unsignedBigInteger('child_product_id');  // filho
            $table->decimal('quantity', 10, 3)->default(1.000);
            $table->timestamps();

            $table->unique(['product_id','child_product_id']);
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('child_product_id')->references('id')->on('products')->restrictOnDelete();
        });
    }

    public function down(): void {
        Schema::dropIfExists('product_items');
    }
};
