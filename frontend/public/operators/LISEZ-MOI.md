# Logos des opérateurs Mobile Money

Déposez ici les fichiers officiels, puis renseignez le champ `logo` dans
`frontend/app/components/OperatorMark.js`. Rien d'autre ne bouge dans
l'application : tous les écrans passent par ce composant.

| Fichier attendu | Opérateur | Ligne à modifier |
|---|---|---|
| `mtn.png` | MTN MoMo | `mtn: { … logo: '/operators/mtn.png' }` |
| `moov.png` | Moov Money | `moov: { … logo: '/operators/moov.png' }` |
| `celtiis.png` | Celtiis Cash | `celtiis: { … logo: '/operators/celtiis.png' }` |

En attendant, l'application affiche des pastilles typographiques aux couleurs
de chaque opérateur.

## Ce qu'il faut savoir avant de choisir un fichier

**Format.** SVG de préférence, sinon PNG à fond transparent, carré, au moins
128 × 128. Le logo est affiché dans un carré blanc de 44 px de côté avec une
marge intérieure : un fichier rectangulaire ou à fond coloré s'y intègre mal.

**Où les trouver.** Sur les espaces presse ou « brand kit » des opérateurs.
Évitez les logos glanés dans un moteur de recherche : ils sont souvent
périmés, déformés ou dans une résolution inutilisable.

**Droits.** Afficher le logo d'un opérateur pour signaler un moyen de paiement
accepté est un usage courant et légitime — c'est ce que fait tout commerçant
avec Visa ou Mastercard. Mais ce sont des marques déposées : utilisez les
fichiers officiels, sans les redessiner ni les recolorer. Une reproduction
approximative reconnaît mal la marque et l'abîme.
