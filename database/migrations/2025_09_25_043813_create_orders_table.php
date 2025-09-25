<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('orders', function (Blueprint $t) {
      $t->bigIncrements('id');
      $t->unsignedBigInteger('customer_id')->nullable();
      $t->string('anchor', 50)->nullable();          // ex: mesa/cliente
      $t->string('status', 20)->default('open');     // open|closed|canceled
      $t->decimal('subtotal',10,2)->default(0);
      $t->decimal('discount',10,2)->default(0);
      $t->decimal('total',10,2)->default(0);
      $t->timestamp('closed_at')->nullable();
      $t->timestamp('canceled_at')->nullable();
      $t->timestamps();
    });
  }
  public function down(): void { Schema::dropIfExists('orders'); }
};
