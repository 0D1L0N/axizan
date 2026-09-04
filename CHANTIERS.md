# Chantiers Axizán — suivi d'exécution

> **À lire en premier si vous reprenez ce travail.**
> Ce fichier est la source de vérité sur l'avancement. Il est mis à jour après
> chaque lot terminé. Pour reprendre : trouvez le premier `[ ]` et continuez.
>
> Périmètre décidé avec le porteur du projet : **rôle client uniquement**.
> Les écrans préparateur et livreur sont explicitement reportés — ne pas les
> traiter, même s'ils apparaissent comme incomplets.
>
> Référence des constats : audit du 3 septembre 2026 (26 points).

## Règles de travail

- Un lot = un ensemble cohérent, vérifiable, qui laisse le dépôt en état de marche.
- La suite de tests doit passer à la fin de chaque lot : `docker compose exec -T laravel ./vendor/bin/phpunit`
- Toute correction de comportement métier s'accompagne d'un test.
- Après chaque lot : cocher ici, et noter sous « Journal » ce qui a été fait.

---

## Lot 1 — Sécurité (le plus urgent, le plus rapide)

- [x] **1.1 CORS restreint** — publier `config/cors.php`, limiter `allowed_origins`
      aux domaines Axizán via variable d'environnement (`FRONTEND_URL`).
      *Constat : aucun config/cors.php, défaut `['*']` confirmé sur l'API.*
- [x] **1.2 Limitation de débit sur l'authentification** — `throttle` sur
      `/auth/login` et `/auth/register` dans `routes/api.php`.
      *Constat : seules les routes de mot de passe sont bridées.*
- [x] **1.3 Expiration des jetons Sanctum** — publier `config/sanctum.php`,
      `expiration` à 30 jours, planifier `sanctum:prune-expired`.
      *Constat : pas de config/sanctum.php, donc expiration `null`.*
- [x] **1.4 Arrondi monétaire au franc entier** — le XOF n'a pas de subdivision.
      `OrderService::calculateTax()` et tous les calculs de total.
      *Constat : `$subtotal * 0.10` sans arrondi, colonnes `decimal(12,2)`.*

## Lot 2 — Mobile et robustesse du front client

- [x] **2.1 Menu mobile fonctionnel** — `Header.js:118`, bouton sans gestionnaire.
      Panneau avec navigation, compte, notifications ; fermeture clic extérieur + Échap.
- [x] **2.2 Écrans d'erreur et de chargement** — `error.js`, `loading.js`,
      `not-found.js`. *Constat : aucun des trois n'existe.*
- [x] **2.3 Parcours clés repassés au téléphone** — accueil, catalogue, fiche
      produit, panier, checkout, compte.

## Lot 3 — Intégration continue

- [x] **3.1 Workflow GitHub Actions** — suite PHPUnit + lint front à chaque poussée.
      *À faire avant les gros chantiers : c'est ce qui les rend réversibles.*

## Lot 4 — Instrumenter l'argent

- [x] **4.1 `actual_unit_price` sur `order_items`** — migration + exposition API
      + calcul de marge. *La saisie par le préparateur est hors périmètre :
      on prépare le socle, l'écran viendra avec le lot préparateur.*
- [x] **4.2 Preuve de validation de paiement** — référence de transaction,
      identité du validateur, horodatage. Rendre la référence obligatoire.

## Lot 5 — Architecture

- [x] **5.1 Redis pour cache et file** — `CACHE_STORE`, `QUEUE_CONNECTION`.
      *Constat : Redis tourne, tout pointe sur `database`.*
- [x] **5.2 Ouvrier de file + envois asynchrones** — service `queue:work` dans
      `docker-compose.yml`, notifications et mails en `ShouldQueue`.
- [x] **5.3 Idempotence sur `POST /orders`** — clé d'idempotence, la même clé
      renvoie la commande déjà créée.
- [x] **5.4 Journal d'événements de commande** — table `order_events` :
      acteur, transition, motif, horodatage.
- [x] **5.5 Supprimer `delivery_slots.current_orders_count`** — colonne devenue
      mensongère depuis que la capacité se compte par date.
- [x] **5.6 Précharger `items.product`** dans `OrderController::index()`.

## Lot 6 — Expérience client

- [x] **6.1 Images optimisées** — `next/image` partout (9 `<img>` bruts).
      Budget de poids sur l'accueil, mesuré en réseau bridé.
- [x] **6.2 Système de notifications éphémères** — un composant unique ;
      toute action réussie se confirme, toute action échouée dit comment reprendre.
- [x] **6.3 Photo de preuve de livraison visible par le client** — sur la
      commande livrée, avec heure de remise et nom du livreur.
- [x] **6.4 Panier persistant côté serveur** — pour les comptes connectés,
      fusion avec le panier local à la connexion.

## Lot 7 — Produit

- [x] **7.1 Recommander une commande passée** — en un geste depuis `/orders`.
- [x] **7.2 Panier récurrent** — programmable, aligné sur le cycle mensuel.
- [x] **7.3 Installation sur l'écran d'accueil (PWA)** — compléter le manifeste,
      invitation à installer. *Le mode hors-ligne reste écarté (décision du porteur).*
- [x] **7.4 Lien de contact WhatsApp** — numéro affiché, avant toute intégration.

## Hors périmètre (décisions du porteur, à ne pas traiter)

- Écrans préparateur et livreur — reportés à un lot ultérieur.
- Mode hors-ligne / synchronisation différée.
- OTP par SMS — nécessite un agrégateur local et un chiffrage du coût par message.
- Intégration Mobile Money — nécessite contrats, clés API et règles métier.
- Séance photo du catalogue (27 produits sans image) — hors code.

---

## Journal

<!-- Ajouter une ligne par lot terminé : date, ce qui a été fait, ce qui reste. -->

- **3 sept. 2026 — Lot 1 (sécurité) terminé.** `config/cors.php` et `config/sanctum.php` publiés, throttle 10/min sur login+register, `App\Support\Money` créé et appliqué à tous les calculs de `OrderService`. Vérifié en direct : CORS restreint, 429 après 10 essais, expiration = 43200 min. 38 tests passants.
- **3 sept. 2026 — Lot 2 (mobile) terminé.** Menu mobile fonctionnel (panneau, clic extérieur, Échap, verrou de défilement, fermeture au changement de page) ; `error.js`, `loading.js`, `not-found.js` créés. Build de production vert : 19 routes, 144 kB au premier chargement sur l'accueil.
- **3 sept. 2026 — Lot 3 (CI) terminé.** `.github/workflows/ci.yml` : PHPUnit + `npm run lint` + `npm run build`. Build vérifié localement avant d'écrire le workflow.
- **3 sept. 2026 — Lot 4 (argent) terminé.** Migration `..._000012` : `actual_quantity` / `actual_unit_price` / `actual_total_price` / `purchased_at` sur `order_items`, `validated_by` / `validated_at` sur `payments`. `Order::purchaseCost()` et `Order::grossMargin()` renvoient `null` tant que TOUTES les lignes ne sont pas saisies (une marge partielle est un mensonge). Référence de transaction désormais obligatoire à la validation, double validation refusée. 44 tests passants.
  - *Reste lié :* la saisie du prix réel se fera dans l'écran préparateur, hors périmètre actuel.
- **3 sept. 2026 — Lot 5 (architecture) terminé.** Redis branché sur le cache et la file ; service `queue` ajouté au compose (`queue:work redis --tries=3`) ; `ResetPasswordNotification` en `ShouldQueue` — vérifié de bout en bout : le mail part de l'ouvrier, plus de la requête HTTP. Idempotence sur `POST /orders` via l'en-tête `Idempotency-Key` (empreinte de charge utile normalisée, 409 si la clé est réutilisée pour une commande différente). Table `order_events` alimentée par un hook Eloquent sur `Order` — toute voie qui change le statut est journalisée, y compris une correction en console. `current_orders_count` supprimé. `items.product` préchargé. 51 tests passants.
  - *Trois obstacles rencontrés, à connaître :*
    - L'extension PHP `phpredis` est **non installable** : le canal PECL renvoie « Package "redis" does not have REST info xml available », y compris après `channel-update`. On utilise `predis/predis` (PHP pur, `REDIS_CLIENT=predis`). Pour repasser à phpredis quand PECL refonctionne : installer l'extension dans `backend/Dockerfile` et changer `REDIS_CLIENT`.
    - `composer.json` contenait un script cassé (`vendor:publish --force-ansi`, option inexistante) qui faisait échouer `composer install` — donc la CI aussi. Corrigé.
    - Le cache sur Redis cassait l'isolation des tests : le limiteur de débit gardait ses compteurs d'un test à l'autre (429 au lieu de 422). `tests/TestCase.php` force désormais `cache=array`, `queue=sync`, `session=array`.
- **3 sept. 2026 — Lot 6 (expérience client) terminé.** 58 tests passants, build vert.
  - **6.1** `RemoteImage` (enveloppe `next/image` avec repli sur erreur) ; plus aucun `<img>` brut dans le front. `ProductImage` fournit lui-même le conteneur positionné qu'exige `fill`, donc les classes de dimension habillent le conteneur chez tous les appelants. AVIF/WebP activés. **Mesuré : l'image d'accueil passe de 621 Ko à 100 Ko, soit 84 % de moins.**
  - **6.2** `ToastContext` : une seule voie pour les retours. Une erreur ne disparaît pas seule (elle porte souvent une consigne) ; un toast peut porter une action de reprise. Branché sur l'ajout au panier et la commande.
  - **6.3** Photo de preuve affichée au client, avec heure de remise et nom du livreur.
  - **6.4** Panier serveur (`cart_items`, `CartController`) ; fusion à la connexion en gardant **la quantité la plus élevée** — additionner doublerait la commande si la fusion est rejouée.
  - *Trois défauts corrigés au passage :*
    - La photo de preuve était écrite sur le disque **`local`** (privé, `storage/app`) et référencée par une URL qui ne menait nulle part : produite, stockée, injoignable. Passée sur le disque `public` + `storage:link`.
    - `delivery.livreur` était chargé en entier : seul `password` est masqué sur `User`, donc l'**e-mail et le numéro personnel du livreur** partaient au client. Restreint à `id,name,avatar_url`.
    - Le checkout calculait la taxe sans arrondi et affichait donc un total différent de celui facturé. `roundXof` ajouté côté front, miroir de `App\Support\Money`.
- **3 sept. 2026 — Lot 7 (produit) terminé.** 65 tests passants, build vert.
  - **7.1** `POST /orders/{id}/reorder` : recharge le panier depuis une commande passée, **aux prix du jour** — un prix d'il y a un mois n'engage personne. Les articles disparus du catalogue sont signalés, pas ignorés.
  - **7.2** Paniers récurrents (`recurring_baskets`), cadences hebdo / quinzaine / mensuelle. **Décision structurante : un panier récurrent ne crée pas de commande tout seul**, il envoie un rappel. Commander sans confirmation reviendrait à partir acheter au marché pour quelqu'un qui n'a rien validé, à des prix qui ont bougé. Commande `axizan:send-basket-reminders`, planifiée à 08:00.
  - **7.3** PWA installable : `InstallPrompt` (mise en veille 30 jours après refus), icônes PNG générées avec sharp, raccourcis dans le manifeste. Le mode hors-ligne reste écarté.
  - **7.4** WhatsApp : lien dans le pied de page et bouton contextualisé sur chaque commande (message pré-rempli avec le numéro de commande). `app/lib/contact.js` centralise numéro et e-mail.
  - *Trois manques corrigés :*
    - Aucun ordonnanceur ne tournait : `routes/console.php` n'était jamais exécuté. Service `scheduler` (`schedule:work`) ajouté au compose.
    - Le manifeste ne déclarait que des icônes SVG : **Android n'installe pas un site sans PNG 192 et 512**. Générées, plus une variante *maskable* avec marge de sécurité (sinon le cauri serait amputé) et un `apple-touch-icon` (iOS ne lit pas le manifeste).
    - Pas de `.dockerignore` : le lien `public/storage` créé au lot 6 cassait le build (« invalid file request »). Ajouté, avec exclusion de `.env` et `vendor/`.

---

## Tous les lots du périmètre client sont terminés (24/24).

**Restent hors périmètre, par décision du porteur :** écrans préparateur et livreur,
mode hors-ligne, OTP par SMS, intégration Mobile Money, photos du catalogue.

**À faire avant la mise en ligne, non technique :**
- Remplacer le numéro et l'e-mail de contact (`frontend/app/lib/contact.js` — actuellement des remplaçants).
- Renseigner `CORS_ALLOWED_ORIGINS` et `FRONTEND_URL` avec les vrais domaines.
- Décider si les 10 % appliqués au sous-total sont une taxe ou une commission (nommage et affichage en dépendent).

## Complément — moyens de paiement exposés (3 sept. 2026)

Étape intermédiaire assumée, **sans aucune API opérateur** : le client choisit
MTN / Moov / Celtiis, reçoit le numéro marchand et le motif (= son numéro de
commande), envoie l'argent, puis déclare la référence de son SMS. Un
administrateur vérifie sur le compte et valide. C'est la façon dont un
commerçant béninois opère aujourd'hui, et rien ne prétend traiter un paiement
en ligne.

- `config/payments.php` : opérateurs, numéros marchands, USSD, `enabled`.
  Un opérateur fermé s'affiche « bientôt » et **son numéro marchand n'est pas
  communiqué** — l'afficher ferait envoyer de l'argent sur un compte non relevé.
- `GET /payment-methods` (public) — le client doit savoir comment il paiera
  avant de composer son panier.
- `POST /orders/{id}/declare-payment` — **ne valide rien.**
  `payer_declared_reference` (ce que le client affirme) et `reference_number`
  (ce que l'admin constate) sont deux colonnes distinctes : les confondre
  reviendrait à traiter une affirmation comme une preuve. Journalisé dans
  `order_events`.
- Front : `PaymentMethodPicker` au checkout, `PaymentInstructions` sur la
  commande (marche à suivre en 3 étapes, montants copiables, formulaire de
  déclaration).
- 74 tests passants, dont un qui vérifie explicitement qu'une déclaration
  client ne débloque pas `canBeAccepted()`.

**Avant la mise en ligne :** renseigner `PAY_MTN_NUMBER`, `PAY_MOOV_NUMBER`,
`PAY_CELTIIS_NUMBER` et `PAY_MERCHANT_NAME` — actuellement des remplaçants.

## Complément — notifications par e-mail et SMS (3 sept. 2026)

**82 tests passants.** Diffusion multicanale depuis `NotificationService::notify()`,
qui portait jusqu'ici deux `TODO` inertes.

- **E-mail : opérationnel.** `OrderUpdateNotification`, en français, en file
  d'attente (un SMTP lent ne doit pas retarder une commande), avec un lien vers
  la fiche commande — « Payer ma commande » à la création. Vérifié dans Mailpit.
- **SMS : câblé, agrégateur à brancher.** `App\Contracts\SmsGateway` + pilote
  `log` par défaut, qui écrit le message dans les journaux **sans rien envoyer
  ni rien simuler**. Vérifié en réel : numéro normalisé en E.164, 94 caractères,
  1 segment. Pour brancher un vrai agrégateur : une classe de plus dans
  `config/sms.php`, changer `SMS_DRIVER`. Rien d'autre ne bouge.
- **Routage par canal** (`config/notifications.php`). Le SMS coûte au segment :
  il est réservé aux trois moments où l'inaction du client a une conséquence —
  commande créée (il doit payer), paiement validé (on part au marché), en
  livraison (il doit être là). Les étapes de suivi restent in-app.
- **Préférences client** (`users.notification_preferences`), réglables sur
  `/account`. Les notifications **transactionnelles ignorent ces réglages** :
  les couper ferait manquer une commande déjà payée. C'est dit à l'écran.
- **Robustesse** : un agrégateur injoignable, un e-mail en échec ou un message
  trop long ne font jamais échouer la commande qui les a déclenchés. Testé.

*Bug corrigé :* « commande créée », « acceptée » et « annulée » partageaient le
type `ORDER_ACCEPTED`. Trois événements sous une seule étiquette rendaient tout
routage par canal impossible et faussaient la boîte de réception. Types
`ORDER_CREATED` et `ORDER_CANCELLED` ajoutés.

**Avant la mise en ligne :** choisir un agrégateur SMS béninois et chiffrer le
coût par segment. À trois SMS par commande, c'est une ligne de coût variable qui
pèse directement sur la marge.

## Revue de fond en comble (4 sept. 2026)

**133 tests, 504 assertions.** Dix points relevés à la relecture du projet
entier, traités dans l'ordre décidé par le porteur.

### 1–5 · Le stock n'est plus un verrou, le code mort est parti

Axizán ne détient aucun stock : les articles sont retirés chez des
fournisseurs partenaires **au moment de la commande**. Refuser 50 kg d'ignames
parce qu'une colonne affiche 45 n'avait aucun sens quand le fournisseur en a
des sacs.

- `stock_quantity` est désormais **un signal indicatif, jamais une limite** ;
  rien ne le décrémente. Le seul verrou est `is_available`, que
  l'administration bascule quand un produit disparaît du marché national.
  Verrouillé par `StockIsOnlyASignalTest`.
- **Supprimés** : `promotions`, `promotion_usages`, `reviews`, `menus`,
  `menu_items`, `invoices` — tables vides depuis le premier jour, sans aucune
  interface, mais `applyPromotion()` s'exécutait à chaque commande. Du schéma
  qui ment coûte plus cher qu'il ne rapporte : le prochain développeur croit
  qu'un système de promotions existe et cherche où il est branché.
  Migration `..._000022`, volontairement non réversible.
- Avec eux : `PaymentService::initiateMobileMoneyPayment()`, `generateInvoice()`,
  `callMobileMoneyAPI()`, la route `POST /payments/initiate`, les types de
  notification jamais émis (`review_request`, `promo_available`, `new_product`,
  `admin_alert`, `system_notification`), et le **code promo du panier** — un
  champ qui affichait « −500 XOF » sans qu'aucun code n'existe.
- `Market::preparateurs()` filtre maintenant sur le rôle : `market_id` vit sur
  `users`, la relation ramenait n'importe quel compte rattaché.
- `DeliveryController::stats()` renvoyait un `average_rating` qui appelait une
  relation supprimée (erreur fatale), et appelait « gains du livreur » les
  frais de livraison encaissés par Axizán. Corrigé et renommé.

### 6 · Écran admin réparé et repris au système de design

Il était **cassé** : `validatePayment(orderId)` partait sans référence alors que
l'API l'exige depuis la traçabilité des paiements — 422 systématique.

- Le formulaire s'ouvre sur place, pré-rempli de la **référence déclarée par le
  client**, avec la consigne : retrouver la transaction sur le compte marchand,
  vérifier le montant, puis recopier. La validation n'est pas un bouton, c'est
  une décision.
- La file **remonte en tête les commandes dont le client a déjà déclaré une
  référence** : quelqu'un attend derrière, et la référence dit quoi chercher.
- Création de compte staff avec **sélection du marché**, obligatoire pour un
  préparateur : sans marché le compte est mort-né, il ouvre son écran, ne voit
  aucune commande, et personne ne comprend pourquoi. Nouvel endpoint
  `GET /admin/markets`, avec l'effectif par marché.

### 7 · Livreur prévenu quand un panier arrive au dépôt

Le préparateur dépose et repart : personne ne se passe le panier en main
propre. Jusqu'ici le panier attendait qu'un livreur pense à rafraîchir.

- `NotificationType::BASKET_AT_DEPOT`, diffusé à **tous les livreurs actifs** à
  `markReady()`, avec le nom du dépôt et le quartier. In-app seulement : un SMS
  par livreur et par panier coûterait le prix de la course, et le premier
  arrivé prend de toute façon.
- L'écran livreur **relit le dépôt toutes les 45 s**, uniquement sur l'écran de
  liste et onglet au premier plan, et signale les nouveaux paniers par leur
  numéro. Revenir sur l'onglet déclenche une relecture immédiate.

### 8 · Résistance réseau du préparateur (le vrai risque terrain)

Chaque tap sur « Pris » / « Absent » attendait un aller-retour réseau **et se
perdait en silence en cas d'échec** : le préparateur croyait avoir pointé un
article qui restait « en attente » côté serveur, et ne l'apprenait qu'au moment
de déposer, à l'autre bout du marché.

- `usePickQueue` (`app/lib/pickQueue.js`) : le tap est acté immédiatement à
  l'écran, écrit sur le téléphone, puis rejoué jusqu'à ce qu'il passe. Fermer
  l'écran ne le perd pas. Envois **séquentiels et dans l'ordre** — deux
  modifications du même article doivent arriver dans le bon ordre.
- **Distinction réseau / refus** : un 4xx est définitif (fournisseur hors
  marché, remplacement interdit) et remonte au préparateur pour correction ; une
  coupure est réessayée en silence, avec paliers 1 s → 30 s. Confondre les deux,
  c'est soit boucler à l'infini sur une erreur de saisie, soit jeter un pointage
  à cause d'un trou de couverture.
- Retour du réseau (`online`) : reprise immédiate, sans attendre le palier.
- « J'ai déposé » est **bloqué tant que la file n'est pas vide** — sinon le
  serveur refuse pour « articles en attente » alors que tout est pointé sur le
  téléphone.
- Bandeau d'état : « hors réseau, vos pointages sont gardés » / « N pointages en
  cours d'envoi ».

### 9 · Les dix pages restantes passées au système de design

`login`, `register`, `cart`, `orders`, `orders/[id]`, `menus`, `products/[id]`,
`CartItem`, `OrderCard`, `OrderStatusTimeline` utilisaient encore `bg-primary`,
`text-gray-*`, `rounded-lg` et des hexadécimaux en dur — dont un dégradé
`to-secondary` qui ne pointait sur aucune couleur définie.

L'alias de compatibilité `primary` a été **retiré de la configuration** : plus
aucune page ne l'utilise, et le laisser rouvrirait la porte à deux noms pour
une même couleur.

Au passage, le panier n'annonce plus de total inventé : les frais de livraison
dépendent de la zone de l'adresse et la taxe se calcule dessus. Ces montants ne
sont connus qu'au checkout, et le total qui engage est celui que le serveur
renvoie.

### Reste à faire avant une mise en ligne

- Numéro et e-mail de contact réels (`app/lib/contact.js` : `+229 00 00 00 00`).
- Numéros marchands réels (`PAY_MTN_NUMBER`, etc.) et clés FedaPay
  (`FEDAPAY_ENABLED=false` aujourd'hui).
- 27 des 49 produits sans photo.
- `orders.payment_method` a encore `'cash'` par défaut en migration, ce qui
  contredit la décision « pas de paiement à la livraison ».

## Facture et preuves obligatoires (4 sept. 2026)

**142 tests, 606 assertions.**

### Facture après livraison

`GET /orders/{id}/invoice`, accessible au client propriétaire et à l'admin,
**409 tant que rien n'a été remis** : avant la livraison il n'y a rien à
facturer, seulement une commande dont le contenu peut encore changer au marché.

- **Calculée, jamais stockée.** Les montants vivent déjà sur la commande et ses
  lignes ; les recopier créerait une seconde vérité qui pourrait diverger. Le
  numéro se dérive du numéro de commande — `AXZ-20260904-00012` devient
  `FA-20260904-00012` : au téléphone, le client donne l'un, l'équipe retrouve
  l'autre sans table de correspondance.
- **Elle dit ce qui a été livré, pas ce qui a été commandé.** Un article retiré
  au marché a bien été payé : la facture l'affiche barré, marqué « non livré »,
  et totalise le montant dû (`totals.undelivered`). L'effacer reviendrait à
  facturer une marchandise jamais remise. Un article remplacé nomme ce qui a
  réellement été livré.
- La référence portée est celle **constatée par Axizán**, jamais celle déclarée
  par le client : une facture ne reprend pas une affirmation.
- Page `/orders/[id]/facture`, pensée pour l'impression autant que pour
  l'écran — un ménage fonctionnaire qui justifie une dépense a besoin d'un
  papier ou d'un PDF. Le navigateur fait le PDF via `@media print` ; aucune
  bibliothèque de rendu embarquée. `print-color-adjust: exact` sans quoi la
  plupart des navigateurs impriment un document décoloré.
- L'e-mail « commande livrée » mène maintenant à la facture, pas à la fiche
  commande.

### Les deux photos deviennent obligatoires

Facultatives, elles n'auraient été prises que les jours calmes — c'est-à-dire
jamais quand elles comptent.

- **Photo de préparation** (`PreparatorController::ready`) : le client paie
  d'avance des courses qu'il ne voit pas faire. C'est la promesse de qualité
  rendue vérifiable, et la seule preuve d'Axizán si elle est contestée.
- **Photo de remise** (`DeliveryController::confirm`) : « je n'ai rien reçu »
  ne se tranche pas sur parole.
- Les deux apparaissent sur la facture, comme deux moments distincts tous deux
  opposables.

*Deux bugs corrigés au passage.* Le front livreur envoyait `photo` et `notes`
alors que l'API attend `proof_photo` et `delivery_notes` : **aucune preuve de
remise n'a jamais été enregistrée**, le serveur les ignorait en silence. Et le
champ photo du préparateur vivait à l'intérieur du bloc « dépôt » — sur un
marché sans dépôt configuré, la photo devenant obligatoire, il aurait été
définitivement incapable de déposer.

`DeliveryController::confirm` autorise désormais **avant** de valider : sinon un
inconnu apprenait la forme attendue du corps de requête avant qu'on lui dise
que la livraison ne le regarde pas.
