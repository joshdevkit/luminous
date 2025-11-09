<?php

use Core\Database\Schema\Migration;
use Core\Database\Schema\Blueprint;

class CreateOrderItemsTable extends Migration
{
    public function up(): void
    {
        $this->schema->create('order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('product_id');
            $table->integer('quantity')->default(1);
            $table->decimal('price', 10, 2);
            $table->decimal('subtotal', 10, 2);
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        $this->schema->drop('order_items');
    }
}
