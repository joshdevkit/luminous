<?php

namespace Database\Generators;

use Core\Database\Generators\Factory;
use Core\Support\Str;
use Faker\Factory as Faker;

/**
 * @extends Factory<\App\Entities\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $faker = Faker::create();

        return [
            'name' => $faker->word(),
            'slug' => Str::slug($faker->word()),
            'description' => $faker->sentence(),
            'price' => $faker->randomFloat(2, 100, 2000),
            'cost_price' => $faker->randomFloat(2, 50, 1000),
            'sku' => $faker->unique()->bothify('??-###'),
            'barcode' => $faker->ean13(),
            'quantity' => $faker->numberBetween(10, 500),
            'category' => $faker->word(),
            'brand' => $faker->company(),
            'status' => $faker->randomElement([0, 1]),
            'weight' => $faker->randomFloat(2, 0.1, 5) . 'kg',
            'dimensions' => $faker->randomFloat(1, 5, 50) . 'x' . $faker->randomFloat(1, 5, 50) . 'x' . $faker->randomFloat(1, 5, 50) . ' cm',
            'image_url' => 'https://placehold.co/600x400?text=' . urlencode($faker->word()),
        ];
    }
}
