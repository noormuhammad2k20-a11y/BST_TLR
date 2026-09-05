<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ClothStore\Category;
use App\Models\ClothStore\Product;
use Illuminate\Support\Str;

class FabricProductsSeeder extends Seeder
{
    public function run()
    {
        $brands = [
            'Miandad Fabrics' => [
                'Supreme Boski',
                'King Wool',
                'Kashmiri Wool',
                'Tehzeeb',
                'Fontana',
                'Pioneer By Miandad',
                'Elegant w/w',
                'Hitachi',
                'Makhmal',
            ],
            'Grace Fabrics' => [
                'Summer Wibe',
                'Shadow Pearl',
                'Imperial',
                'Play Boy',
                'Master Kong',
                'Ivory Strom',
                'Crystal Wave',
                'Noble Frost',
                'London Twill',
                'Bell Line Cotton',
                'Turkiya Cotton',
            ],
            'Wijdan' => [
                'Cool Breeze',
                'Onyx By Wijdan',
                'Passion',
                'Charcoal',
                'Gloria',
            ],
            'Narkin\'s' => [
                'Rang-e-Mehfil D-01',
                'Rang-e-Mehfil D-02',
                'Rang-e-Mehfil D-03',
                'Rang-e-Mehfil D-04',
                'Rang-e-Mehfil D-05',
            ],
            'Gul Ahmed' => [
                'Chairman Latha',
                'Bemisaal Good Luck',
                'Gul 900 Castor',
                'GUL Panther',
                'Vision Opera',
            ],
        ];

        foreach ($brands as $brandName => $products) {
            $category = Category::firstOrCreate(['name' => $brandName], [
                'name' => $brandName,
                'color_bg' => '#3b82f6',
                'color_text' => '#ffffff',
            ]);

            foreach ($products as $name) {
                Product::firstOrCreate(['name' => $name, 'cs_category_id' => $category->id], [
                    'name' => $name,
                    'cs_category_id' => $category->id,
                    'price' => 4200,
                    'cost_price' => 3000,
                    'stock_quantity' => 100,
                    'unit' => 'meter',
                    'status' => 'Active'
                ]);
            }
        }
    }
}
