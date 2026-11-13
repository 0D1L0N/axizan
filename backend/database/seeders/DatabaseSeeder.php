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
                'image_url' => 'https://images.unsplash.com/photo-1506806732259-39c2d0268443?auto=format&fit=crop&w=1200&q=90',
                'display_order' => 1,
                'is_active' => true,
            ],
            [
                'name' => 'Viandes & Poissons',
                'description' => 'Viandes, poissons et produits frais de la mer.',
                'icon_emoji' => '🍖',
                'image_url' => 'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?auto=format&fit=crop&w=1200&q=90',
                'display_order' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Boissons',
                'description' => 'Jus, boissons fraîches et rafraîchissements.',
                'icon_emoji' => '🥤',
                'image_url' => 'https://images.unsplash.com/photo-1470337458703-46ad1756a187?auto=format&fit=crop&w=1200&q=90',
                'display_order' => 3,
                'is_active' => true,
            ],
            [
                'name' => 'Épicerie',
                'description' => 'Condiments, riz, huiles et ingrédients essentiels.',
                'icon_emoji' => '🛒',
                'image_url' => 'https://images.unsplash.com/photo-1598515214435-1ffd9733bde0?auto=format&fit=crop&w=1200&q=90',
                'display_order' => 4,
                'is_active' => true,
            ],
            [
                'name' => 'Produits locaux',
                'description' => 'Ingrédients et saveurs du terroir béninois.',
                'icon_emoji' => '🌽',
                'image_url' => 'https://images.unsplash.com/photo-1521302080397-1e5772361b9d?auto=format&fit=crop&w=1200&q=90',
                'display_order' => 5,
                'is_active' => true,
            ],
            [
                'name' => 'Boulangerie & Snacks',
                'description' => 'Pain, biscuits et encas faciles à emporter.',
                'icon_emoji' => '🥐',
                'image_url' => 'https://images.unsplash.com/photo-1525351484163-7529414344d8?auto=format&fit=crop&w=1200&q=90',
                'display_order' => 6,
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
                'name' => 'Carottes bio',
                'description' => 'Carottes croquantes, parfaites pour salades et cuissons.',
                'category_name' => 'Fruits & Légumes',
                'price_type' => 'kg',
                'base_price' => 800,
                'stock_quantity' => 90,
                'low_stock_threshold' => 10,
                'is_available' => true,
                'image_url' => 'https://images.unsplash.com/photo-1511690743698-d9d85f2fbf38?auto=format&fit=crop&w=1200&q=90',
                'is_bestseller' => false,
                'popularity_score' => 58,
            ],
            [
                'name' => 'Igname frais',
                'description' => 'Igname blanc tendre, parfait pour les plats traditionnels.',
                'category_name' => 'Produits locaux',
                'price_type' => 'kg',
                'base_price' => 1200,
                'stock_quantity' => 70,
                'low_stock_threshold' => 8,
                'is_available' => true,
                'image_url' => 'https://images.unsplash.com/photo-1542041509877-ec86bd7c9e05?auto=format&fit=crop&w=1200&q=90',
                'is_bestseller' => true,
                'popularity_score' => 92,
            ],
            [
                'name' => 'Poisson fumé',
                'description' => 'Poisson fumé local, délicieux dans le riz ou la soupe.',
                'category_name' => 'Viandes & Poissons',
                'price_type' => 'piece',
                'base_price' => 2200,
                'stock_quantity' => 40,
                'low_stock_threshold' => 6,
                'is_available' => true,
                'image_url' => 'https://images.unsplash.com/photo-1504674900247-0877df9cc836?auto=format&fit=crop&w=1200&q=90',
                'is_bestseller' => true,
                'popularity_score' => 91,
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
                'name' => 'Bœuf braisé',
                'description' => 'Bœuf mariné et grillé, prêt à déguster.',
                'category_name' => 'Viandes & Poissons',
                'price_type' => 'kg',
                'base_price' => 4500,
                'stock_quantity' => 25,
                'low_stock_threshold' => 4,
                'is_available' => true,
                'image_url' => 'https://images.unsplash.com/photo-1551183053-bf91a1d81141?auto=format&fit=crop&w=1200&q=90',
                'is_bestseller' => false,
                'popularity_score' => 78,
            ],
            [
                'name' => 'Riz parfumé',
                'description' => 'Riz parfumé de qualité supérieure, conditionné en sac de 5kg.',
                'category_name' => 'Épicerie',
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
                'name' => 'Huile d’arachide',
                'description' => 'Huile d’arachide pure pour la cuisson traditionnelle.',
                'category_name' => 'Épicerie',
                'price_type' => 'bottle',
                'base_price' => 2400,
                'stock_quantity' => 80,
                'low_stock_threshold' => 10,
                'is_available' => true,
                'image_url' => 'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?auto=format&fit=crop&w=1200&q=90',
                'is_bestseller' => false,
                'popularity_score' => 76,
            ],
            [
                'name' => 'Sel iodé',
                'description' => 'Sel iodé de table, indispensable en cuisine.',
                'category_name' => 'Épicerie',
                'price_type' => 'bag',
                'base_price' => 400,
                'stock_quantity' => 180,
                'low_stock_threshold' => 20,
                'is_available' => true,
                'image_url' => 'https://images.unsplash.com/photo-1506806732259-39c2d0268443?auto=format&fit=crop&w=1200&q=90',
                'is_bestseller' => false,
                'popularity_score' => 62,
            ],
            [
                'name' => 'Jus de bissap',
                'description' => 'Jus naturel de bissap, rafraîchissant et riche en saveurs.',
                'category_name' => 'Boissons',
                'price_type' => 'piece',
                'base_price' => 700,
                'stock_quantity' => 120,
                'low_stock_threshold' => 10,
                'is_available' => true,
                'image_url' => 'https://images.unsplash.com/photo-1551024601-bec78aea704b?auto=format&fit=crop&w=1200&q=90',
                'is_bestseller' => true,
                'popularity_score' => 85,
            ],
            [
                'name' => 'Jus de gingembre',
                'description' => 'Jus de gingembre épicé et rafraîchissant.',
                'category_name' => 'Boissons',
                'price_type' => 'piece',
                'base_price' => 800,
                'stock_quantity' => 100,
                'low_stock_threshold' => 8,
                'is_available' => true,
                'image_url' => 'https://images.unsplash.com/photo-1524594154903-1b4078b0b33d?auto=format&fit=crop&w=1200&q=90',
                'is_bestseller' => false,
                'popularity_score' => 71,
            ],
            [
                'name' => 'Eau minérale 1.5L',
                'description' => 'Bouteille d’eau minérale, pratique et rafraîchissante.',
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
                'name' => 'Pain frais',
                'description' => 'Pain fraîchement cuit, idéal pour le petit-déjeuner.',
                'category_name' => 'Boulangerie & Snacks',
                'price_type' => 'piece',
                'base_price' => 500,
                'stock_quantity' => 120,
                'low_stock_threshold' => 15,
                'is_available' => true,
                'image_url' => 'https://images.unsplash.com/photo-1525351484163-7529414344d8?auto=format&fit=crop&w=1200&q=90',
                'is_bestseller' => true,
                'popularity_score' => 88,
            ],
            [
                'name' => 'Beignets sucrés',
                'description' => 'Beignets moelleux, parfaits en encas.',
                'category_name' => 'Boulangerie & Snacks',
                'price_type' => 'piece',
                'base_price' => 250,
                'stock_quantity' => 180,
                'low_stock_threshold' => 20,
                'is_available' => true,
                'image_url' => 'https://images.unsplash.com/photo-1511690743698-d9d85f2fbf38?auto=format&fit=crop&w=1200&q=90',
                'is_bestseller' => false,
                'popularity_score' => 67,
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

        // Additional Beninese market categories and samples
        $this->call(\Database\Seeders\BenineseCategoriesSeeder::class);
    }
}
