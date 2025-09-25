<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('order_items', function (Blueprint $t) {
      $t->bigIncrements('id');
      $t->unsignedBigInteger('order_id');
      $t->unsignedBigInteger('product_id')->nullable();
      $t->string('name_snapshot',160);
      $t->decimal('price_snapshot',10,2);
      $t->decimal('quantity',10,3)->default(1.000);

      // KDS lite
      $t->string('status',20)->default('draft');   // draft|sent|prepared|served|voided
      $t->timestamp('sent_at')->nullable();
      $t->timestamp('prepared_at')->nullable();
      $t->timestamp('served_at')->nullable();
      $t->unsignedBigInteger('route_id_snapshot')->nullable()->index(); // vem de categories.printer_route_id

      $t->timestamps();

      $t->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
    });
  }
  public function down(): void { Schema::dropIfExists('order_items'); }
};
