<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::table('order_items', function (Blueprint $t) {
      $t->index(['order_id','product_id','status','price_snapshot'], 'oi_consolidation_idx');
    });
  }
  public function down(): void {
    Schema::table('order_items', function (Blueprint $t) {
      $t->dropIndex('oi_consolidation_idx');
    });
  }
};
