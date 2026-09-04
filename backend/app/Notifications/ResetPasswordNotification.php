<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Mail de reinitialisation, en francais et signe Axizan.
 *
 * Celui de Laravel arrive en anglais avec « Reset Password Notification » en
 * objet : pour un client a Cotonou, c'est un mail suspect qu'on ne clique pas.
 */
class ResetPasswordNotification extends ResetPassword implements ShouldQueue
{
    use \Illuminate\Bus\Queueable;

    public function toMail($notifiable): MailMessage
    {
        $url = $this->resetUrl($notifiable);
        $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject('Axizán — réinitialiser votre mot de passe')
            ->greeting('Bonjour '.($notifiable->name ?: '').',')
            ->line('Vous avez demandé à réinitialiser le mot de passe de votre compte Axizán.')
            ->action('Choisir un nouveau mot de passe', $url)
            ->line("Ce lien expire dans {$minutes} minutes.")
            ->line('Si vous n’êtes pas à l’origine de cette demande, ignorez ce message : votre mot de passe reste inchangé.')
            ->salutation('À bientôt sur le marché, l’équipe Axizán');
    }
}
