<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class BenineseCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Légumes racines & Tubercules',
                'description' => 'Igname, manioc, patate douce et autres tubercules locaux.',
                'icon_emoji' => '🧺',
                'image_url' => '/images/categories/tubercules.svg',
                'display_order' => 7,
                'is_active' => true,
            ],
            [
                'name' => 'Céréales & Grains',
                'description' => 'Maïs, mil, sorgho, riz et autres céréales.',
                'icon_emoji' => '🌾',
                'image_url' => '/images/categories/cereales.svg',
                'display_order' => 8,
                'is_active' => true,
            ],
            [
                'name' => 'Épices & Condiments',
                'description' => 'Poivre, piment, gingembre, sel et sauces.',
                'icon_emoji' => '🧂',
                'image_url' => '/images/categories/epices.svg',
                'display_order' => 9,
                'is_active' => true,
            ],
            [
                'name' => 'Produits de la mer',
                'description' => 'Poissons frais, crustacés et produits transformés.',
                'icon_emoji' => '🐟',
                'image_url' => '/images/categories/mer.svg',
                'display_order' => 10,
                'is_active' => true,
            ],
            [
                'name' => 'Feuilles & Sauces',
                'description' => 'Feuilles locales pour sauces et accompagnements.',
                'icon_emoji' => '🥬',
                'image_url' => '/images/categories/feuilles.svg',
                'display_order' => 11,
                'is_active' => true,
            ],
        ];

        foreach ($categories as $c) {
            Category::updateOrCreate(['name' => $c['name']], $c);
        }

        // Sample products for new categories
        $samples = [
            [
                'name' => 'Igname local',
                'description' => 'Igname blanc traditionnel.',
                'category' => 'Légumes racines & Tubercules',
                'price_type' => 'kg',
                'base_price' => 1300,
                'stock_quantity' => 80,
                'low_stock_threshold' => 8,
                'is_available' => true,
                'image_url' => '/images/categories/tubercules.svg',
            ],
            [
                'name' => 'Maïs sec',
                'description' => 'Maïs sec pour préparation locale.',
                'category' => 'Céréales & Grains',
                'price_type' => 'bag',
                'base_price' => 2400,
                'stock_quantity' => 60,
                'low_stock_threshold' => 6,
                'is_available' => true,
                'image_url' => '/images/categories/cereales.svg',
            ],
            [
                'name' => 'Piment séché',
                'description' => 'Piment local pour sauces et marinades.',
                'category' => 'Épices & Condiments',
                'price_type' => 'bag',
                'base_price' => 600,
                'stock_quantity' => 150,
                'low_stock_threshold' => 20,
                'is_available' => true,
                'image_url' => '/images/categories/epices.svg',
            ],
            [
                'name' => 'Poisson frais (petit)',
                'description' => 'Poisson du jour, petite taille.',
                'category' => 'Produits de la mer',
                'price_type' => 'piece',
                'base_price' => 900,
                'stock_quantity' => 70,
                'low_stock_threshold' => 7,
                'is_available' => true,
                'image_url' => '/images/categories/mer.svg',
            ],
        ];

        foreach ($samples as $p) {
            $cat = Category::where('name', $p['category'])->first();
            if (! $cat) continue;
            $attrs = $p;
            unset($attrs['category']);
            $attrs['category_id'] = $cat->id;
            Product::updateOrCreate(['name' => $p['name']], $attrs);
        }
    }
}
