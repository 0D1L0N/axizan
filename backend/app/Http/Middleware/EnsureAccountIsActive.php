<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Un compte suspendu n'agit plus, meme avec un jeton valide.
 *
 * `users.status` etait ecrit a l'inscription et lu NULLE PART : suspendre un
 * compte n'avait aucun effet. Le bloquer a la seule connexion ne suffit pas
 * davantage — les jetons vivent trente jours, et quelqu'un deja connecte
 * continuerait de commander pendant tout ce temps.
 *
 * Le controle se fait donc a chaque requete authentifiee, et le jeton courant
 * est detruit au passage : inutile de laisser vivre une cle qui n'ouvre plus
 * rien.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->status !== 'active') {
            $user->currentAccessToken()?->delete();

            return response()->json([
                'success' => false,
                'error' => 'account_suspended',
                'message' => 'Ce compte est suspendu. Contactez Axizán.',
            ], 403);
        }

        return $next($request);
    }
}
