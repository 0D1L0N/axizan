# Axizán Simple Apache/PHP

Prototype rapide pour une application de commande alimentaire utilisant Apache, PHP, et SQLite.

## Démarrage rapide

```bash
cd simple-app
docker compose up --build
```

Puis ouvrez:

```
http://localhost:8080
```

## Fonctionnalités

- Catalogue produit simple
- Panier en JavaScript
- Commandes enregistrées avec SQLite
- API très légère en PHP

## Architecture

- `Dockerfile` : image Apache + PHP + SQLite
- `docker-compose.yml` : service web
- `src/index.php` : interface utilisateur
- `src/api.php` : API JSON
- `src/db.php` : initialisation et stockage SQLite
