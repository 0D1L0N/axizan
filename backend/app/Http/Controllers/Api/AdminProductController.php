<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Gestion du catalogue.
 *
 * Jusqu'ici le catalogue etait en lecture seule : aucune route ne permettait de
 * retirer un article introuvable sur le marche national, d'en corriger le prix,
 * ni d'y ajouter une photo. Tout passait par la base.
 *
 * Deux regles tiennent le reste.
 *
 * 1. UN PRODUIT NE SE SUPPRIME PAS. Il est reference par des lignes de commande,
 *    donc par des factures deja remises. Le supprimer les rendrait illisibles.
 *    On l'archive : il quitte la boutique, l'historique reste entier.
 *
 * 2. LES PHOTOS ARRIVENT DEJA REDIMENSIONNEES. Ni GD ni Imagick ne sont
 *    installes, et c'est un choix : le navigateur redimensionne avant l'envoi.
 *    Une photo de telephone fait 4 Mo ; la televerser telle quelle depuis une
 *    connexion beninoise prendrait une minute pour un fichier dont on n'utilise
 *    qu'un quart. Le serveur verifie ce qu'il recoit, il ne le fabrique pas.
 */
class AdminProductController extends Controller
{
    /** Taille maximale acceptee, apres redimensionnement par le navigateur. */
    private const MAX_PHOTO_KB = 3072;

    /**
     * Le catalogue entier, archives comprises.
     *
     * L'administration voit ce que la boutique cache : c'est tout l'interet.
     */
    public function index(Request $request)
    {
        $query = Product::query()->with('category:id,name');

        if ($request->filled('category')) {
            $query->where('category_id', $request->integer('category'));
        }

        if ($request->filled('search')) {
            $query->search($request->string('search'));
        }

        // Les trois filtres qui servent vraiment au quotidien.
        match ($request->string('filter')->toString()) {
            'unavailable' => $query->where('is_available', false)->where('status', 'active'),
            'archived' => $query->where('status', 'archived'),
            'no_photo' => $query->whereNull('image_url')->where('status', 'active'),
            default => $query->where('status', 'active'),
        };

        $products = $query->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $products,
            'meta' => [
                'total' => Product::where('status', 'active')->count(),
                'unavailable' => Product::where('status', 'active')->where('is_available', false)->count(),
                'no_photo' => Product::where('status', 'active')->whereNull('image_url')->count(),
                'archived' => Product::where('status', 'archived')->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, creating: true);

        // En vente par defaut, et dit explicitement : la valeur par defaut de la
        // colonne n'est pas relue apres l'insertion, et l'article revenait avec
        // une disponibilite nulle — ni en vente, ni retiree.
        $product = Product::create($data + ['status' => 'active', 'is_available' => $data['is_available'] ?? true]);

        return response()->json([
            'success' => true,
            'message' => 'Article ajouté au catalogue.',
            'data' => $product->load('category:id,name'),
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $product = Product::find($id);

        if (! $product) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

        $product->update($this->validated($request, creating: false, ignore: $product->id));

        return response()->json([
            'success' => true,
            'message' => 'Article mis à jour.',
            'data' => $product->fresh()->load('category:id,name'),
        ]);
    }

    /**
     * Retire un article de la vente, ou l'y remet.
     *
     * C'est le geste demande : quand un produit devient introuvable sur le
     * marche national, il disparait de la boutique le temps qu'il faut.
     * Reversible d'un clic — une rupture est rarement definitive.
     */
    public function toggleAvailability(Request $request, $id)
    {
        $product = Product::find($id);

        if (! $product) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

        $data = $request->validate(['is_available' => 'required|boolean']);

        $product->update(['is_available' => $data['is_available']]);

        return response()->json([
            'success' => true,
            'message' => $data['is_available']
                ? "{$product->name} est de nouveau en vente."
                : "{$product->name} est retiré de la vente.",
            'data' => $product->fresh(),
        ]);
    }

    /**
     * Archive un article, ou le restaure.
     *
     * Jamais de suppression : les lignes de commande et les factures deja
     * remises pointent dessus. Archiver le sort du catalogue sans trouer
     * l'historique.
     */
    public function archive(Request $request, $id)
    {
        $product = Product::find($id);

        if (! $product) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

        $data = $request->validate(['archived' => 'required|boolean']);

        $product->update([
            'status' => $data['archived'] ? 'archived' : 'active',
            // Un article archive ne doit pas pouvoir revenir en vente par
            // inadvertance : les deux verrous bougent ensemble.
            'is_available' => $data['archived'] ? false : $product->is_available,
        ]);

        return response()->json([
            'success' => true,
            'message' => $data['archived'] ? 'Article archivé.' : 'Article restauré.',
            'meta' => ['ordered_lines' => OrderItem::where('product_id', $product->id)->count()],
            'data' => $product->fresh(),
        ]);
    }

    /**
     * Recoit la photo d'un article.
     *
     * Deux fichiers : l'image de fiche et sa vignette, toutes deux
     * redimensionnees par le navigateur avant l'envoi. La vignette est
     * facultative — sans elle on retombe sur l'image de fiche, que Next
     * redimensionnera a l'affichage.
     */
    public function uploadPhoto(Request $request, $id)
    {
        $product = Product::find($id);

        if (! $product) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

        $request->validate([
            'photo' => 'required|image|max:'.self::MAX_PHOTO_KB,
            'thumbnail' => 'nullable|image|max:512',
        ], [
            'photo.required' => 'Choisissez une image.',
            'photo.image' => 'Le fichier doit être une image.',
            'photo.max' => 'Image trop lourde. Elle devrait être réduite avant l’envoi.',
        ]);

        $this->forgetUploadedPhotos($product);

        $folder = 'products/'.$product->id;
        $image = Storage::disk('public')->url($request->file('photo')->store($folder, 'public'));

        $thumb = $request->hasFile('thumbnail')
            ? Storage::disk('public')->url($request->file('thumbnail')->store($folder, 'public'))
            : $image;

        $product->update(['image_url' => $image, 'thumbnail_url' => $thumb]);

        return response()->json([
            'success' => true,
            'message' => 'Photo enregistrée.',
            'data' => $product->fresh(),
        ]);
    }

    /**
     * Assigne une photo de la banque d'images.
     *
     * Les fichiers de la banque sont servis par le front et partages entre
     * articles : on ne les copie pas, on pointe dessus. Deux articles peuvent
     * legitimement montrer la meme photo d'etal.
     */
    public function assignFromLibrary(Request $request, $id)
    {
        $product = Product::find($id);

        if (! $product) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

        $data = $request->validate([
            // Un seul nom de fichier, sans chemin : refuser tout ce qui
            // ressemble a une remontee de repertoire.
            'file' => ['required', 'string', 'regex:/^[A-Za-z0-9._-]+\.(jpg|jpeg|png|webp)$/i'],
        ], ['file.regex' => 'Nom de fichier invalide.']);

        $path = '/products/banque/'.$data['file'];

        $this->forgetUploadedPhotos($product);
        $product->update(['image_url' => $path, 'thumbnail_url' => $path]);

        return response()->json([
            'success' => true,
            'message' => 'Photo assignée depuis la banque.',
            'data' => $product->fresh(),
        ]);
    }

    /** Retire la photo : l'article retombe sur le visuel de marque. */
    public function removePhoto($id)
    {
        $product = Product::find($id);

        if (! $product) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

        $this->forgetUploadedPhotos($product);
        $product->update(['image_url' => null, 'thumbnail_url' => null]);

        return response()->json([
            'success' => true,
            'message' => 'Photo retirée.',
            'data' => $product->fresh(),
        ]);
    }

    /**
     * Efface du disque les fichiers TELEVERSES d'un article.
     *
     * Uniquement ceux-la : les photos du catalogue livre (`/products/...`) et
     * celles de la banque sont partagees et versionnees avec l'application.
     * Les effacer casserait d'autres articles.
     */
    private function forgetUploadedPhotos(Product $product): void
    {
        foreach ([$product->image_url, $product->thumbnail_url] as $url) {
            if (! $url || ! str_starts_with($url, '/storage/')) {
                continue;
            }

            Storage::disk('public')->delete(substr($url, strlen('/storage/')));
        }
    }

    private function validated(Request $request, bool $creating, ?int $ignore = null): array
    {
        return $request->validate([
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255', Rule::unique('products', 'name')->ignore($ignore)],
            'description' => 'nullable|string|max:500',
            'category_id' => [$creating ? 'required' : 'sometimes', Rule::exists('categories', 'id')],
            // Les unites viennent du front (voir app/lib/units.js) : une valeur
            // inconnue casserait l'affichage des quantites et le pas du panier.
            'price_type' => [$creating ? 'required' : 'sometimes', Rule::in(['kg', 'piece', 'cup', 'bottle', 'jar', 'bag'])],
            'base_price' => [$creating ? 'required' : 'sometimes', 'numeric', 'min:0'],
            // Signal indicatif, jamais une limite : rien n'est decremente.
            'stock_quantity' => 'nullable|numeric|min:0',
            'is_available' => 'sometimes|boolean',
            'is_bestseller' => 'sometimes|boolean',
        ], [
            'name.unique' => 'Un article porte déjà ce nom.',
            'price_type.in' => 'Unité inconnue.',
        ]);
    }

    /**
     * La banque d'images : ce qui est disponible a l'assignation.
     *
     * Lue depuis le disque du front, ou vivent les fichiers versionnes avec
     * l'application.
     */
    public function library()
    {
        $directory = base_path('../frontend/public/products/banque');

        if (! is_dir($directory)) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $files = collect(scandir($directory) ?: [])
            ->filter(fn ($file) => preg_match('/\.(jpg|jpeg|png|webp)$/i', $file))
            ->values()
            ->map(fn ($file) => [
                'file' => $file,
                'url' => '/products/banque/'.$file,
                // Quels articles utilisent deja cette image : eviter de
                // rattacher la meme photo d'etal a cinq produits differents.
                'used_by' => Product::where('image_url', '/products/banque/'.$file)
                    ->pluck('name')
                    ->all(),
            ]);

        return response()->json(['success' => true, 'data' => $files]);
    }

    /** Categories, pour le choix a la creation d'un article. */
    public function categories()
    {
        return response()->json([
            'success' => true,
            'data' => Category::withCount('products')->orderBy('display_order')->get(['id', 'name', 'icon_emoji']),
        ]);
    }
}
