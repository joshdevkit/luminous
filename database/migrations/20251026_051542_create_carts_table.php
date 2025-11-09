<?php

use Core\Database\Schema\Migration;
use Core\Database\Schema\Blueprint;

class CreateCartsTable extends Migration
{
    public function up(): void
    {
        $this->schema->create('carts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('product_id');
            $table->integer('quantity')->default(1);
            $table->timestamps();


            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        $this->schema->drop('carts');
    }
}
