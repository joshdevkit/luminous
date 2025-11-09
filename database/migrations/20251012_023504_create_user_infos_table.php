<?php

use Core\Database\Schema\Migration;
use Core\Database\Schema\Blueprint;

class CreateUserInfosTable extends Migration
{
    public function up(): void
    {
        $this->schema->create('user_infos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            
            $table->timestamps();
            
            // Foreign key constraint
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->cascadeOnDelete();
            
        });
    }

    public function down(): void
    {
        $this->schema->drop('user_infos');
    }
}