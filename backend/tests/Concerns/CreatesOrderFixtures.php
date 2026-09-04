<?php

namespace Tests\Concerns;

use App\Models\Address;
use App\Models\Category;
use App\Models\DeliverySlot;
use App\Models\DeliveryZone;
use App\Models\Market;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;

trait CreatesOrderFixtures
{
    protected function createClient(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'phone' => '+22990' . random_int(100000, 999999),
            'role' => 'client',
        ], $overrides));
    }

    protected function createStaff(string $role, array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'phone' => '+22991' . random_int(100000, 999999),
            'role' => $role,
        ], $overrides));
    }

    /**
     * @return array{0: DeliveryZone, 1: DeliverySlot}
     */
    protected function createZoneWithSlot(float $fee = 800): array
    {
        $zone = DeliveryZone::create([
            'name' => 'Zone ' . uniqid(),
            'base_delivery_fee' => $fee,
            'is_active' => true,
        ]);

        $slot = DeliverySlot::create([
            'zone_id' => $zone->id,
            'time_start' => '09:00',
            'time_end' => '11:00',
            'display_label' => '09:00 - 11:00',
            'max_orders_per_slot' => 20,
            'is_active' => true,
        ]);

        return [$zone, $slot];
    }

    /**
     * Cree un creneau d'une heure demarrant a l'heure donnee ('11:00').
     */
    protected function createHourlySlot(DeliveryZone $zone, string $start): DeliverySlot
    {
        $end = \Carbon\Carbon::createFromTimeString($start)->addHour()->format('H:i');

        return DeliverySlot::create([
            'zone_id' => $zone->id,
            'time_start' => $start,
            'time_end' => $end,
            'display_label' => "{$start} - {$end}",
            'max_orders_per_slot' => 20,
            'is_active' => true,
        ]);
    }

    protected function createAddressFor(User $user, ?DeliveryZone $zone = null): Address
    {
        return Address::create([
            'user_id' => $user->id,
            'label' => 'Maison',
            'quartier' => 'Cadjehoun',
            'delivery_zone_id' => $zone?->id,
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    /**
     * Marche de retrait desservant une zone, avec un fournisseur partenaire.
     *
     * @return array{0: Market, 1: Supplier}
     */
    protected function createMarketFor(DeliveryZone $zone, array $overrides = []): array
    {
        $market = Market::create(array_merge([
            'name' => 'Marché '.uniqid(),
            'city' => 'Cotonou',
            'is_active' => true,
        ], $overrides));

        $market->zones()->attach($zone->id);

        $supplier = Supplier::create([
            'market_id' => $market->id,
            'name' => 'Étal '.uniqid(),
            'stall' => 'Allée A1',
            'speciality' => 'Légumes frais',
            'is_active' => true,
        ]);

        return [$market, $supplier];
    }

    /** Preparateur rattache a un marche : sans marche, il ne voit rien. */
    protected function createPreparateurFor(Market $market): User
    {
        return $this->createStaff('preparateur', ['market_id' => $market->id]);
    }

    /**
     * Fichier image bidon.
     *
     * create() plutot que image() : l'extension GD n'est pas installee, et
     * l'application ne fait que stocker le fichier — elle ne le retaille pas.
     * Inutile d'alourdir l'image de production pour les tests.
     */
    protected function fakePhoto(string $name = 'panier.jpg'): \Illuminate\Http\UploadedFile
    {
        return \Illuminate\Http\UploadedFile::fake()->create($name, 120, 'image/jpeg');
    }

    /**
     * Deposer le panier au depot. La photo est obligatoire : c'est ce que le
     * client verra d'un retrait qu'il n'a pas vu faire.
     *
     * POST + _method : un envoi multipart ne passe pas en PATCH.
     */
    protected function markReady($orderId, array $extra = [])
    {
        return $this->postJson("/api/v1/preparateur/orders/{$orderId}/ready", array_merge([
            '_method' => 'PATCH',
            'photo' => $this->fakePhoto(),
        ], $extra));
    }

    /** Confirmer la remise. La photo de livraison est obligatoire. */
    protected function confirmDelivery($deliveryId, array $extra = [])
    {
        return $this->postJson("/api/v1/deliveries/{$deliveryId}/confirm", array_merge([
            'proof_photo' => $this->fakePhoto('remise.jpg'),
        ], $extra));
    }

    protected function createProduct(array $overrides = []): Product
    {
        $category = Category::create(['name' => 'Catégorie ' . uniqid()]);

        return Product::create(array_merge([
            'name' => 'Produit ' . uniqid(),
            'category_id' => $category->id,
            'base_price' => 1000,
            'stock_quantity' => 50,
            'is_available' => true,
        ], $overrides));
    }
}
