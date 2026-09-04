<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;

/**
 * Ce fournisseur etait declare dans bootstrap/providers.php sans exister sur le
 * disque. On le cree pour de bon, et il porte la configuration du lien de
 * reinitialisation.
 */
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // L'API n'a pas de page web : le lien du mail doit pointer vers le
        // front Next.js, qui repostera le jeton a /auth/reset-password.
        ResetPassword::createUrlUsing(function ($notifiable, string $token) {
            $base = rtrim(config('app.frontend_url'), '/');

            return $base.'/reinitialiser-mot-de-passe?token='.$token
                .'&email='.urlencode($notifiable->getEmailForPasswordReset());
        });
    }
}
