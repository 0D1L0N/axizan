<?php

namespace App\Console\Commands;

use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\RecurringBasket;
use Illuminate\Console\Command;

/**
 * Rappels de paniers recurrents.
 *
 * Volontairement un rappel, pas une commande automatique : partir acheter au
 * marche pour quelqu'un qui n'a rien valide — avec des prix qui ont bouge
 * depuis — engagerait notre argent sur une supposition.
 */
class SendRecurringBasketReminders extends Command
{
    protected $signature = 'axizan:send-basket-reminders';

    protected $description = 'Notifie les clients dont un panier récurrent arrive à échéance';

    public function handle(): int
    {
        $due = RecurringBasket::due()->with('user')->get();

        foreach ($due as $basket) {
            if (! $basket->user) {
                continue;
            }

            Notification::create([
                'user_id' => $basket->user_id,
                'type' => NotificationType::ORDER_ACCEPTED,
                'title' => 'Votre panier « '.$basket->name.' »',
                'message' => 'C’est le moment de le recharger. Un geste et il est prêt à commander.',
                'data' => ['recurring_basket_id' => $basket->id],
            ]);

            $basket->scheduleNext();
        }

        $this->info($due->count().' rappel(s) envoyé(s).');

        return self::SUCCESS;
    }
}
