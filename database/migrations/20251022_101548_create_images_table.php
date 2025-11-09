<?php

use Core\Database\Schema\Migration;
use Core\Database\Schema\Blueprint;

class CreateImagesTable extends Migration
{
    public function up(): void
    {
        $this->schema->create('images', function (Blueprint $table) {
            $table->id();
            $table->string('url');
            $table->morphs('imageable');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->schema->drop('images');
    }
}
