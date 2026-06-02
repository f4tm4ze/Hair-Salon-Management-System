<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;

class ProductSeeder extends Seeder
{
    public function run()
    {
        $products = [
            // Hair Care
            ['name' => 'Shampoo', 'category' => 'Shampoo', 'size' => '250ml', 'quantity' => 15, 'price' => 120.00],
            ['name' => 'Shampoo', 'category' => 'Shampoo', 'size' => '500ml', 'quantity' => 8, 'price' => 220.00],
            ['name' => 'Conditioner', 'category' => 'Conditioner', 'size' => '250ml', 'quantity' => 12, 'price' => 130.00],
            ['name' => 'Conditioner', 'category' => 'Conditioner', 'size' => '500ml', 'quantity' => 5, 'price' => 240.00],
            ['name' => 'Hair Mask', 'category' => 'Treatment Products', 'size' => '200ml', 'quantity' => 7, 'price' => 350.00],
            ['name' => 'Hair Oil', 'category' => 'Treatment Products', 'size' => '100ml', 'quantity' => 10, 'price' => 180.00],

            // Hair Color
            ['name' => 'Permanent Hair Color', 'category' => 'Hair Color', 'size' => '60ml', 'quantity' => 20, 'price' => 250.00],
            ['name' => 'Semi-Permanent Color', 'category' => 'Hair Color', 'size' => '60ml', 'quantity' => 18, 'price' => 220.00],

            // Styling Tools
            ['name' => 'Hair Dryer', 'category' => 'Styling Tools', 'size' => '1800W', 'quantity' => 3, 'price' => 1500.00],
            ['name' => 'Flat Iron', 'category' => 'Styling Tools', 'size' => '1 inch', 'quantity' => 4, 'price' => 1200.00],
            ['name' => 'Curling Wand', 'category' => 'Styling Tools', 'size' => '25mm', 'quantity' => 2, 'price' => 1300.00],

            // Nail Products
            ['name' => 'Nail Polish - Red', 'category' => 'Nail Products', 'size' => '15ml', 'quantity' => 25, 'price' => 80.00],
            ['name' => 'Nail Polish - Pink', 'category' => 'Nail Products', 'size' => '15ml', 'quantity' => 22, 'price' => 80.00],
            ['name' => 'Base Coat', 'category' => 'Nail Products', 'size' => '15ml', 'quantity' => 10, 'price' => 90.00],
            ['name' => 'Top Coat', 'category' => 'Nail Products', 'size' => '15ml', 'quantity' => 9, 'price' => 90.00],
            ['name' => 'Nail Polish Remover', 'category' => 'Nail Products', 'size' => '250ml', 'quantity' => 6, 'price' => 120.00],

            // Others
            ['name' => 'Plastic Comb', 'category' => 'Others', 'size' => 'Set of 4', 'quantity' => 20, 'price' => 150.00],
            ['name' => 'Hair Clips', 'category' => 'Others', 'size' => 'Set of 6', 'quantity' => 30, 'price' => 60.00],
            ['name' => 'Apron', 'category' => 'Others', 'size' => 'One size', 'quantity' => 5, 'price' => 250.00],
        ];

        foreach ($products as $product) {
            Product::create($product);
        }
    }
}
