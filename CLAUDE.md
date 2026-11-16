# CLAUDE.md — Axizán

> Lu automatiquement par Claude Code au début de chaque session.
> Court et à jour : mettre à jour la section « État actuel » en fin de session.

## Le projet en une phrase
Plateforme de livraison de produits alimentaires du marché au Bénin (Cotonou, Littoral et Atlantique) : le client commande, un **préparateur** achète au marché, un **livreur** livre avec preuve photo, un **admin** pilote.

## Source de vérité
- **`CHANTIERS.md`** = suivi d'exécution et décisions métier. À lire avant toute modification. Respecter la section « Hors périmètre ».
- `README.md` et `docs/` sont en partie **anciens** (ils parlent de JWT, Laravel 11, React 19) : se fier au code.

## Stack réelle
- **Backend** : Laravel 12, PHP 8.2, auth **Sanctum** (jetons 30 jours), PostgreSQL 15, Redis (cache, sessions, files d'attente).
- **Frontend** : Next.js 15 (App Router), React 18, Tailwind 3. Groupes de routes : `(auth)`, `(client)`, `(staff)/admin|preparateur|livreur`.
- **Paiement** : Mobile Money par demande poussée via FedaPay (`app/Services/FedapayService.php`).
- **Services métier** : `backend/app/Services/` (Order, Delivery, Payment, Invoice, SupplierSettlement, OrderWatch, Notification, Sms).

## Lancer et tester
```bash
docker compose up -d                                   # postgres, redis, laravel:8000, queue, scheduler, nextjs:3000, mailpit:8025
docker compose exec -T laravel ./vendor/bin/phpunit    # suite backend — doit passer à la fin de chaque lot
cd frontend && npm run lint && npm run build           # ce que vérifie la CI (.github/workflows/ci.yml)
```
Après un clone ou un déplacement : `composer install` dans `backend/`, `npm install` dans `frontend/`.
Aide-mémoire complet : `docs/DEMARRER.html`.

## Règles de travail
- Un lot = un ensemble cohérent qui laisse le dépôt en état de marche ; cocher dans `CHANTIERS.md` et noter au Journal.
- Toute correction de comportement métier s'accompagne d'un test (`backend/tests/Feature/`).
- **Monnaie : XOF, arrondi au franc entier**, jamais de décimales dans les totaux.
- Messages de commit en français, une phrase qui dit ce qui change pour l'utilisateur (ex. « Les créneaux : capacité, zones suspendues et jours fermés »).
- Windows : ne jamais commiter de changements qui ne portent que sur les fins de ligne (CRLF/LF). Vérifier avec `git diff --ignore-cr-at-eol`.

## État actuel (16 nov. 2026)
- Dossier : `D:\Projets\axizan` (déplacé depuis `C:\Users\B4DM4N\source\repos\axizan`).
- Branche de travail **`presentation`**, poussée sur GitHub, en avance sur `master` (travail de septembre + créneaux). Une PR `presentation → master` reste à ouvrir.
- **14 fichiers modifiés non commités** (contrôleurs API, `DeliveryService`, `User`, `composer.json`/`.lock`, `LivreurHandoverTest`).
- La branche **`sauvegarde/copie-projects-presentation`** contient un commit WIP (codes promo client, blocage des livreurs non vérifiés, collecte préparateur, CI) qui n'est pas encore reporté dans `presentation`. Il recoupe en partie les fichiers modifiés ci-dessus.
