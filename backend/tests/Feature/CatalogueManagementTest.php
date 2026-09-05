<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

/**
 * Gestion du catalogue.
 *
 * Le catalogue etait en lecture seule : impossible de retirer un article
 * introuvable sur le marche, d'en corriger le prix ou d'y poser une photo.
 *
 * Deux regles structurent ces tests : la vitrine dit la meme chose que la
 * caisse, et un article ne se supprime jamais — des factures pointent dessus.
 */
class CatalogueManagementTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    private function admin(): void
    {
        Sanctum::actingAs($this->createStaff('admin'), ['*']);
    }

    // ------------------------------------------------ la vitrine et la caisse

    public function test_an_article_withdrawn_from_sale_leaves_the_shop(): void
    {
        $product = $this->createProduct(['name' => 'Gombo frais']);

        $this->assertCount(1, $this->getJson('/api/v1/products')->assertOk()->json('data'));

        $this->admin();
        $this->patchJson("/api/v1/admin/catalogue/{$product->id}/availability", ['is_available' => false])
            ->assertOk();

        // Auparavant le filtre etait facultatif et aucun ecran ne le passait :
        // l'article restait en boutique et le client ne l'apprenait qu'a la
        // commande, par une erreur. Retirer un produit ne retirait rien.
        $this->assertCount(0, $this->getJson('/api/v1/products')->assertOk()->json('data'));
    }

    public function test_the_page_of_a_withdrawn_article_is_gone_too(): void
    {
        $product = $this->createProduct();

        $this->getJson("/api/v1/products/{$product->id}")->assertOk();

        $this->admin();
        $this->patchJson("/api/v1/admin/catalogue/{$product->id}/availability", ['is_available' => false]);

        // Un lien partagé ou un favori ouvrait encore la fiche, avec un bouton
        // « ajouter au panier » qui menait à une erreur.
        $this->getJson("/api/v1/products/{$product->id}")->assertNotFound();
    }

    public function test_searching_inside_a_category_stays_inside_it(): void
    {
        $legumes = Category::create(['name' => 'Légumes du jour']);
        $fruits = Category::create(['name' => 'Fruits']);

        Product::create(['name' => 'Gombo frais', 'category_id' => $legumes->id, 'base_price' => 1200, 'is_available' => true]);
        Product::create(['name' => 'Ananas', 'category_id' => $fruits->id, 'base_price' => 900, 'is_available' => true,
            'description' => 'Parfait avec du gombo, curieusement.']);

        $found = $this->getJson("/api/v1/products?category={$legumes->id}&search=gombo")->assertOk()->json('data');

        // Le `orWhere` de la recherche n'etait pas groupe : il remontait au
        // niveau de la requete entiere et annulait le filtre categorie.
        $this->assertSame(['Gombo frais'], array_column($found, 'name'));
    }

    // ------------------------------------------------------------- le CRUD

    public function test_an_admin_adds_an_article(): void
    {
        $category = Category::create(['name' => 'Condiments & épices']);
        $this->admin();

        $this->postJson('/api/v1/admin/catalogue', [
            'name' => 'Afitin (moutarde locale)',
            'category_id' => $category->id,
            'price_type' => 'cup',
            'base_price' => 500,
            'description' => 'Condiment fermenté de graines de néré.',
        ])->assertCreated()->assertJsonPath('data.is_available', true);

        $this->assertDatabaseHas('products', ['name' => 'Afitin (moutarde locale)', 'status' => 'active']);
    }

    public function test_two_articles_cannot_share_a_name(): void
    {
        $this->createProduct(['name' => 'Gombo frais']);
        $this->admin();

        // Le seeder retrouve les produits par leur nom : un doublon casserait
        // silencieusement la mise a jour du catalogue.
        $this->postJson('/api/v1/admin/catalogue', [
            'name' => 'Gombo frais',
            'category_id' => Category::create(['name' => 'X'])->id,
            'price_type' => 'kg',
            'base_price' => 1200,
        ])->assertStatus(422)->assertJsonValidationErrors('name');
    }

    public function test_an_unknown_unit_is_refused(): void
    {
        $this->admin();

        // Les unites viennent du front : une valeur inconnue casserait
        // l'affichage des quantites et le pas du panier.
        $this->postJson('/api/v1/admin/catalogue', [
            'name' => 'Truc',
            'category_id' => Category::create(['name' => 'X'])->id,
            'price_type' => 'litre',
            'base_price' => 100,
        ])->assertStatus(422)->assertJsonValidationErrors('price_type');
    }

    public function test_a_price_can_be_corrected(): void
    {
        $product = $this->createProduct(['base_price' => 1200]);
        $this->admin();

        $this->patchJson("/api/v1/admin/catalogue/{$product->id}", ['base_price' => 1500])->assertOk();

        $this->assertSame('1500.00', $product->fresh()->base_price);
    }

    // ---------------------------------------------------------- l'archivage

    public function test_an_ordered_article_is_archived_never_deleted(): void
    {
        [$zone, $slot] = $this->createZoneWithSlot();
        $client = $this->createClient();
        $address = $this->createAddressFor($client, $zone);
        $product = $this->createProduct();

        Sanctum::actingAs($client, ['*']);
        $this->postJson('/api/v1/orders', [
            'delivery_address_id' => $address->id,
            'delivery_slot_id' => $slot->id,
            'delivery_date' => now()->addDay()->toDateString(),
            'items' => [['type' => 'product', 'product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated();

        $this->admin();
        $this->patchJson("/api/v1/admin/catalogue/{$product->id}/archive", ['archived' => true])
            ->assertOk()
            ->assertJsonPath('meta.ordered_lines', 1);

        // Des factures deja remises pointent dessus : le supprimer les rendrait
        // illisibles. Il quitte la boutique, l'historique reste entier.
        $this->assertDatabaseHas('products', ['id' => $product->id, 'status' => 'archived']);
        $this->assertCount(0, $this->getJson('/api/v1/products')->json('data'));
    }

    public function test_archiving_also_withdraws_from_sale(): void
    {
        $product = $this->createProduct(['is_available' => true]);
        $this->admin();

        $this->patchJson("/api/v1/admin/catalogue/{$product->id}/archive", ['archived' => true])->assertOk();

        // Les deux verrous bougent ensemble : un article archivé ne doit pas
        // pouvoir revenir en vente par inadvertance.
        $this->assertFalse($product->fresh()->is_available);
    }

    public function test_an_archived_article_can_be_restored(): void
    {
        $product = $this->createProduct();
        $this->admin();

        $this->patchJson("/api/v1/admin/catalogue/{$product->id}/archive", ['archived' => true]);
        $this->patchJson("/api/v1/admin/catalogue/{$product->id}/archive", ['archived' => false])->assertOk();

        $this->assertSame('active', $product->fresh()->status);
    }

    // ------------------------------------------------------------ les photos

    public function test_a_photo_is_stored_and_attached(): void
    {
        $product = $this->createProduct();
        $this->admin();

        $this->postJson("/api/v1/admin/catalogue/{$product->id}/photo", [
            'photo' => $this->fakePhoto('gombo.jpg'),
            'thumbnail' => $this->fakePhoto('gombo-thumb.jpg'),
        ])->assertOk();

        $fresh = $product->fresh();
        $this->assertStringStartsWith('/storage/products/'.$product->id, $fresh->image_url);
        $this->assertNotSame($fresh->image_url, $fresh->thumbnail_url);
    }

    public function test_without_a_thumbnail_the_full_image_serves_as_one(): void
    {
        $product = $this->createProduct();
        $this->admin();

        $this->postJson("/api/v1/admin/catalogue/{$product->id}/photo", [
            'photo' => $this->fakePhoto(),
        ])->assertOk();

        // Next redimensionne a l'affichage : mieux vaut une vignette lourde
        // qu'une carte sans image.
        $this->assertSame($product->fresh()->image_url, $product->fresh()->thumbnail_url);
    }

    public function test_replacing_a_photo_erases_the_previous_file(): void
    {
        $product = $this->createProduct();
        $this->admin();

        $this->postJson("/api/v1/admin/catalogue/{$product->id}/photo", ['photo' => $this->fakePhoto('un.jpg')]);
        $first = substr($product->fresh()->image_url, strlen('/storage/'));

        $this->postJson("/api/v1/admin/catalogue/{$product->id}/photo", ['photo' => $this->fakePhoto('deux.jpg')]);

        // Sans cela le disque se remplirait de photos que plus rien n'affiche.
        Storage::disk('public')->assertMissing($first);
    }

    public function test_a_library_photo_is_pointed_at_never_copied(): void
    {
        $product = $this->createProduct();
        $this->admin();

        $this->postJson("/api/v1/admin/catalogue/{$product->id}/photo-banque", [
            'file' => 'photo-1550258987-190a2d41a8ba.jpg',
        ])->assertOk();

        $this->assertSame('/products/banque/photo-1550258987-190a2d41a8ba.jpg', $product->fresh()->image_url);
    }

    public function test_a_path_traversal_in_the_library_name_is_refused(): void
    {
        $product = $this->createProduct();
        $this->admin();

        // Un nom de fichier libre laisserait pointer n'importe ou sur le disque.
        foreach (['../../.env', 'a/b.jpg', '../banque/x.jpg'] as $attempt) {
            $this->postJson("/api/v1/admin/catalogue/{$product->id}/photo-banque", ['file' => $attempt])
                ->assertStatus(422)
                ->assertJsonValidationErrors('file');
        }
    }

    public function test_removing_a_photo_leaves_the_seeded_files_alone(): void
    {
        $product = $this->createProduct();
        $product->update([
            'image_url' => '/products/photo-1550258987-190a2d41a8ba.jpg',
            'thumbnail_url' => '/products/photo-1550258987-190a2d41a8ba-thumb.jpg',
        ]);
        $this->admin();

        $this->deleteJson("/api/v1/admin/catalogue/{$product->id}/photo")->assertOk();

        // Ces fichiers sont versionnes avec l'application et partages : les
        // effacer casserait d'autres articles.
        $this->assertNull($product->fresh()->image_url);
        $this->assertDirectoryDoesNotExist(Storage::disk('public')->path('products'));
    }

    // ------------------------------------------------------------- l'accès

    public function test_the_catalogue_is_closed_to_everyone_but_the_admin(): void
    {
        $product = $this->createProduct();

        foreach (['client', 'preparateur', 'livreur'] as $role) {
            Sanctum::actingAs($this->createStaff($role), ['*']);

            $this->getJson('/api/v1/admin/catalogue')->assertStatus(403);
            $this->patchJson("/api/v1/admin/catalogue/{$product->id}/availability", ['is_available' => false])
                ->assertStatus(403);
            $this->deleteJson("/api/v1/admin/catalogue/{$product->id}/photo")->assertStatus(403);
        }
    }

    public function test_the_admin_sees_what_the_shop_hides(): void
    {
        $this->createProduct(['name' => 'Visible']);
        $hidden = $this->createProduct(['name' => 'Retiré']);
        $this->admin();

        $this->patchJson("/api/v1/admin/catalogue/{$hidden->id}/availability", ['is_available' => false]);

        $meta = $this->getJson('/api/v1/admin/catalogue?filter=unavailable')->assertOk();

        $this->assertSame(['Retiré'], array_column($meta->json('data'), 'name'));
        $this->assertSame(1, $meta->json('meta.unavailable'));
        $this->assertSame(2, $meta->json('meta.no_photo'));
    }
}
