<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

/**
 * Regle metier : il faut au moins une heure entre la commande et le DEBUT du
 * creneau. Le preparateur doit aller au marche.
 *
 * Ces tests figent l'heure : sans cela, ils passeraient ou echoueraient selon
 * le moment de la journee ou on lance la suite.
 */
class DeliverySlotLeadTimeTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function orderAt(string $slotStart, array $overrides = [])
    {
        $client = $this->createClient();
        [$zone] = $this->createZoneWithSlot();
        $slot = $this->createHourlySlot($zone, $slotStart);
        $address = $this->createAddressFor($client, $zone);
        $product = $this->createProduct();

        Sanctum::actingAs($client, ['*']);

        return $this->postJson('/api/v1/orders', array_merge([
            'delivery_address_id' => $address->id,
            'delivery_slot_id' => $slot->id,
            'delivery_date' => Carbon::now()->toDateString(),
            'items' => [
                ['type' => 'product', 'product_id' => $product->id, 'quantity' => 1],
            ],
        ], $overrides));
    }

    public function test_a_slot_starting_exactly_one_hour_later_is_accepted(): void
    {
        Carbon::setTestNow(Carbon::today()->setTime(10, 0));

        $this->orderAt('11:00')->assertCreated();
    }

    public function test_a_slot_starting_in_less_than_an_hour_is_rejected(): void
    {
        Carbon::setTestNow(Carbon::today()->setTime(10, 1));

        $response = $this->orderAt('11:00');

        $response->assertStatus(422);
        $this->assertSame('slot_too_soon', $response->json('error'));
    }

    public function test_a_slot_already_started_is_rejected(): void
    {
        Carbon::setTestNow(Carbon::today()->setTime(14, 30));

        $this->orderAt('14:00')->assertStatus(422);
    }

    public function test_same_day_delivery_is_allowed(): void
    {
        Carbon::setTestNow(Carbon::today()->setTime(8, 0));

        // Le cas nominal du metier : commande le matin, livraison l'apres-midi.
        $this->orderAt('15:00')->assertCreated();
    }

    public function test_a_date_beyond_the_booking_horizon_is_rejected(): void
    {
        Carbon::setTestNow(Carbon::today()->setTime(10, 0));

        $response = $this->orderAt('11:00', [
            'delivery_date' => Carbon::today()->addDays(30)->toDateString(),
        ]);

        $response->assertStatus(422);
        $this->assertSame('date_out_of_range', $response->json('error'));
    }

    public function test_slots_endpoint_flags_the_ones_that_are_too_soon(): void
    {
        Carbon::setTestNow(Carbon::today()->setTime(10, 0));

        [$zone] = $this->createZoneWithSlot();
        $this->createHourlySlot($zone, '09:00');
        $this->createHourlySlot($zone, '11:00');

        $response = $this->getJson(
            '/api/v1/delivery-slots?zone_id='.$zone->id.'&date='.Carbon::today()->toDateString()
        );

        $response->assertOk();

        $byLabel = collect($response->json('data'))->keyBy('display_label');

        $this->assertFalse($byLabel['09:00 - 10:00']['is_bookable']);
        $this->assertSame('too_soon', $byLabel['09:00 - 10:00']['unavailable_reason']);
        $this->assertTrue($byLabel['11:00 - 12:00']['is_bookable']);
    }
}
