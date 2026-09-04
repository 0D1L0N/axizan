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
        $query = Product::query()->with('category');

        // Filtrer par catégorie
        if ($request->has('category')) {
            $query->where('category_id', $request->category);
        }

        // Filtrer par disponibilité
        if ($request->boolean('available')) {
            $query->available();
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
        $product = Product::with('category')->find($id);

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
