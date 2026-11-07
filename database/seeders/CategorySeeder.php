<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $tree = [
            'Tools & Equipment' => ['Power Tools', 'Pressure Washers', 'Ladders'],
            'Photography & Video' => ['Cameras', 'Lenses', 'Lighting'],
            'Events & Party' => ['Tents & Canopies', 'Sound Systems', 'Decor'],
            'Outdoor & Sports' => ['Camping Gear', 'Bicycles', 'Water Sports'],
            'Vehicles' => ['Cars', 'Motorcycles'],
            'Electronics' => ['Projectors', 'Drones'],
            'Home Appliances' => ['Cleaning Equipment', 'Kitchen Appliances'],
        ];

        foreach ($tree as $name => $subcategories) {
            $category = Category::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name]);

            foreach ($subcategories as $subcategoryName) {
                Category::firstOrCreate(['slug' => Str::slug($subcategoryName)], [
                    'name' => $subcategoryName,
                    'parent_id' => $category->id,
                ]);
            }
        }
    }
}
