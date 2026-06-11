<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'remember_token' => bin2hex(random_bytes(10)),
            ]
        );

        $categories = [
            [
                'name' => 'Fruits & Légumes',
                'description' => 'Produits frais et locaux pour votre panier quotidien.',
                'icon_emoji' => '🥬',
                'image_url' => 'https://images.unsplash.com/photo-1506806732259-39c2d0268443?auto=format&fit=crop&w=800&q=80',
                'display_order' => 1,
                'is_active' => true,
            ],
            [
                'name' => 'Viandes & Poissons',
                'description' => 'Viandes, poissons et produits frais de la mer.',
                'icon_emoji' => '🍖',
                'image_url' => 'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?auto=format&fit=crop&w=800&q=80',
                'display_order' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Boissons',
                'description' => 'Jus, boissons fraîches et rafraîchissements.',
                'icon_emoji' => '🥤',
                'image_url' => 'https://images.unsplash.com/photo-1576402187878-1f38fd4ed3f2?auto=format&fit=crop&w=800&q=80',
                'display_order' => 3,
                'is_active' => true,
            ],
        ];

        foreach ($categories as $categoryData) {
            Category::updateOrCreate(
                ['name' => $categoryData['name']],
                $categoryData
            );
        }

        $products = [
            [
                'name' => 'Mangue fraîche',
                'description' => 'Mangue juteuse et sucrée, parfaite pour le dessert.',
                'category_name' => 'Fruits & Légumes',
                'price_type' => 'kg',
                'base_price' => 1800,
                'stock_quantity' => 50,
                'low_stock_threshold' => 5,
                'is_available' => true,
                'image_url' => 'https://images.unsplash.com/photo-1572556360226-9da392d64430?auto=format&fit=crop&w=1200&q=90',
                'is_bestseller' => true,
                'popularity_score' => 98,
            ],
            [
                'name' => 'Banane plantain',
                'description' => 'Banane plantain mûre, idéale pour frire ou rôtir.',
                'category_name' => 'Fruits & Légumes',
                'price_type' => 'kg',
                'base_price' => 900,
                'stock_quantity' => 120,
                'low_stock_threshold' => 10,
                'is_available' => true,
                'image_url' => 'https://images.unsplash.com/photo-1506806732259-39c2d0268443?auto=format&fit=crop&w=1200&q=90',
                'is_bestseller' => false,
                'popularity_score' => 65,
            ],
            [
                'name' => 'Tomates locales',
                'description' => 'Tomates rouges, cultivées localement.',
                'category_name' => 'Fruits & Légumes',
                'price_type' => 'kg',
                'base_price' => 1200,
                'stock_quantity' => 80,
                'low_stock_threshold' => 8,
                'is_available' => true,
                'image_url' => 'https://images.unsplash.com/photo-1528825871115-3581a5387919?auto=format&fit=crop&w=1200&q=90',
                'is_bestseller' => false,
                'popularity_score' => 70,
            ],
            [
                'name' => 'Poulet fermier',
                'description' => 'Poulet fermier entier, élevé en plein air.',
                'category_name' => 'Viandes & Poissons',
                'price_type' => 'piece',
                'base_price' => 2800,
                'stock_quantity' => 30,
                'low_stock_threshold' => 5,
                'is_available' => true,
                'image_url' => 'https://images.unsplash.com/photo-1604908177522-4b3b12a1153a?auto=format&fit=crop&w=1200&q=90',
                'is_bestseller' => true,
                'popularity_score' => 87,
            ],
            [
                'name' => 'Filet de saumon',
                'description' => 'Filet de saumon frais, qualité sashimi.',
                'category_name' => 'Viandes & Poissons',
                'price_type' => 'kg',
                'base_price' => 8200,
                'stock_quantity' => 25,
                'low_stock_threshold' => 3,
                'is_available' => true,
                'image_url' => 'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?auto=format&fit=crop&w=1200&q=90',
                'is_bestseller' => false,
                'popularity_score' => 80,
            ],
            [
                'name' => 'Jus dâ€™orange',
                'description' => 'Jus dâ€™orange pressé 100% naturel.',
                'category_name' => 'Boissons',
                'price_type' => 'piece',
                'base_price' => 900,
                'stock_quantity' => 200,
                'low_stock_threshold' => 20,
                'is_available' => true,
                'image_url' => 'https://images.unsplash.com/photo-1470337458703-46ad1756a187?auto=format&fit=crop&w=1200&q=90',
                'is_bestseller' => true,
                'popularity_score' => 85,
            ],
            [
                'name' => 'Eau minérale',
                'description' => 'Bouteille dâ€™eau minérale 1.5L.',
                'category_name' => 'Boissons',
                'price_type' => 'piece',
                'base_price' => 300,
                'stock_quantity' => 500,
                'low_stock_threshold' => 50,
                'is_available' => true,
                'image_url' => 'https://images.unsplash.com/photo-1505577058444-a3dab9723a0f?auto=format&fit=crop&w=1200&q=90',
                'is_bestseller' => false,
                'popularity_score' => 60,
            ],
            [
                'name' => 'Carottes bio',
                'description' => 'Carottes croquantes, parfaites pour salades et cuissons.',
                'category_name' => 'Fruits & Légumes',
                'price_type' => 'kg',
                'base_price' => 800,
                'stock_quantity' => 90,
                'low_stock_threshold' => 10,
                'is_available' => true,
                'image_url' => 'https://images.unsplash.com/photo-1506806732259-39c2d0268443?auto=format&fit=crop&w=1200&q=90',
                'is_bestseller' => false,
                'popularity_score' => 58,
            ],
            [
                'name' => 'Riz parfumé',
                'description' => 'Riz parfumé 5kg, cuisson parfaite.',
                'category_name' => 'Fruits & Légumes',
                'price_type' => 'bag',
                'base_price' => 5200,
                'stock_quantity' => 60,
                'low_stock_threshold' => 6,
                'is_available' => true,
                'image_url' => 'https://images.unsplash.com/photo-1542444459-db5a8d3a0d6b?auto=format&fit=crop&w=1200&q=90',
                'is_bestseller' => true,
                'popularity_score' => 90,
            ],
            [
                'name' => 'Oeufs fermiers (12)',
                'description' => 'Boîte de 12 oeufs fermiers.',
                'category_name' => 'Fruits & Légumes',
                'price_type' => 'box',
                'base_price' => 1500,
                'stock_quantity' => 150,
                'low_stock_threshold' => 15,
                'is_available' => true,
                'image_url' => 'https://images.unsplash.com/photo-1503922717007-5f9e6f4e0a3d?auto=format&fit=crop&w=1200&q=90',
                'is_bestseller' => false,
                'popularity_score' => 75,
            ],
        ];

        foreach ($products as $productData) {
            $category = Category::where('name', $productData['category_name'])->first();
            if (! $category) {
                continue;
            }

            $attributes = $productData;
            unset($attributes['category_name']);
            $attributes['category_id'] = $category->id;

            Product::updateOrCreate(
                ['name' => $productData['name']],
                $attributes
            );
        }
    }
}
