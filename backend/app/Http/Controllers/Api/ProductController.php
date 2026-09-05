<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Lister produits
     */
    public function index(Request $request)
    {
        /*
         * La boutique ne montre QUE ce qui est commandable.
         *
         * Jusqu'ici le filtre etait facultatif (`?available=1`) et aucun ecran
         * ne le passait : un article retire de la vente restait affiche, et le
         * client ne l'apprenait qu'au moment de commander, par une erreur.
         * Retirer un produit du catalogue ne retirait donc rien.
         *
         * `is_available` est le seul verrou de la commande (voir OrderService) :
         * la vitrine doit dire la meme chose que la caisse.
         */
        $query = Product::query()
            ->with('category')
            ->available()
            ->where('status', 'active');

        // Filtrer par catégorie
        if ($request->has('category')) {
            $query->where('category_id', $request->category);
        }

        // Bestsellers
        if ($request->boolean('bestsellers')) {
            $query->bestsellers();
        }

        // Recherche
        if ($request->has('search')) {
            $query->search($request->search);
        }

        // Tri
        $sort = $request->get('sort', 'popularity');
        switch ($sort) {
            case 'price_asc':
                $query->orderBy('base_price');
                break;
            case 'price_desc':
                $query->orderByDesc('base_price');
                break;
            case 'newest':
                $query->latest();
                break;
            case 'popularity':
            default:
                $query->orderByDesc('popularity_score');
        }

        $products = $query->paginate($request->get('limit', 20));

        return response()->json([
            'success' => true,
            'data' => $products->items(),
            'pagination' => [
                'total' => $products->total(),
                'per_page' => $products->perPage(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
            ],
        ]);
    }

    /**
     * Détail produit
     */
    public function show($id)
    {
        // Meme regle que la liste : un article retire de la vente n'a pas de
        // fiche publique. Sans ce filtre, un lien partage ou un favori
        // continuait d'ouvrir la fiche d'un produit qu'on ne peut plus
        // commander — et le bouton « ajouter au panier » y menait a une erreur.
        $product = Product::with('category')
            ->available()
            ->where('status', 'active')
            ->find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'error' => 'not_found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $product,
        ]);
    }

    /**
     * Catégories
     */
    public function categories()
    {
        $categories = Category::active()->get();

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }
}
