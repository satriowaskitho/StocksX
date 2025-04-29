<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Str;
use App\Models\ProductCategory;
use App\Models\ProductSupplier;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create users
        User::factory(2)->create();

        // Create product categories with real values
        $categories = [
            ['title' => 'Perlengkapan Rumah Tangga', 'slug' => 'rt'],
            ['title' => 'Peralatan Komputer', 'slug' => 'pc'],
            ['title' => 'Alat Tulis Kantor', 'slug' => 'atk'],
            ['title' => 'Perlengkapan Kesehatan', 'slug' => 'kesehatan'],
            ['title' => 'Perlengkapan Cetak', 'slug' => 'cetak'],
        ];
        foreach ($categories as $category) {
            ProductCategory::create($category);
        }

        // Create product suppliers with real values
        $suppliers = [
            ['name' => 'Supplier A', 'email' => 'supplierA@example.com', 'phone' => '1234567890', 'category_id' => 1],
            ['name' => 'Supplier B', 'email' => 'supplierB@example.com', 'phone' => '0987654321', 'category_id' => 2],
            ['name' => 'Supplier C', 'email' => 'supplierC@example.com', 'phone' => '1122334455', 'category_id' => 3],
        ];
        foreach ($suppliers as $supplier) {
            ProductSupplier::create($supplier);
        }

        // Create products with real values
        $products = [
            ['name' => 'Tisu', 'slug' => Str::slug("Tisu"), 'price' => 2000, 'quantity' => 50, 'product_categories_id' => 1, 'product_suppliers_id' => 1, 'image' => 'tisu.jpg'],
            ['name' => 'Mouse', 'slug' => Str::slug("Mouse"), 'price' => 50000, 'quantity' => 20, 'product_categories_id' => 2, 'product_suppliers_id' => 2, 'image' => 'mouse.jpg'],
            ['name' => 'Kertas A4 1 Rim', 'slug' => Str::slug("Kertas A4 1 Rim"), 'price' => 80000, 'quantity' => 100, 'product_categories_id' => 3, 'product_suppliers_id' => 3, 'image' => 'kertas.jpg'],
            ['name' => 'Masker', 'slug' => Str::slug("Masker"), 'price' => 2000, 'quantity' => 40, 'product_categories_id' => 4, 'product_suppliers_id' => 1, 'image' => 'masker.jpg'],
            ['name' => 'Brosur', 'slug' => Str::slug("Brosur"), 'price' => 10000, 'quantity' => 150, 'product_categories_id' => 5, 'product_suppliers_id' => 2, 'image' => 'brosur.jpg'],
        ];
        foreach ($products as $product) {
            Product::create($product);
        }

        // Generate orders using factories
        $orders = Order::factory(53)->create();
    }
}
