<?php

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(3, true));

        return [
            'shop_id' => Shop::factory(),
            'product_category_id' => ProductCategory::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'description' => fake()->paragraph(),
            'status' => ProductStatus::Live,
            'base_price' => '150.00',
            'weight_grams' => 500,
            'published_at' => now(),
        ];
    }

    public function status(ProductStatus $status): static
    {
        return $this->state(['status' => $status]);
    }

    /**
     * Every product gets one default variation, matching how sellers create products.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Product $product) {
            if (! $product->variations()->exists()) {
                $product->variations()->create([
                    'sku' => 'SKU-'.Str::upper(Str::random(8)),
                    'label' => 'Default',
                    'price' => $product->base_price,
                    'stock' => 20,
                    'is_default' => true,
                ]);
            }
        });
    }
}
