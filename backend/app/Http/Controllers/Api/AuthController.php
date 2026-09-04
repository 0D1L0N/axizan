<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;

class AuthController extends Controller
{
    /**
     * Inscription
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users',
            'phone' => 'required|string|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'role' => UserRole::CLIENT->value,
                'status' => 'active',
            ]);

            $token = $user->createToken('api-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'data' => $user,
                'token' => $token,
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Connexion
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'error' => 'invalid_credentials',
                'message' => 'Email ou mot de passe incorrect',
            ], 401);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => $user,
            'token' => $token,
        ]);
    }

    /**
     * Profil utilisateur
     */
    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => $user,
            'profile' => $this->profileStatus($user),
        ]);
    }

    /**
     * Ce qu'il manque au compte pour pouvoir commander.
     *
     * Calcule a la demande plutot que stocke dans une colonne : un indicateur
     * fige se desynchronise des que le client supprime son adresse.
     *
     * Volontairement hors du modele User : il est serialise dans chaque
     * commande (client, preparateur, livreur) et un accesseur qui compte les
     * adresses y declencherait une requete par ligne.
     */
    private function profileStatus(User $user): array
    {
        $missing = [];

        if (blank($user->name)) {
            $missing[] = 'name';
        }
        if (blank($user->email)) {
            $missing[] = 'email';
        }
        if (blank($user->phone)) {
            $missing[] = 'phone';
        }

        $addressCount = $user->addresses()->active()->count();
        if ($addressCount === 0) {
            $missing[] = 'address';
        }

        return [
            'is_complete' => $missing === [],
            'missing' => $missing,
            'address_count' => $addressCount,
            // Sans adresse, le tunnel de commande n'a rien a valider.
            'can_order' => $addressCount > 0 && blank($user->phone) === false,
        ];
    }

    /**
     * Demander un lien de reinitialisation.
     *
     * La reponse est volontairement identique que l'adresse existe ou non :
     * repondre "compte inconnu" transformerait ce point d'entree en annuaire
     * des comptes Axizan.
     */
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_THROTTLED) {
            return response()->json([
                'success' => false,
                'error' => 'throttled',
                'message' => 'Un lien vient déjà d’être envoyé. Patientez quelques minutes.',
            ], 429);
        }

        return response()->json([
            'success' => true,
            'message' => 'Si un compte existe avec cette adresse, un lien de réinitialisation vient d’être envoyé.',
        ]);
    }

    /**
     * Reinitialiser le mot de passe a partir du jeton recu par mail.
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => Hash::make($password)])->save();

                // Un mot de passe reinitialise doit deconnecter les sessions
                // existantes : si le compte etait compromis, changer le mot de
                // passe sans revoquer les jetons ne sert a rien.
                $user->tokens()->delete();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'success' => false,
                'error' => 'invalid_token',
                'message' => 'Ce lien est invalide ou expiré. Demandez-en un nouveau.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Mot de passe modifié. Vous pouvez vous connecter.',
        ]);
    }

    /**
     * Changer son mot de passe en etant connecte.
     */
    public function changePassword(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'password' => ['required', 'confirmed', 'different:current_password', Rules\Password::defaults()],
        ], [
            'password.different' => 'Le nouveau mot de passe doit être différent de l’actuel.',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        if (! Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'errors' => ['current_password' => ['Mot de passe actuel incorrect.']],
            ], 422);
        }

        $user->forceFill(['password' => Hash::make($request->password)])->save();

        // On revoque les autres appareils mais on garde la session courante,
        // sinon l'utilisateur se deconnecte lui-meme en changeant son mot de
        // passe depuis son compte.
        $currentTokenId = $request->user()->currentAccessToken()?->id;
        $user->tokens()->when($currentTokenId, fn ($q) => $q->whereKeyNot($currentTokenId))->delete();

        return response()->json([
            'success' => true,
            'message' => 'Mot de passe modifié. Les autres appareils ont été déconnectés.',
        ]);
    }

    /**
     * Mettre à jour profil
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            // Email et telephone sont uniques : on s'exclut soi-meme, sinon
            // reenregistrer son propre numero renvoie une erreur.
            'email' => [
                'sometimes',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'phone' => [
                'sometimes',
                'nullable',
                'string',
                'max:30',
                // Format beninois : +229 suivi de 8 a 10 chiffres, espaces
                // tolerés a la saisie.
                'regex:/^\+?[0-9\s().-]{8,20}$/',
                Rule::unique('users', 'phone')->ignore($user->id),
            ],
            'bio' => 'sometimes|nullable|string|max:500',
            'avatar_url' => 'sometimes|nullable|string|url',
            // Le client coupe les canaux qu'il veut. Les notifications
            // transactionnelles (argent, livraison imminente) ignorent ces
            // réglages — les couper ferait perdre des commandes payées.
            'notification_preferences' => 'sometimes|array',
            'notification_preferences.email' => 'sometimes|boolean',
            'notification_preferences.sms' => 'sometimes|boolean',
        ], [
            'phone.regex' => 'Numéro invalide. Exemple : +229 97 00 00 00',
            'phone.unique' => 'Ce numéro est déjà utilisé par un autre compte.',
            'email.unique' => 'Cette adresse e-mail est déjà utilisée.',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        // Changer d'email invalide la verification precedente.
        if (array_key_exists('email', $data) && $data['email'] !== $user->email) {
            $data['email_verified_at'] = null;
        }
        if (array_key_exists('phone', $data) && $data['phone'] !== $user->phone) {
            $data['phone_verified_at'] = null;
        }

        $user->update($data);

        return response()->json([
            'success' => true,
            'data' => $user->fresh(),
            'profile' => $this->profileStatus($user->fresh()),
        ]);
    }

    /**
     * Déconnexion
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Déconnecté avec succès',
        ]);
    }

    /**
     * Rafraîchir token
     */
    public function refresh(Request $request)
    {
        $user = $request->user();
        $user->currentAccessToken()->delete();

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => $user,
            'token' => $token,
        ]);
    }
}
