<?php

namespace Database\Dummy;

use App\Entities\Product;
use Core\Database\Generators\Seeder;
use Core\Facades\DB;

class ProductSeeder extends Seeder
{
    public function run(): void
    {

        $products = [];

        foreach (range(1, 50) as $i) {
            $products[] = Product::factory()
                ->state(['status' => $i <= 35 ? 1 : 0])
                ->definition();
        }

        DB::table('products')->insert($products);

    }
}
