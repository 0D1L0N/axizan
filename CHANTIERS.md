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

## Paiement Mobile Money par demande poussée (4 sept. 2026)

**149 tests, 627 assertions.** Ce qui était en place n'utilisait que le module
Checkout.js — une page de paiement à charger. L'API FedaPay permet mieux : la
**demande poussée**, qui fait sonner le téléphone du client.

### Le parcours

`POST /orders/{id}/request-payment` avec `provider` et `phone` :

1. `Transaction::create()` — montant, description, devise XOF, `custom_metadata`
   portant le numéro de commande.
2. L'identifiant est **écrit sur le paiement avant la poussée** : si l'envoi
   échoue, le webhook pourra quand même retrouver la commande.
3. `sendNow($mode)` — génère un jeton et pousse vers l'opérateur. **Sans ce
   second appel, la transaction existe mais rien n'arrive sur le téléphone.**
4. Le client tape son code. Le webhook `transaction.approved` débloque la
   commande.

Les modes sont imposés par FedaPay et n'ont rien d'intuitif — MTN Bénin est
`mtn_open` (`mtn` n'existe pas), Celtiis est `sbin`, le nom de l'ancienne
société. La correspondance est centralisée dans `FedapayService::MODES` et
verrouillée par un test : une faute de frappe casserait tous les paiements d'un
opérateur sans qu'aucun autre test ne s'en aperçoive.

### Le sondage, parce que le webhook ne suffit pas

`GET /orders/{id}/payment-status` **redemande à FedaPay** tant que le paiement
est en attente. Le webhook reste la source de vérité, mais en développement il
n'y a pas d'URL publique, et en production une notification peut se perdre :
sans ce recours, une commande payée dormirait indéfiniment.

### Limitation de débit, non négociable

`request-payment` est bridée à **5 par minute**. Rien n'oblige le numéro saisi à
être celui du client : sans cette limite, la route devient un outil de
harcèlement gratuit — chaque appel fait sonner un téléphone tiers.

### Bug corrigé

`custom_metadata` était passé à la racine de `FedaPay.init()` alors qu'il
appartient à l'objet `transaction`. FedaPay l'ignorait, et le webhook devait
deviner la commande en analysant la description — ça marchait par chance, parce
que la description contient le numéro.

### Pour passer en production

1. Compte Live sur fedapay.com, section Développeur → clés API.
2. `FEDAPAY_PUBLIC_KEY`, `FEDAPAY_SECRET_KEY`, `FEDAPAY_ENABLED=true`,
   `FEDAPAY_SANDBOX=false`.
3. Webhook sur `https://<domaine>/api/v1/webhooks/fedapay`, événement
   `transaction.approved`. Le secret du webhook se copie depuis cet écran et
   est **différent de la clé secrète** — c'est la cause n°1 des erreurs de
   signature.

Numéros de test en sandbox : **66000001** réussit, **66000000** échoue (MTN) ;
**64000001** réussit, **64000000** échoue (Moov).

## Écran de paiement : un seul chemin (4 sept. 2026)

Le module **Checkout.js de FedaPay a été retiré**. Il ouvrait une fenêtre qui
redemandait nom, prénom et e-mail — des informations déjà en base, et une
saisie de plus entre le client et son paiement. Il ne reste que la demande
poussée : opérateur, numéro, c'est tout.

Conséquence : `FedapayButton.js` supprimé. La route `confirm-payment` est
conservée comme rattrapage — c'est le recours quand un paiement reste bloqué et
qu'on a son identifiant sous les yeux dans le tableau de bord FedaPay.

### Identité visuelle des opérateurs

`OperatorMark` centralise l'affichage des trois opérateurs. Aucun paquet
d'icônes ne couvre Moov Africa ni Celtiis — ce sont des marques ouest-africaines
absentes de Simple Icons, react-icons et lucide. En attendant les fichiers
officiels, l'application affiche des pastilles typographiques aux couleurs de
chaque opérateur.

**Pour passer aux vrais logos** : déposer les fichiers dans
`frontend/public/operators/` et renseigner le champ `logo` du composant. Une
ligne par opérateur, rien d'autre ne bouge. Voir `public/operators/LISEZ-MOI.md`.

### Le texte du checkout ne ment plus

`PaymentMethodPicker` annonçait « rien n'est prélevé automatiquement » alors que
FedaPay débite désormais le compte du client. Le texte suit maintenant le régime
réel (`meta.flow`) : annoncer l'un pendant que l'application fait l'autre coûte
un paiement, ou la confiance.

### Deux gardes-fous de test

- **FedaPay est coupé d'office dans `TestCase`**, clés effacées. Le `.env` local
  porte des clés **live** : sans cette coupure, un test appelant FedaPay
  créerait de vraies transactions et ferait sonner de vrais téléphones.
- `test_payment_methods_are_public` imposait le régime attendu sans le
  configurer : il ne disait que si le développeur avait des clés, et il a
  effectivement cassé le jour où on en a mis.

### Code mort retiré

`paymentService.initiate` pointait vers `/payments/initiate`, route supprimée
plus tôt dans la journée. Et `preparatorService.ready` gardait une branche
« sans photo » que le serveur refuse désormais systématiquement.

## Le point fournisseur (5 sept. 2026)

**162 tests, 698 assertions.**

Le préparateur se présente chez le partenaire, se sert, et ne paie rien —
Axizán règle après. Cette dette grossissait depuis la première commande **sans
que personne puisse la chiffrer** : chaque ligne enregistrait déjà `supplier_id`
et `collected_at`, mais aucune interface ne savait lire cette donnée.

C'est le seul manque qui pouvait arrêter l'activité. Un fournisseur qui doute
d'être payé ferme le compte, et le préparateur se retrouve devant un étal qui
refuse de servir.

### Le tarif convenu, et pourquoi il ne suffit pas

`supplier_prices` porte le prix négocié par fournisseur et par article. Mais ce
n'est **pas** la vérité comptable : un prix se renégocie, et recalculer un point
de la semaine dernière avec le tarif d'aujourd'hui ferait bouger un chiffre déjà
réglé.

Le prix est donc **estampillé sur la ligne au moment du retrait**, dans
`actual_unit_price` — colonne qui existait déjà et attendait cet usage. C'est le
produit **réellement pris** qui est tarifé : sur un remplacement, c'est
l'article de substitution qui est sorti de l'étal.

### Deux règles qui font qu'un point tient

**Une ligne réglée ne revient jamais.** `order_items.settlement_id` rattache
chaque ligne à son règlement, dans la même transaction que sa création. Le
double paiement est empêché par le schéma, pas par la vigilance de celui qui
fait les comptes.

**On ne solde pas une dette dont une partie n'est pas chiffrée.** Un article
sans tarif n'est pas compté pour zéro, il est signalé — et il bloque le
règlement. Une dette sous-évaluée présentée comme complète est pire qu'une dette
qu'on sait incomplète : on la règle, et l'écart ne se découvre jamais.

Renseigner un tarif oublié **rechiffre automatiquement** les lignes déjà
retirées et non réglées. Sans cela l'administrateur devrait deviner seul qu'il
faut aussi recalculer. Les lignes déjà réglées, elles, ne bougent pas.

### L'écran

`/admin/fournisseurs` — la liste des étals à régler avec l'ancienneté de la
dette, puis le relevé détaillé groupé par article, avec l'historique des
règlements. Imprimable : le point se fait assis avec le fournisseur, papier en
main.

Le bouton de règlement dit ce qu'il fait — « les N lignes couvertes ne
réapparaîtront plus jamais dans un point, cette action ne s'annule pas ».

### Reste ouvert

Les frais FedaPay (1,80 % par transaction) n'entrent toujours pas dans
`Order::grossMargin()`. Avec le coût d'achat désormais connu, la marge réelle
devient calculable — c'est le prochain chantier naturel.

## Catalogue recatégorisé sur le marché réel (5 sept. 2026)

**164 tests, 708 assertions.**

Les catégories servaient deux usages qui tiraient dans des sens différents :
la navigation du client, et le **routage du préparateur** vers les étals
(`suggestSuppliers`). Le découpage a été refait pour servir les deux.

### Ce qui n'allait pas

**« Viandes & poissons » mélangeait deux zones distinctes.** À Dantokpa, la
poissonnerie et la boucherie sont deux endroits différents, tenus par des
personnes différentes. On ne peut pas envoyer un préparateur « au rayon
viandes-et-poissons » : ça n'existe pas.

**« Huiles & sauces » réunissait deux mondes** : l'huile de palme retirée à un
étal, et la tomate concentrée achetée en boutique — produit industriel,
emballé, au prix affiché. Le beurre de karité y traînait aussi, alors qu'il
n'entre dans aucune sauce.

### Le nouveau découpage

Dix catégories, dans l'ordre de l'assiette — le féculent qui fait le fond, la
sauce autour :

Tubercules & plantain · Céréales & légumineuses · Légumes du jour ·
Condiments & épices · Poissons & fruits de mer · Viandes & œufs ·
Huiles & pâtes · Épicerie & conserves · Fruits · Boissons locales

Cadré sur la description FAO de la distribution alimentaire à Cotonou : des
vendeuses spécialisées par produit, et huit familles de base — maïs, riz,
igname, gari, haricots, arachides, poisson.
Voir https://www.fao.org/4/X6984F/x6984f05.htm

### Le bug latent trouvé en chemin

Le routage vers les étals comparait le **nom** de la catégorie à une chaîne
libre saisie dans `suppliers.speciality`. Deux textes qui devaient rester
identiques à la lettre près, dans deux tables, sans contrainte. Renommer une
catégorie cassait le routage **en silence** — le préparateur perdait ses
suggestions d'étal sans qu'aucune erreur n'apparaisse nulle part.

Table `category_supplier` : un étal déclare les catégories qu'il couvre,
plusieurs comme sur le terrain (une vendeuse de légumes propose presque
toujours les condiments qui vont avec). `speciality` survit comme libellé
affiché, plus comme clé de jointure.

Une catégorie est servie par le **premier** étal qui la couvre : deux vendeuses
proposent souvent les mêmes condiments, et renvoyer le préparateur tantôt chez
l'une tantôt chez l'autre lui ferait traverser le marché deux fois.

Deux tests verrouillent le parcours : la liste de retrait suit l'ordre des
étals, et un article que personne ne couvre tombe en fin de liste sans
suggestion — visible, plutôt que cherché au mauvais endroit.

Le marché de démonstration passe de 5 à 8 étals : la boucherie, les fruits et
la boutique d'épicerie manquaient.

## La salle de contrôle (5 sept. 2026)

**179 tests, 767 assertions.** `/admin/commandes`.

Trois constats avant de construire, tous du même ordre : **la donnée existait,
rien ne savait la lire.**

- Le journal des commandes comptait déjà 33 lignes. Chaque transition y est
  enregistrée avec **son auteur** (`actor_id`, `actor_role`), d'où elle venait,
  où elle allait, pourquoi. Aucun écran ne l'affichait.
- La position des livreurs remonte toutes les 45 s. Le client la voyait ;
  l'administration, non — aucune route n'y touchait.
- Le catalogue est en lecture seule : **impossible de retirer un article
  manquant sur le marché national**, alors que `is_available` est le seul
  verrou à la commande depuis la suppression du contrôle de stock. *(Reste à
  faire.)*

### Ce qui ne bouge pas

Une liste dit ce qui existe ; elle ne dit pas ce qui coince. `OrderWatchService`
répond à la seconde question, et le signal retenu n'est pas une durée fixe mais
le **temps qui reste avant le créneau**.

Une commande acceptée depuis deux heures pour une livraison demain ne pose
aucun problème ; la même, pour un créneau qui commence dans quarante minutes,
en pose un grave. Un seuil en heures aurait alerté sur la première et laissé
passer la seconde.

Cinq situations sont détectées : payée que personne n'a prise, acceptée sans
retrait commencé, retrait entamé puis abandonné (> 3 h), panier qui vieillit au
dépôt sans livreur, créneau dépassé en cours de route.

### La fiche

Le journal déroulé avec ses auteurs, les contacts cliquables du préparateur et
du livreur, les deux photos, et la position en course quand elle existe.

### Débloquer

Libérer vers le pool, réassigner, annuler — motif obligatoire, chaque geste
journalisé au nom de l'administrateur.

Deux garde-fous : un panier **déjà au dépôt ne peut plus être libéré** (le
retrait est fait, le libérer laisserait le panier sans commande en face), et une
réassignation **refuse un marché qui ne dessert pas la zone** — le préparateur
ne verrait même pas la commande sur son écran.

### Deux bugs corrigés en chemin

**Le rendu d'une commande ne remettait pas à zéro les prix estampillés.**
Introduit la veille avec le point fournisseur : une commande rendue gardait ses
`actual_unit_price`, et le fournisseur aurait été payé pour une marchandise
jamais sortie de l'étal.

**Une annulation pouvait échouer à cause du remboursement.** `cancelOrder` se
fiait à `orders.payment_status` pour décider de rembourser, puis appelait
`refundPayment()` qui teste `payments.status`. Deux colonnes distinctes que rien
ne garantit d'accord : dès qu'elles divergeaient, l'annulation entière partait
en erreur et le client restait bloqué avec une commande impossible à annuler.
L'annulation prime désormais sur l'écriture comptable qui la suit.

### Reste à faire, par ordre d'utilité

1. **Gestion du catalogue** — activer/retirer un article, corriger un prix,
   ajouter une photo (27 produits sur 49 n'en ont pas).
2. **Le personnel** — liste, charge du jour, suspension d'un compte. Un livreur
   qui part garde aujourd'hui son accès à vie.
3. **Les créneaux** — fermer un jour, ajuster une capacité, suspendre une zone.
4. **Les chiffres** — la marge réelle est calculable depuis qu'on a les coûts
   d'achat ; il manque les 1,80 % FedaPay dans `Order::grossMargin()`.

## Images du catalogue rapatriées en local (5 sept. 2026)

**40 photos sur 49 articles**, contre 22 auparavant — et surtout, **plus une
seule image distante**.

### Pourquoi local plutôt que le CDN

Ce n'était pas qu'un bouchage de trous. Les photos venaient d'`images.unsplash.com`,
c'est-à-dire d'un serveur hors du continent.

- **Latence.** Mesuré ici : **1,05 s depuis le CDN, 24 ms en local.** Une grille
  de six produits, c'était six allers-retours vers l'Europe avant que la page ne
  soit lisible. Depuis une connexion mobile béninoise, l'écart est bien pire.
- **Hors-ligne.** L'application doit pouvoir être montrée et travaillée sans
  connexion. Le catalogue apparaissait vide.
- **Pérennité.** Une photo retirée d'Unsplash cassait une fiche produit sans que
  personne ne s'en aperçoive avant un client.

Les fichiers vivent dans `frontend/public/products/`, nommés par leur
identifiant Unsplash d'origine — la traçabilité vers la source est conservée
sans table de correspondance. 88 fichiers, 12 Mo : une version pleine taille
(1200 px) et une vignette (400 px, ~30 Ko) par photo.

### Les neuf qui restent sans photo, et pourquoi

> Grande morelle (fotètè) · Aubergine africaine (gboma) · Afitin ·
> Crevettes séchées · Beurre de karité · Jus de baobab · Jus de tamarin ·
> Tapioca · Eau minérale

**Ce n'est pas un oubli.** Ces produits n'existent pas dans les banques
d'images, et ce qu'on y trouve sous ces mots montre autre chose : de la moutarde
de Dijon pour l'afitin, des épinards pour le fotètè, une aubergine italienne
pour le gboma. Une cliente de Cotonou le voit immédiatement.

Une photo qui ment sur la marchandise coûte plus cher qu'une case vide — surtout
pour une marque qui promet de savoir choisir. Ces neuf-là attendent les photos
d'Axizán, prises au marché.

### Reste à faire

La **gestion du catalogue** reste le blocage : téléverser une photo, activer ou
retirer un article, corriger un prix. Sans elle, les photos d'Axizán n'auront
nulle part où aller — et un produit introuvable sur le marché national ne peut
toujours pas être retiré de la plateforme.

## Banque d'images ouest-africaine (5 sept. 2026)

`frontend/public/products/banque/` — **24 photographies** libres d'usage
commercial, téléchargées comme réserve pour le catalogue : étals de marché,
plats ouest-africains, poisson séché, fruits du palmier, karité, hibiscus,
légumes-feuilles, plantain.

Elles ne sont rattachées à aucun produit : ce sont des candidates. Le manifeste
`BANQUE.md` liste pour chacune la description écrite par son photographe et le
lien vers la source.

**Avertissement porté dans le manifeste** : je n'ai vu aucune de ces images. Les
descriptions viennent des auteurs. Et aucune ne montre un produit spécifiquement
béninois nommé — ni afitin, ni fotètè, ni gboma. Ces produits-là n'existent pas
dans les banques d'images.

Une seule comblait honnêtement un manque : des pots de beurre de karité d'une
marque ghanéenne. Rattachée. **41 articles sur 49 ont désormais une photo.**

## Vérification : la preuve photo du préparateur

Contrôlé de bout en bout, rien à construire — la chaîne fonctionnait déjà :

- `PreparatorController::ready()` exige la photo (`required|image|max:5120`) ;
  sans elle le dépôt est refusé.
- Elle est stockée sur le disque `public`, dans `preparations/{commande}/`.
- Servie via le proxy `/storage/*` du front vers Laravel : **200** des deux
  côtés, testé avec un vrai fichier.
- L'administration la voit sur la fiche commande (`/admin/commandes`), à côté de
  la photo de remise du livreur.

Le client la voit également sur sa commande et sur sa facture.

## Gestion du catalogue (5 sept. 2026)

**197 tests, 829 assertions.** `/admin/catalogue`.

Le catalogue était en lecture seule : aucune route ne permettait de retirer un
article introuvable sur le marché national, d'en corriger le prix, ni d'y poser
une photo. Tout passait par la base.

### Deux bugs trouvés avant d'écrire la première ligne

**Un article « retiré de la vente » restait affiché en boutique.** Le filtre de
disponibilité était facultatif (`?available=1`) et aucun écran ne le passait.
Le client découvrait le problème au moment de commander, par une erreur. Le
bouton « retirer » que cette fonctionnalité apporte n'aurait donc rien retiré.
La vitrine dit maintenant la même chose que la caisse — liste **et** fiche
produit.

**Le `orWhere` de la recherche n'était pas groupé.** Il remontait au niveau de
la requête entière et annulait tout ce qui précédait : chercher dans une
catégorie renvoyait des articles des autres catégories dès que leur description
contenait le mot.

### Ce que fait l'écran

Ajouter un article, corriger nom, catégorie, unité, prix, description. Retirer
de la vente d'un clic — réversible, une rupture est rarement définitive.
Filtres : sans photo, retirés, archivés.

**Rien ne se supprime.** Un produit est référencé par des lignes de commande,
donc par des factures déjà remises : le supprimer les rendrait illisibles. On
l'archive, il quitte la boutique, l'historique reste entier. L'archivage retire
aussi de la vente — les deux verrous bougent ensemble pour qu'un article archivé
ne revienne pas par inadvertance.

### Les photos, redimensionnées par le navigateur

Ni GD ni Imagick ne sont installés, et c'est assumé : le navigateur réduit
l'image **avant** l'envoi (`app/lib/resizeImage.js`). Une photo de téléphone
pèse 4 Mo ; la téléverser telle quelle depuis une connexion béninoise prendrait
une minute pour un fichier dont on n'utilise qu'un quart. L'écran affiche ce qui
a été économisé. Le serveur vérifie ce qu'il reçoit, il ne le fabrique pas.

Deux sources : l'appareil photo, ou la banque d'images — dont les fichiers sont
**pointés, jamais copiés**, avec un avertissement quand une photo sert déjà
ailleurs. Remplacer une photo téléversée efface l'ancien fichier ; les photos
livrées avec l'application et celles de la banque ne sont jamais effacées, elles
sont partagées.

Le nom de fichier de la banque est contraint par expression régulière : sans
cela, `../../.env` pointait où il voulait sur le disque. Testé.

### Article de test

`ZZZ — Article de test Axizán (ne pas commander)`, 1 XOF, dans Épicerie &
conserves. **Une commande ne coûtera pas 1 XOF** : les frais de livraison
s'ajoutent — 800 XOF à Cotonou Centre, 1 200 à Porto-Novo. Le plancher réel est
801 XOF, en argent réel puisque les clés FedaPay sont des clés live.

## Gestion des clients (5 sept. 2026)

**211 tests, 882 assertions.** `/admin/clients`.

Rien n'existait : ni liste, ni historique, ni moyen de suspendre un compte.

### Le bug qui rendait la suspension décorative

`users.status` était écrit à l'inscription et **lu nulle part**. Suspendre un
compte n'aurait rien fait — exactement la même classe de bug que l'article
« retiré de la vente » qui restait en boutique.

Deux verrous posés, et le second est le vrai :

- La **connexion** refuse un compte suspendu, avec un message distinct de
  « identifiants incorrects » : la personne doit savoir qu'il est inutile de
  réessayer son mot de passe.
- Un **middleware `active`** sur toutes les routes authentifiées. Bloquer la
  seule connexion n'aurait pas suffi : les jetons Sanctum vivent **trente
  jours**, et un compte suspendu aurait continué de commander pendant tout ce
  temps. Le jeton courant est détruit au passage, et la suspension révoque tous
  les jetons du compte.

Le verrou porte sur l'authentification, pas sur le rôle : un livreur qui rend
son tablier perd aussi ses accès.

### Les impayés — la définition fait tout

Une commande non payée **pour demain** n'est pas un impayé : le client a le
temps. Une commande non payée **dont le créneau est passé** en est un — elle a
occupé une place que personne d'autre n'a pu prendre, et la capacité des
créneaux est le stock réel d'Axizán.

Les commandes annulées ne comptent pas : elles ont libéré leur place.

### La fiche

Ce qu'on ouvre quand quelqu'un appelle : téléphone cliquable, adresses avec
leur zone, historique complet, et les chiffres qui comptent — total **réellement
payé**, panier moyen sur les seules commandes payées, nombre d'impayés et leur
montant. Une moyenne calculée sur tout inclurait des commandes qui n'ont jamais
rapporté un franc.

### Bug de fabrique corrigé

`UserFactory` ne renseignait pas `status`. La colonne a pourtant `active` comme
défaut en base — mais Laravel ne relit pas les valeurs par défaut après
l'insertion, et le modèle en mémoire portait `null`. Le nouveau middleware
rejetait donc des utilisateurs que la base considère comme actifs. Même piège
que `is_available` à la création d'un produit.

## Couverture : Littoral et Atlantique (5 sept. 2026)

**215 tests, 893 assertions.**

La couverture épouse désormais **deux départements entiers**, soit neuf
communes :

| Département | Communes |
|---|---|
| **Littoral** | Cotonou *(commune unique du département)* |
| **Atlantique** | Abomey-Calavi, Allada, Kpomassè, Ouidah, Sô-Ava, Toffo, Tori-Bossito, Zè |

**Porto-Novo sort de la couverture** : la commune appartient au département de
l'Ouémé, pas à l'Atlantique.

### Rien n'est effacé

Des adresses, des commandes et des factures pointent sur la zone de Porto-Novo
et sur le marché Ouando. Les supprimer troueraient l'historique. Ils sont
**désactivés** : ils disparaissent des choix offerts aux nouveaux clients, et
tout ce qui existe reste lisible — testé.

Un détail qui aurait échappé : désactiver la zone ne suffisait pas. L'API des
créneaux interroge `delivery_slots.is_active`, et les créneaux de Porto-Novo
seraient restés réservables par appel direct. Les deux tombent ensemble.

« Cotonou Centre » devient « Cotonou » : la zone couvre la commune entière, pas
un centre-ville dont personne n'avait défini les limites. Renommée plutôt que
recréée, pour préserver les adresses et créneaux déjà rattachés.

### Trois marchés pour neuf communes

| Marché | Dessert |
|---|---|
| Dantokpa (Cotonou) | Cotonou, Abomey-Calavi, Sô-Ava |
| Marché de Ouidah | Ouidah, Kpomassè, Tori-Bossito |
| Marché d'Allada | Allada, Toffo, Zè |

Dantokpa est le marché de gros du Grand Nokoué — les communes voisines s'y
fournissent déjà. Ouidah et Allada existent parce qu'envoyer un préparateur
depuis Cotonou lui ferait faire 40 km avant de commencer sa tournée.

Un dépôt par marché, et le routage préparateur suit automatiquement : il ne voit
que les commandes des zones desservies par son marché.

### Frais de livraison — à réviser

Échelonnés sur l'éloignement du dépôt, de 800 XOF (Cotonou) à 2 500 XOF
(Toffo, Zè). **Ce sont des ordres de grandeur, pas des tarifs négociés.** À
revoir avant l'ouverture réelle de chaque commune.

### Mis à jour partout

Accueil (« Littoral & Atlantique · 9 communes » — une liste de neuf noms ne
tiendrait pas sur la ligne, le détail est en pied de page), pied de page,
métadonnées, manifeste PWA, et le commentaire du contrôleur préparateur qui
citait encore Porto-Novo en exemple.

## Périmètre de lancement, et la fermeture de Dantokpa (5 sept. 2026)

**215 tests.** Trois changements liés.

### Dantokpa n'existe plus

**Fait vérifié : le marché a été définitivement fermé le 2 mai 2026**, ses
~20 000 commerçants relocalisés par catégorie entre le 13 avril et le 2 mai, et
le site rasé. L'application envoyait ses préparateurs approvisionner un marché
détruit depuis quatre mois.

Les vivriers sont partis au **pôle agroalimentaire du Grand Nokoué, rebaptisé
« Tokpa Dahô »** : 168 ha à Akassato (Abomey-Calavi), à 1,5 km de la RNIE 2,
six magasins secs et deux unités de froid. Céréales, tubercules, oignons,
haricot, maïs, fruits, légumes, condiments. C'est désormais la base
d'approvisionnement d'Axizán.

Marchés en service, seedés : **Tokpa Dahô** (Akassato), **Ganhi** (Cotonou,
déc. 2024), **Midombo** (Akpakpa est, nov. 2024), **Cococodji**
(Abomey-Calavi, janv. 2026).

Dantokpa est **désactivé, jamais supprimé** : des préparateurs y sont
rattachés, des fournisseurs y sont enregistrés, et des achats déjà réglés
pointent sur ses étals.

Sources : Jeune Afrique, La Nation Bénin, Matin Libre, Global Voices, gouv.bj.

### Les marchés sont documentés pour le préparateur

Nouvelles colonnes sur `markets` : `address`, `landmark`, `opening_hours`,
`notes`, `maps_query`. L'écran préparateur affiche une fiche du marché avec un
bouton « Y aller » qui ouvre une **recherche** Maps plutôt qu'un point figé —
ces pôles sont neufs et leurs coordonnées ne sont pas encore stables.

Un préparateur ne connaît pas forcément le marché où on l'envoie. Sans adresse
ni horaires, il perd sa matinée.

**À relever sur place** : les étals sont seedés comme « à renseigner ». Aucune
source publique ne donne les noms ni les numéros de halle de ces pôles.

### Périmètre réduit à Cotonou et Abomey-Calavi

Les sept autres communes de l'Atlantique restent en base avec leurs créneaux,
désactivées. Les rouvrir sera un interrupteur, pas une migration. Ouvrir neuf
communes le premier jour aurait promis des livraisons à Zè sans préparateur sur
place ni tarif négocié.

### « En attente de validation » ne veut plus rien dire

Le libellé datait du régime déclaratif, où un administrateur vérifiait le
virement. Depuis FedaPay, **personne ne valide rien** : c'est le client qui doit
payer. Lui dire d'attendre le laissait immobile devant une validation qui
n'arriverait jamais — et sa commande occupait un créneau.

Devenu **« À payer — vos courses n'ont pas encore démarré »**, en or, avec une
icône de carte.

*Vérifié au passage* : les trois commandes en attente portant une transaction
FedaPay sont toutes `canceled` côté opérateur. Personne n'a été débité.

## Le remplacement en direct (5 sept. 2026)

**225 tests, 943 assertions.**

Jusqu'ici, le client indiquait à la commande ce qu'il fallait faire d'un
article introuvable — remplacer ou retirer — puis **n'apprenait plus rien
jusqu'à la livraison**. Il découvrait dans son panier un produit qu'il n'avait
pas choisi. Aucune notification ne partait.

C'est le manque que révélait la comparaison avec Instacart, dont c'est
l'innovation centrale.

### Le parti pris : prévenir et laisser contester

**Pas « demander et attendre ».** Le préparateur est debout à l'étal, sur un
réseau capricieux. L'immobiliser en attendant la réponse d'un client peut-être
en réunion, téléphone au fond d'un sac, coûterait plus cher que le problème
qu'on résout.

Il décide donc, le client est prévenu dans la seconde, et peut refuser **tant
que le panier n'a pas quitté le marché**. Sans réponse, la décision du
préparateur tient — c'est la seule règle qui fonctionne quand l'un des deux n'a
pas de réseau.

### La fenêtre

Elle s'arrête au dépôt. Passé ce point, le préparateur est parti et l'article
est dans le panier : refuser promettrait au client quelque chose qu'on ne peut
plus tenir. L'écran le dit, et l'API refuse (422 `window_closed`).

### Un seul SMS par commande

Le SMS est assumé malgré son coût : la fenêtre est courte, et une notification
in-app que le client ne verrait qu'en rouvrant l'application arriverait trop
tard. C'est exactement le cas prévu par la règle de `config/notifications.php` —
son inaction a une conséquence.

Mais **un seul par commande**, même si trois articles changent. Le SMS ne sert
qu'à faire ouvrir l'application ; trois messages coûteraient trois fois le prix
pour dire la même chose. Les suivants restent in-app.

### Refuser efface aussi la dette fournisseur

Un remplacement refusé remet la ligne à zéro : plus de fournisseur, plus de prix
estampillé. Rien n'est sorti de l'étal — le facturer au partenaire reviendrait à
payer une marchandise jamais retirée.

### Le préparateur le voit

Sa liste distingue « retiré — indisponible » de **« le client a refusé —
reposez-le »**. Dans le second cas il a le produit en main.

## Voie d'essai : éprouver le parcours sans argent (7 sept. 2026)

**228 tests, 971 assertions.**

Les clés FedaPay sont des clés **live** : la moindre commande débite un vrai
compte. Et un article à 1 franc n'existe pas — les frais de livraison portaient
le total à 801 XOF.

### Le couple qui rend le test gratuit

- **`ZZZ — Article de test Axizán`** à 0 XOF
- **`ZZZ — Zone de test (usage interne)`** à 0 XOF de livraison

Total nul, donc **paiement acquitté d'office**. Une commande à zéro franc est
payée d'avance : la laisser attendre bloquerait le client devant un écran lui
demandant de régler zéro, pendant que sa commande occupe un créneau. Le
garde-fou est testé — une commande payante attend toujours son paiement.

La zone est rattachée à Tokpa Dahô, faute de quoi aucun préparateur ne verrait
la commande de test.

### Vérifié en vrai, pas seulement en test

Parcours complet exécuté à travers le tunnel public : commande à 0 XOF →
paiement acquitté → préparateur accepte, démarre, pointe → dépôt avec photo →
livreur accepte, part, transmet sa position, remet avec photo → facture
`FA-20260907-00003`, total 0, les deux photos présentes.

### Deux contradictions corrigées

**Le seeder rouvrait les communes que la migration fermait.** Il écrivait
`is_active => true` pour toutes les zones : la couverture annoncée dépendait du
dernier des deux qui avait tourné. Le périmètre est désormais explicite (`open`),
et les créneaux suivent leur zone.

**Le `sync()` des marchés détachait la voie d'essai** à chaque passage du
seeder, rendant la commande de test invisible.

### Une fausse piste, notée pour mémoire

Les envois de photo semblaient échouer à travers le proxy Next : `curl`
retournait 000 alors que le JSON passait. J'ai remplacé la réécriture par un
passe-plat avant de trouver la vraie cause — **exit code 26, `CURLE_READ_ERROR`** :
`curl` sous Windows ne sait pas lire un chemin `/tmp` de Git Bash. Il n'y avait
aucun bug applicatif ; la réécriture a été restaurée.

*À retenir* : ne pas conclure à un bug applicatif avant d'avoir lu le code de
sortie de l'outil de test.

## Le livreur invisible, et trois courses (20 sept. 2026)

**238 tests, 1045 assertions.**

Parti pour l'écran du personnel, j'ai d'abord lu le chemin du livreur. Il y
manquait plus que des écrans.

### `orders.livreur_id` n'était écrit par personne

Le seul code qui le posait — `OrderService::assignDelivery()` — **n'était
appelé de nulle part**. Le chemin réel (`DeliveryController::accept` →
`DeliveryService::assignOrderToLivreur`) créait la ligne `deliveries` et
s'arrêtait là.

Conséquence visible en production : la salle de contrôle affichait un contact
« Livreur » vide pour **toutes** les commandes, y compris celles en route. Le
commentaire de l'écran promettait l'inverse — *« Le personnel est identifié ET
joignable : l'intérêt de cet écran est de pouvoir appeler la personne
concernée »*. Le jour où un client appelle pour dire que personne n'arrive,
l'administrateur n'avait aucun numéro à composer.

La méthode morte est retirée. Un commentaire prend sa place, pour que le
prochain qui cherche `livreur_id` trouve le bon chemin du premier coup.

### Le SMS le plus utile du parcours ne partait pas

`config/notifications.php` déclare `ORDER_OUT_FOR_DELIVERY` en SMS depuis le
début, avec sa justification écrite : *« Le livreur est en route : le client
doit être là. C'est le SMS le plus utile de tout le parcours. »*

Son unique émetteur était ce même code mort. Le client découvrait le livreur à
sa porte, ou n'entendait pas sonner.

Il part maintenant **au départ du dépôt**, pas à l'acceptation : quand le
livreur prend le panier il est encore sur place, et le client n'a rien à faire
de cette information-là. Le numéro du livreur est dans le texte — ce SMS arrive
quand le client doit être joignable ; s'il a un empêchement, il doit pouvoir
rappeler sans rouvrir l'application.

Le second message, celui qui prévenait le livreur de la course qu'il venait
lui-même d'accepter, est supprimé.

### La capacité des créneaux était vendable deux fois

Le contrôle de capacité vivait dans `OrderController`, **hors de la transaction
de création** : lu à l'entrée, la commande écrite plus loin. Deux clients qui
visent la dernière place à trois secondes d'écart passaient tous les deux.

Ce n'est pas un raffinement de robustesse. Axizán ne détient aucun stock — les
ignames ne manquent pas au marché. Ce qui est rare ici, ce sont les **heures** :
la capacité d'un créneau est le seul stock réel de la plateforme. Une place
vendue deux fois, c'est un préparateur qui découvre le matin une commande de
trop, et un client à rappeler pour lui reprendre son créneau.

Le contrôle entre dans la transaction, verrou posé **sur la ligne du créneau** —
seul point de passage commun à tous ceux qui réservent ce créneau-là. Verrouiller
les commandes existantes n'aurait rien protégé : ce qu'on empêche, ce sont les
lignes qui n'existent pas encore.

Le contrôle du contrôleur reste en place : il répond plus vite, porte les autres
motifs de refus (créneau fermé, délai trop court, date hors horizon), et évite
d'ouvrir une transaction pour rien. Le second est le dernier mot.

### Deux préparateurs, une commande

`acceptOrder()` lisait `canBeAccepted()` puis écrivait, sans rien entre les
deux. Plusieurs préparateurs regardent pourtant la **même liste**. Le second
écrasait le premier — qui était déjà parti acheter, et revenait au dépôt avec un
panier qui ne lui appartenait plus.

La commande est relue à l'intérieur de la transaction, verrou posé. Le second
apprend qu'elle lui échappe, **et pourquoi** : « cette commande ne peut pas être
acceptée » laissait croire à un défaut de la commande.

La relecture porte sur une instance fraîche, pas sur celle reçue en argument —
c'est justement l'écart entre les deux chargements qu'on ferme. Et la mise à
jour passe par le modèle, pas par le constructeur de requêtes : un `UPDATE` de
masse ne déclenche pas les événements Eloquent, et le journal des transitions,
branché sur `updated`, perdrait l'entrée.

### Deux livreurs, un panier

`deliveries.order_id` porte un index `unique()` depuis la migration d'origine :
le doublon était **déjà** arrêté. Mais il répondait `SQLSTATE[23505] : duplicate
key value violates unique constraint`, affiché tel quel sur le téléphone du
second livreur.

Le garde-fou était bon ; seule la phrase manquait. C'est désormais un 409 et
« Un autre livreur vient de prendre ce panier. » Le dépôt montre la liste à tous
les livreurs actifs : la collision est le cas normal, pas l'incident.

### Ce que les tests prouvent, et ce qu'ils ne prouvent pas

La suite tourne sur SQLite, où `lockForUpdate` ne compile rien : **le verrouillage
lui-même ne s'éprouve qu'en PostgreSQL**. Ce que les dix nouveaux tests
établissent, c'est que le contrôle a été déplacé là où le verrou le protège — et
les défauts eux-mêmes, qui sont réels.

Vérifiés en remettant l'ancien code : **8 des 10 échouent avant correction**. Le
plus net est celui du créneau — *« Failed asserting that 2 is identical to 1 »* :
deux commandes sur un créneau d'une place. La survente était démontrable, pas
supposée.

Les deux qui passaient déjà gardent leur utilité : ils empêchent de réintroduire
la notification inutile au livreur, et de verrouiller dehors le préparateur qui
appuie deux fois sur un réseau instable.

### Reste à faire

L'écran du personnel — le chantier pour lequel j'ouvrais ce code. Il peut
maintenant s'appuyer sur `orders.livreur_id`, qui dit enfin la vérité.

## Le personnel, et la course qui ne revient pas (20 sept. 2026)

**254 tests, 1156 assertions.** `/admin/personnel`.

*« Un livreur qui part garde aujourd'hui son accès à vie. »* Le verrou existait
pourtant depuis la gestion des clients — `users.status` coupe la connexion, la
suspension détruit les jetons. Mais rien ne permettait de l'actionner sur
quelqu'un du personnel. **La serrure était posée, sans poignée.**

### Le filtre qui sert

« Sans nouvelle » : comptes actifs sans un seul passage depuis quatorze jours.
C'est le signal qui répond au problème d'origine — personne ne vient déclarer
qu'il arrête, il cesse simplement de venir.

Quatorze jours passent deux week-ends et un empêchement ordinaire ; en deçà, on
signalerait des gens en congé. Les comptes créés récemment sont exclus : embauché
avant-hier, sans passage, quelqu'un serait signalé comme parti — et le filtre
serait ignoré au bout d'une semaine.

### Deux règles de suspension, et elles ne sont pas symétriques

Parce que ce que tient un préparateur et ce que tient un livreur ne sont pas la
même chose.

**Un préparateur : ses commandes repartent au marché.** Rien n'est physiquement
engagé — il n'a pas encore déposé au dépôt. Les lignes déjà pointées sont remises
à zéro, la marchandise étant chez lui ; le suivant repart d'une liste vierge.
Suspendre sans libérer bloquerait ces commandes pour toujours : `preparateur_id`
reste posé, personne d'autre ne peut les prendre, et le client attend une
commande que plus personne ne traite.

Libéré d'office plutôt qu'en exigeant le ménage à la main : une suspension est
souvent une urgence, et c'est le pire moment pour imposer six clics préalables.

**Un livreur en course : c'est refusé.** La marchandise est dans sa sacoche. Lui
couper l'application n'amènerait pas le panier chez le client — ça supprimerait
seulement le moyen de savoir où il est. Le refus **nomme la commande** : sans le
numéro, il faudrait fouiller la salle de contrôle pour trouver laquelle bloque.

Un panier déjà au dépôt ne retient pas son préparateur : son travail sur cette
commande est fini, elle appartient au livreur.

### L'impasse trouvée en vérifiant sur les vraies données

L'écran branché, `Livreur Démo` affichait **trois paniers en main** — restes des
essais du 7 septembre. Avec la règle ci-dessus, son compte devenait
**insuspendable à vie**. J'ai suivi ma propre consigne — « traitez la livraison
dans la salle de contrôle » — et la porte n'ouvrait pas :

- `release` refuse `IN_DELIVERY` : le retrait est déjà fait.
- `canBeCancelled()` exclut `IN_DELIVERY` : pour ne pas annuler sous les pieds
  d'un livreur qui roule.
- `DeliveryService::failDelivery()` existait — **exposée par aucune route**.
  Encore du code mort, comme `assignDelivery()` ce matin.

Ces deux refus sont justes quand la course se déroule. Ils enfermaient tout
quand elle ne se déroule pas : livreur injoignable, moto volée, quelqu'un qui ne
revient jamais. La commande restait `IN_DELIVERY` à vie, le client sans nouvelle
ni remboursement.

**« La course ne revient pas »** est l'issue, dans la salle de contrôle. Elle
marque la livraison échouée, annule la commande, rembourse le client et le
prévient. Le motif est obligatoire.

On ne remet pas la commande « prête » : le panier n'est plus au dépôt, et
annoncer le contraire enverrait un autre livreur chercher une marchandise qui
n'existe plus sur place. **La perte est pour Axizán, et elle est écrite.**

Le bouton n'apparaît que si un livreur a pris la commande. Ailleurs, « Libérer »
ou « Annuler » suffisent — sans rembourser personne.

### Ce que ça dit du reste

Trois morceaux de code mort en une journée, tous sur le parcours livreur :
`assignDelivery()`, la notification de départ, `failDelivery()`. Le rôle livreur
a été écrit puis jamais rebranché. Les écrans préparateur et livreur étaient
hors périmètre par décision du porteur — mais leur **service** ne l'était pas, et
personne n'a relu ce qui restait connecté.

### Reste à faire, par ordre d'utilité

1. **Les créneaux** — fermer un jour, ajuster une capacité, suspendre une zone.
   Depuis ce matin la capacité est défendue par un verrou ; elle n'est toujours
   pas réglable sans passer par la base.
2. **Les chiffres** — les 1,80 % FedaPay manquent toujours dans
   `Order::grossMargin()`.
3. **Le ménage avant mise en ligne** — `orders.payment_method` a encore `'cash'`
   par défaut en migration, ce qui contredit « pas de paiement à la livraison ».
   Contact réel, et 27 produits sur 49 sans photo.
4. **Relire le parcours livreur en entier.** Trois trouvailles en une journée
   sur le même chemin : il y en a probablement d'autres.
