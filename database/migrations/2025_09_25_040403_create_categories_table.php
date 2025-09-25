<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('categories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->integer('sort_order')->default(0);
            $table->string('color_hex', 7)->nullable();   // ex: #FF6600
            $table->string('icon', 50)->nullable();
            $table->boolean('visible_pos')->default(true);
            $table->boolean('visible_online')->default(false);
            $table->unsignedBigInteger('printer_route_id')->nullable()->index(); // para futuro/KDS
            $table->string('image_path')->nullable();
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('categories')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::dropIfExists('categories');
    }
};
