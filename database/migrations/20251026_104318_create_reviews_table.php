<?php

use Core\Database\Schema\Migration;
use Core\Database\Schema\Blueprint;

class CreateReviewsTable extends Migration
{
    public function up(): void
    {
        $this->schema->create('reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('product_id');

            $table->tinyInteger('rating')->unsigned();
            $table->text('comment')->nullable();

            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        $this->schema->drop('reviews');
    }
}
