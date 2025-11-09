<?php

use Core\Database\Schema\Migration;
use Core\Database\Schema\Blueprint;

class CreateOrdersTable extends Migration
{
    public function up(): void
    {
        $this->schema->create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->unsignedBigInteger('user_id');
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('shipping_cost', 10, 2)->default(0);
            $table->enum('status', ['pending', 'cancelled', 'processed', 'otw', 'delivered'])->default('pending'); 
            $table->enum('payment_method', ['cash', 'cod', 'e-waller'])->nullable(); // e.g. COD, GCash, PayPal
            $table->text('shipping_address')->nullable();
            $table->timestamps();


            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        $this->schema->drop('orders');
    }
}
