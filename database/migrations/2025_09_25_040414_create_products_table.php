<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('products', function (Blueprint $table) {
            $table->bigIncrements('id');
            // multi-estabelecimento preparado (sem FK por enquanto)
            $table->unsignedBigInteger('establishment_id')->default(1)->index();

            $table->unsignedBigInteger('category_id')->nullable()->index();
            $table->string('name', 160);
            $table->string('slug', 180)->unique();
            $table->string('type', 16)->default('simple'); // simple|composed
            $table->boolean('active')->default(true);

            $table->string('sku', 80)->nullable();
            $table->string('barcode', 80)->nullable();

            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('promo_price', 10, 2)->nullable();

            $table->boolean('track_stock')->default(false);
            $table->integer('stock_qty')->nullable();
            $table->integer('min_stock')->nullable();

            $table->boolean('visible_pos')->default(true);
            $table->string('image_path')->nullable();

            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::dropIfExists('products');
    }
};
