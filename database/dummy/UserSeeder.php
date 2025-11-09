<?php

namespace Database\Dummy;

use Core\Database\Generators\Seeder;
use Database\Generators\UserFactory;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        UserFactory::new()->count(50)->create();
    }
}