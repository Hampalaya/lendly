<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

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
            if ($category->parent_id !== null) {
                throw new RuntimeException('Category slug '.Str::slug($name).' already belongs to a subcategory.');
            }

            foreach ($subcategories as $subcategoryName) {
                $subcategory = Category::firstOrCreate(['slug' => Str::slug($subcategoryName)], [
                    'name' => $subcategoryName,
                    'parent_id' => $category->id,
                ]);
                if ((int) $subcategory->parent_id !== $category->id) {
                    throw new RuntimeException('Subcategory slug '.$subcategory->slug.' already belongs to a different category.');
                }
            }
        }
    }
}
