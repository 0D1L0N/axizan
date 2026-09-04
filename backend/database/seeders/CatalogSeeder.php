<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Catalogue beninois : categories et produits du marche.
 *
 * A propos des images : tant que nous n'avons pas nos propres photos, seules des
 * images verifiees (qui repondent reellement en HTTP 200) sont utilisees, et
 * uniquement quand elles correspondent vraiment au produit. Les autres articles
 * ont image_url = null : l'interface affiche alors un visuel de marque
 * "photo a venir" avec l'emoji de la categorie. On evite ainsi deux pieges :
 * les images cassees, et les photos trompeuses (un plat de legumes melanges
 * presente comme du gombo).
 */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->categories() as $order => $data) {
            Category::updateOrCreate(
                ['name' => $data['name']],
                $data + ['display_order' => $order + 1, 'is_active' => true]
            );
        }

        foreach ($this->products() as $data) {
            $category = Category::where('name', $data['category'])->first();

            $attributes = $data;
            unset($attributes['category']);

            $attributes['category_id'] = $category?->id;
            $attributes['is_available'] = true;
            $attributes['image_url'] = $data['image_url'] ?? null;
            // Vignette allegee pour les listes : inutile de charger du 1200px dans une carte.
            $attributes['thumbnail_url'] = isset($data['image_url'])
                ? $this->thumbnailFrom($data['image_url'])
                : null;

            Product::updateOrCreate(['name' => $data['name']], $attributes);
        }
    }

    /**
     * Categories calquees sur l'organisation reelle du marche beninois.
     *
     * Elles servent DEUX usages, et c'est ce qui a dicte le decoupage.
     *
     * Cote client, on compose un repas : un feculent qui fait le fond, la
     * sauce autour. L'ordre suit donc celui de l'assiette, pas l'alphabet.
     *
     * Cote preparateur, ces memes categories routent vers les etals — un
     * fournisseur declare les categories qu'il couvre, et la liste de retrait
     * est triee dans l'ordre de parcours du marche. D'ou des categories qui
     * epousent des ZONES : a Dantokpa, la poissonnerie et la boucherie sont
     * deux endroits differents tenus par des gens differents. Les melanger,
     * comme le faisait « Viandes & poissons », rendait le routage impossible.
     *
     * La FAO decrit la meme structure pour Cotonou : des vendeuses
     * specialisees par produit, et huit familles de base — mais, riz, igname,
     * gari, haricots, arachides, poisson.
     * Voir https://www.fao.org/4/X6984F/x6984f05.htm
     */
    private function categories(): array
    {
        return [
            [
                'name' => 'Tubercules & plantain',
                'icon_emoji' => '🍠',
                'description' => 'Igname, manioc, patate douce, taro et plantain — le fond du repas.',
                'image_url' => $this->img('photo-1518977676601-b53f82aba655'),
            ],
            [
                'name' => 'Céréales & légumineuses',
                'icon_emoji' => '🌾',
                'description' => 'Riz, farine de maïs, gari, tapioca, haricot et arachides.',
                'image_url' => $this->img('photo-1536304993881-ff6e9eefa2a6'),
            ],
            [
                'name' => 'Légumes du jour',
                'icon_emoji' => '🥬',
                'description' => 'Tomate, oignon, gombo, légumes-feuilles — choisis le matin même.',
                'image_url' => $this->img('photo-1542838132-92c53300491e'),
            ],
            [
                'name' => 'Condiments & épices',
                'icon_emoji' => '🌶️',
                'description' => 'Piment, ail, gingembre, afitin — ce qui fait le goût de la sauce.',
                'image_url' => $this->img('photo-1596040033229-a9821ebd058d'),
            ],
            [
                // Zone distincte de la boucherie, tenue par d'autres vendeuses :
                // Dantokpa jouxte l'ocean, le poisson y a son propre quartier.
                'name' => 'Poissons & fruits de mer',
                'icon_emoji' => '🐟',
                'description' => 'Tilapia, poisson fumé, crevettes séchées.',
                'image_url' => $this->img('photo-1565680018434-b513d5e5fd47'),
            ],
            [
                'name' => 'Viandes & œufs',
                'icon_emoji' => '🍗',
                'description' => 'Bœuf, poulet fermier et œufs.',
                'image_url' => $this->img('photo-1607623814075-e51df1bdc82f'),
            ],
            [
                'name' => 'Huiles & pâtes',
                'icon_emoji' => '🫗',
                'description' => 'Huile rouge, huile végétale, pâte d’arachide, karité.',
                'image_url' => $this->img('photo-1474979266404-7eaacbcd87c5'),
            ],
            [
                // Ce qui ne se retire pas a un etal mais s'achete en boutique :
                // produit industriel, emballe, au prix affiche.
                'name' => 'Épicerie & conserves',
                'icon_emoji' => '🥫',
                'description' => 'Tomate concentrée, sardines, eau minérale.',
                'image_url' => $this->img('photo-1584385002340-d886f3a0f097'),
            ],
            [
                'name' => 'Fruits',
                'icon_emoji' => '🍍',
                'description' => 'Ananas, mangues, oranges, papayes et fruits de saison.',
                'image_url' => $this->img('photo-1619566636858-adf3ef46400b'),
            ],
            [
                'name' => 'Boissons locales',
                'icon_emoji' => '🥤',
                'description' => 'Bissap, gingembre, baobab et tamarin, pressés au Bénin.',
                'image_url' => $this->img('photo-1544145945-f90425340c7e'),
            ],
        ];
    }

    private function products(): array
    {
        return [
            // ---------------------------------------------------------- Fruits
            ['name' => 'Ananas pain de sucre', 'category' => 'Fruits', 'base_price' => 900, 'price_type' => 'piece', 'stock_quantity' => 70, 'is_bestseller' => true, 'popularity_score' => 99,
                'description' => 'Ananas doux et parfumé, idéal pour jus et salades de fruits.',
                'image_url' => $this->img('photo-1550258987-190a2d41a8ba')],
            ['name' => 'Mangues mûres', 'category' => 'Fruits', 'base_price' => 1200, 'price_type' => 'kg', 'stock_quantity' => 50, 'is_bestseller' => true, 'popularity_score' => 98,
                'description' => 'Mangues fraîches du marché, sucrées et prêtes à consommer.',
                'image_url' => $this->img('photo-1553279768-865429fa0078')],
            ['name' => 'Papaye solo', 'category' => 'Fruits', 'base_price' => 700, 'price_type' => 'piece', 'stock_quantity' => 42, 'popularity_score' => 82,
                'description' => 'Papaye tendre pour dessert, jus ou salade de fruits.',
                'image_url' => $this->img('photo-1526318472351-c75fcf070305')],
            ['name' => 'Oranges fraîches', 'category' => 'Fruits', 'base_price' => 1500, 'price_type' => 'kg', 'stock_quantity' => 65, 'popularity_score' => 79,
                'description' => 'Oranges juteuses pour jus pressé et desserts.',
                'image_url' => $this->img('photo-1580052614034-c55d20bfee3b')],
            ['name' => 'Citron vert', 'category' => 'Fruits', 'base_price' => 400, 'price_type' => 'cup', 'stock_quantity' => 80, 'popularity_score' => 70,
                'description' => 'Citrons verts parfumés pour marinades, jus et salades.',
                'image_url' => $this->img('photo-1590502593747-42a996133562')],
            ['name' => 'Bananes douces', 'category' => 'Fruits', 'base_price' => 800, 'price_type' => 'kg', 'stock_quantity' => 90, 'popularity_score' => 77,
                'description' => 'Bananes mûres à croquer, pour le petit-déjeuner et les goûters.'],
            ['name' => 'Avocats', 'category' => 'Fruits', 'base_price' => 1400, 'price_type' => 'kg', 'stock_quantity' => 35, 'popularity_score' => 68,
                'description' => 'Avocats crémeux pour salades et tartines.'],
            ['name' => 'Pastèque', 'category' => 'Fruits', 'base_price' => 2000, 'price_type' => 'piece', 'stock_quantity' => 25, 'popularity_score' => 74,
                'description' => 'Pastèque bien juteuse, parfaite pour la chaleur.'],

            // --------------------------------------------------- Légumes frais
            ['name' => 'Tomates locales', 'category' => 'Légumes du jour', 'base_price' => 1200, 'price_type' => 'kg', 'stock_quantity' => 80, 'is_bestseller' => true, 'popularity_score' => 95,
                'description' => 'Tomates fraîches pour sauces, salades et plats mijotés.'],
            ['name' => 'Oignons violets', 'category' => 'Légumes du jour', 'base_price' => 800, 'price_type' => 'kg', 'stock_quantity' => 95, 'is_bestseller' => true, 'popularity_score' => 93,
                'description' => 'Oignons parfumés pour sauces, fritures et assaisonnement.',
                'image_url' => $this->img('photo-1580201092675-a0a6a6cafbb1')],
            ['name' => 'Gombo frais', 'category' => 'Légumes du jour', 'base_price' => 1000, 'price_type' => 'kg', 'stock_quantity' => 60, 'popularity_score' => 88,
                'description' => 'Gombo tendre pour sauce gluante et accompagnements.'],
            ['name' => 'Carottes', 'category' => 'Légumes du jour', 'base_price' => 900, 'price_type' => 'kg', 'stock_quantity' => 70, 'popularity_score' => 72,
                'description' => 'Carottes croquantes pour salades, riz et cuissons.'],
            ['name' => 'Grande morelle (fotètè)', 'category' => 'Légumes du jour', 'base_price' => 500, 'price_type' => 'piece', 'stock_quantity' => 50, 'popularity_score' => 80,
                'description' => 'Botte de feuilles fraîches pour sauces traditionnelles.'],
            ['name' => 'Aubergine africaine (gboma)', 'category' => 'Légumes du jour', 'base_price' => 700, 'price_type' => 'kg', 'stock_quantity' => 55, 'popularity_score' => 78,
                'description' => 'Aubergines locales pour sauces et ragoûts.'],
            ['name' => 'Chou pommé', 'category' => 'Légumes du jour', 'base_price' => 800, 'price_type' => 'piece', 'stock_quantity' => 40, 'popularity_score' => 63,
                'description' => 'Chou frais pour salades, riz sauté et accompagnements.'],

            // ----------------------------------------------------- Tubercules
            ['name' => 'Igname de Dassa', 'category' => 'Tubercules & plantain', 'base_price' => 1800, 'price_type' => 'kg', 'stock_quantity' => 45, 'is_bestseller' => true, 'popularity_score' => 96,
                'description' => 'Tubercule ferme pour igname pilée, friture ou ragoût.',
                'image_url' => $this->img('photo-1518977676601-b53f82aba655')],
            ['name' => 'Banane plantain', 'category' => 'Tubercules & plantain', 'base_price' => 900, 'price_type' => 'kg', 'stock_quantity' => 120, 'popularity_score' => 89,
                'description' => 'Plantain mûr pour alloco, friture ou accompagnement.',
                'image_url' => $this->img('photo-1603833665858-e61d17a86224')],
            ['name' => 'Manioc frais', 'category' => 'Tubercules & plantain', 'base_price' => 950, 'price_type' => 'kg', 'stock_quantity' => 60, 'popularity_score' => 84,
                'description' => 'Manioc pour gari, tapioca, foutou ou accompagnements.'],
            ['name' => 'Patate douce', 'category' => 'Tubercules & plantain', 'base_price' => 1000, 'price_type' => 'kg', 'stock_quantity' => 65, 'popularity_score' => 75,
                'description' => 'Patate douce sucrée, à bouillir ou à frire.'],
            ['name' => 'Taro', 'category' => 'Tubercules & plantain', 'base_price' => 1100, 'price_type' => 'kg', 'stock_quantity' => 30, 'popularity_score' => 61,
                'description' => 'Taro pour sauces épaisses et plats traditionnels.'],

            // ------------------------------------- Céréales & légumineuses
            ['name' => 'Riz parfumé', 'category' => 'Céréales & légumineuses', 'base_price' => 5200, 'price_type' => 'bag', 'stock_quantity' => 60, 'is_bestseller' => true, 'popularity_score' => 94,
                'description' => 'Sac de 5 kg de riz long grain pour atassi et riz au gras.',
                'image_url' => $this->img('photo-1536304993881-ff6e9eefa2a6')],
            ['name' => 'Gari fin', 'category' => 'Céréales & légumineuses', 'base_price' => 700, 'price_type' => 'kg', 'stock_quantity' => 110, 'popularity_score' => 86,
                'description' => 'Gari sec pour gari foto, accompagnements et goûters.',
                'image_url' => $this->img('photo-1586201375761-83865001e31c')],
            ['name' => 'Farine de maïs', 'category' => 'Céréales & légumineuses', 'base_price' => 1600, 'price_type' => 'kg', 'stock_quantity' => 90, 'popularity_score' => 81,
                'description' => 'Farine de maïs pour amiwo, akassa et pâte.',
                'image_url' => $this->img('photo-1627485937980-221c88ac04f9')],
            ['name' => 'Haricot blanc', 'category' => 'Céréales & légumineuses', 'base_price' => 1400, 'price_type' => 'kg', 'stock_quantity' => 75, 'popularity_score' => 92,
                'description' => 'Haricot propre et trié, parfait pour atassi et abobo.'],
            ['name' => 'Tapioca', 'category' => 'Céréales & légumineuses', 'base_price' => 900, 'price_type' => 'kg', 'stock_quantity' => 50, 'popularity_score' => 66,
                'description' => 'Tapioca pour bouillies, desserts et petits-déjeuners.'],
            ['name' => 'Arachides crues', 'category' => 'Céréales & légumineuses', 'base_price' => 1300, 'price_type' => 'kg', 'stock_quantity' => 55, 'popularity_score' => 73,
                'description' => 'Arachides à griller ou à transformer en pâte.'],

            // ------------------------------------------- Viandes & poissons
            ['name' => 'Poisson fumé', 'category' => 'Poissons & fruits de mer', 'base_price' => 1800, 'price_type' => 'piece', 'stock_quantity' => 34, 'is_bestseller' => true, 'popularity_score' => 90,
                'description' => 'Poisson fumé pour sauces, atassi et plats traditionnels.',
                'image_url' => $this->img('photo-1565680018434-b513d5e5fd47')],
            ['name' => 'Poulet fermier', 'category' => 'Viandes & œufs', 'base_price' => 3200, 'price_type' => 'piece', 'stock_quantity' => 30, 'popularity_score' => 87,
                'description' => 'Poulet entier pour grillades, sauces et riz au gras.',
                'image_url' => $this->img('photo-1587593810167-a84920ea0781')],
            ['name' => 'Oeufs fermiers', 'category' => 'Viandes & œufs', 'base_price' => 1500, 'price_type' => 'piece', 'stock_quantity' => 60, 'popularity_score' => 83,
                'description' => 'Plateau d’oeufs frais pour gari foto et omelettes.',
                'image_url' => $this->img('photo-1506976785307-8732e854ad03')],
            ['name' => 'Sardines', 'category' => 'Épicerie & conserves', 'base_price' => 1200, 'price_type' => 'jar', 'stock_quantity' => 55, 'popularity_score' => 72,
                'description' => 'Boîtes de sardines pour gari foto et repas rapides.',
                'image_url' => $this->img('photo-1559742811-822873691df8')],
            ['name' => 'Tilapia frais', 'category' => 'Poissons & fruits de mer', 'base_price' => 2500, 'price_type' => 'kg', 'stock_quantity' => 28, 'popularity_score' => 85,
                'description' => 'Tilapia du jour, à griller ou en sauce.'],
            ['name' => 'Crevettes séchées', 'category' => 'Poissons & fruits de mer', 'base_price' => 2000, 'price_type' => 'cup', 'stock_quantity' => 40, 'popularity_score' => 79,
                'description' => 'Crevettes séchées pour relever sauces et bouillons.'],
            ['name' => 'Viande de bœuf', 'category' => 'Viandes & œufs', 'base_price' => 3500, 'price_type' => 'kg', 'stock_quantity' => 25, 'popularity_score' => 82,
                'description' => 'Morceaux de bœuf pour ragoûts, brochettes et sauces.'],

            // ------------------------------------------ Épices & condiments
            ['name' => 'Piment rouge frais', 'category' => 'Condiments & épices', 'base_price' => 500, 'price_type' => 'cup', 'stock_quantity' => 120, 'popularity_score' => 86,
                'description' => 'Piment fort pour sauces béninoises et marinades.',
                'image_url' => $this->img('photo-1583119022894-919a68a3d0e3')],
            ['name' => 'Menthe fraîche', 'category' => 'Condiments & épices', 'base_price' => 600, 'price_type' => 'piece', 'stock_quantity' => 40, 'popularity_score' => 64,
                'description' => 'Botte de menthe fraîche pour jus, salades et infusions.',
                'image_url' => $this->img('photo-1628556270448-4d4e4148e1b1')],
            ['name' => 'Ail frais', 'category' => 'Condiments & épices', 'base_price' => 700, 'price_type' => 'cup', 'stock_quantity' => 85, 'popularity_score' => 84,
                'description' => 'Gousses d’ail pour marinades, sauces et assaisonnements.'],
            ['name' => 'Gingembre frais', 'category' => 'Condiments & épices', 'base_price' => 900, 'price_type' => 'kg', 'stock_quantity' => 60, 'popularity_score' => 80,
                'description' => 'Racine de gingembre pour jus, sauces et infusions.'],
            ['name' => 'Afitin (moutarde locale)', 'category' => 'Condiments & épices', 'base_price' => 500, 'price_type' => 'cup', 'stock_quantity' => 70, 'popularity_score' => 87,
                'description' => 'Condiment fermenté béninois, indispensable aux sauces du terroir.'],
            ['name' => 'Poivre noir moulu', 'category' => 'Condiments & épices', 'base_price' => 600, 'price_type' => 'cup', 'stock_quantity' => 65, 'popularity_score' => 62,
                'description' => 'Poivre moulu pour relever tous vos plats.'],

            // ---------------------------------------------- Huiles & sauces
            ['name' => 'Huile rouge', 'category' => 'Huiles & pâtes', 'base_price' => 1300, 'price_type' => 'bottle', 'stock_quantity' => 52, 'is_bestseller' => true, 'popularity_score' => 91,
                'description' => 'Huile de palme locale pour sauces, atassi et gari foto.',
                'image_url' => $this->img('photo-1474979266404-7eaacbcd87c5')],
            ['name' => 'Pâte d’arachide', 'category' => 'Huiles & pâtes', 'base_price' => 1600, 'price_type' => 'jar', 'stock_quantity' => 40, 'popularity_score' => 78,
                'description' => 'Base riche pour sauce arachide et plats familiaux.',
                'image_url' => $this->img('photo-1607623814075-e51df1bdc82f')],
            ['name' => 'Tomate concentrée', 'category' => 'Épicerie & conserves', 'base_price' => 900, 'price_type' => 'jar', 'stock_quantity' => 70, 'popularity_score' => 74,
                'description' => 'Concentré de tomate pour sauces et plats mijotés.',
                'image_url' => $this->img('photo-1472476443507-c7a5948772fc')],
            ['name' => 'Huile végétale', 'category' => 'Huiles & pâtes', 'base_price' => 1800, 'price_type' => 'bottle', 'stock_quantity' => 60, 'popularity_score' => 76,
                'description' => 'Huile neutre pour fritures et cuisson du quotidien.'],
            ['name' => 'Beurre de karité', 'category' => 'Huiles & pâtes', 'base_price' => 1500, 'price_type' => 'jar', 'stock_quantity' => 35, 'popularity_score' => 60,
                'description' => 'Karité pur, pour la cuisine et les soins.'],

            // --------------------------------------------- Boissons locales
            ['name' => 'Jus de bissap', 'category' => 'Boissons locales', 'base_price' => 700, 'price_type' => 'piece', 'stock_quantity' => 100, 'is_bestseller' => true, 'popularity_score' => 85,
                'description' => 'Boisson fraîche au bissap, gingembre léger et menthe.',
                'image_url' => $this->img('photo-1622597467836-f3285f2131b8')],
            ['name' => 'Jus de gingembre', 'category' => 'Boissons locales', 'base_price' => 700, 'price_type' => 'piece', 'stock_quantity' => 90, 'popularity_score' => 80,
                'description' => 'Jus local tonique, frais et bien relevé.',
                'image_url' => $this->img('photo-1621263764928-df1444c5e859')],
            ['name' => 'Jus de baobab', 'category' => 'Boissons locales', 'base_price' => 800, 'price_type' => 'piece', 'stock_quantity' => 70, 'popularity_score' => 77,
                'description' => 'Boisson au pain de singe, douce et nourrissante.'],
            ['name' => 'Jus de tamarin', 'category' => 'Boissons locales', 'base_price' => 800, 'price_type' => 'piece', 'stock_quantity' => 60, 'popularity_score' => 71,
                'description' => 'Jus acidulé de tamarin, très rafraîchissant.'],
            ['name' => 'Eau minérale 1,5 L', 'category' => 'Épicerie & conserves', 'base_price' => 300, 'price_type' => 'bottle', 'stock_quantity' => 200, 'popularity_score' => 69,
                'description' => 'Bouteille d’eau minérale pour la maison.'],
        ];
    }

    /** URL Unsplash normalisee (meme cadrage et meme qualite pour tout le catalogue). */
    private function img(string $photoId): string
    {
        return "https://images.unsplash.com/{$photoId}?auto=format&fit=crop&w=1200&q=90";
    }

    /**
     * Derive une vignette (400px, qualite 70) de l'image principale.
     * Gain reel sur connexion mobile : une carte produit passe de ~200 Ko a ~25 Ko.
     */
    private function thumbnailFrom(string $imageUrl): string
    {
        return str_replace(['w=1200', 'q=90'], ['w=400', 'q=70'], $imageUrl);
    }
}
