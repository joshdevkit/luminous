<?php

namespace Database\Dummy;

use Core\Database\Generators\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            ProductSeeder::class
        ]);
    }
}
