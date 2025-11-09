<?php

use Core\Database\Schema\Migration;
use Core\Database\Schema\Blueprint;

class CreateProductsTable extends Migration
{
    public function up(): void
    {
        $this->schema->create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->text('description');
            $table->decimal('price', 10, 2);
            $table->decimal('cost_price', 10, 2);
            $table->string('sku');
            $table->string('barcode');
            $table->integer('quantity');
            $table->string('category');
            $table->string('brand');
            $table->boolean('status');
            $table->decimal('weight', 8, 2);
            $table->string('dimensions');
            $table->string('image_url');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->schema->drop('products');
    }
}
