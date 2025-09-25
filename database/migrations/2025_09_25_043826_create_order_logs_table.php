<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('order_logs', function (Blueprint $t) {
      $t->bigIncrements('id');
      $t->unsignedBigInteger('order_id')->index();
      $t->unsignedBigInteger('user_id')->nullable();
      $t->string('action',60); // created|item_added|sent|prepared|served|canceled...
      $t->json('context')->nullable();
      $t->timestamp('created_at')->useCurrent();

      $t->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
    });
  }
  public function down(): void { Schema::dropIfExists('order_logs'); }
};
