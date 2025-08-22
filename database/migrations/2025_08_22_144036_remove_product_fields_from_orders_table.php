<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
public function up()
{
    Schema::table('orders', function (Blueprint $table) {
        // First drop the foreign key
        $table->dropForeign(['product_id']);
        // Now drop the columns
        $table->dropColumn(['product_id', 'quantity', 'unit_price']);
    });
}
public function down()
{
    Schema::table('orders', function (Blueprint $table) {
        $table->unsignedBigInteger('product_id')->nullable();
        $table->integer('quantity')->nullable();
        $table->decimal('unit_price', 10, 2)->nullable();
    });
}

};
