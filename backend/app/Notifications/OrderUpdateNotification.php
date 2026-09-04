<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Mail de suivi de commande.
 *
 * En file d'attente : un serveur SMTP lent ne doit pas retarder la reponse
 * d'une commande.
 */
class OrderUpdateNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private NotificationType $type,
        private string $title,
        private string $body,
        private ?Order $order = null
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->subjectLine())
            ->greeting('Bonjour '.($notifiable->name ?: '').',')
            ->line($this->body);

        if ($this->order) {
            $mail->line('Commande **'.$this->order->order_number.'** — '
                .number_format((float) $this->order->total, 0, ',', ' ').' XOF');

            // Chaque mail mene la ou l'action se fait : payer, suivre, ou
            // recuperer sa facture. Renvoyer partout vers la fiche commande
            // obligerait le client a chercher.
            $base = rtrim(config('app.frontend_url'), '/').'/orders/'.$this->order->id;

            $mail->action(...match ($this->type) {
                NotificationType::ORDER_CREATED => ['Payer ma commande', $base],
                NotificationType::ORDER_DELIVERED => ['Voir ma facture', $base.'/facture'],
                default => ['Voir ma commande', $base],
            });
        }

        if ($this->type === NotificationType::ORDER_CREATED) {
            $mail->line('Vos courses partent au marché dès que votre paiement est vérifié.');
        }

        if ($this->type === NotificationType::ORDER_DELIVERED) {
            $mail->line('Votre facture est disponible en ligne, avec la photo de votre panier.');
        }

        return $mail->salutation('À bientôt sur le marché, l’équipe Axizán');
    }

    private function subjectLine(): string
    {
        // Le titre in-app porte un emoji de statut ; il n'a rien a faire dans
        // un objet de mail, ou il declenche des filtres anti-spam.
        $clean = trim(preg_replace('/^[^\p{L}\p{N}]+/u', '', $this->title));

        // Certains titres portent deja la reference (« Commande #AXZ-... ») :
        // la rajouter donnerait « Commande #AXZ-123 — AXZ-123 ».
        $reference = '';
        if ($this->order && ! str_contains($clean, $this->order->order_number)) {
            $reference = ' — '.$this->order->order_number;
        }

        return 'Axizán — '.$clean.$reference;
    }
}
