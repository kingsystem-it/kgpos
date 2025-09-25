<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('pos_settings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('currency', 8)->default('EUR');
            $table->string('ui_language', 5)->default('it');
            $table->boolean('printing_enabled')->default(false);
            $table->boolean('kds_enabled')->default(true);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('pos_settings'); }
};
