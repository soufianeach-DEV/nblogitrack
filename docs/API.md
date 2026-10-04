# API partenaires — version 1

L'API permet à une entreprise cliente de déposer des expéditions et de suivre les siennes depuis son propre logiciel (ERP, WMS, boutique en ligne).

- Adresse de base : `https://<domaine>/api/v1`
- Format : JSON en entrée comme en sortie, encodé en UTF-8.
- Spécification OpenAPI 3 : [`openapi.yaml`](openapi.yaml). L'application l'affiche dans Swagger UI sur `https://<domaine>/api/docs`, d'où l'on peut essayer les appels avec sa clé.
- Collection Postman : [`postman/`](postman/). Les clés se renseignent dans l'environnement `NBLogiTrack`, en variables secrètes (`cleApi`, `cleApiBloquee`) : elles ne figurent jamais dans la collection.
- Toutes les réponses sont en JSON, y compris les erreurs, que l'appel envoie `Accept: application/json` ou non.

## Authentification

Un administrateur de la plateforme crée la clé depuis l'écran **API REST** (menu **Système**). Le secret n'est montré qu'une fois, au moment de la création.

Chaque appel présente la clé dans l'en-tête `Authorization` :

```
Authorization: Bearer nblt_xxxxxxx.secret
```

Une clé porte des permissions (`lecture`, `ecriture`). Elle peut aussi avoir une date d'expiration et une liste d'adresses IP autorisées.

- **Clé d'entreprise** : elle ne voit et ne dépose que les expéditions de son entreprise.
- **Clé interne** : elle n'est rattachée à aucune entreprise. Elle est réservée à l'exploitation, lit toutes les expéditions et ne peut rien déposer.

| Code | Motif (`motif`)      | Cause                                                   |
|------|----------------------|---------------------------------------------------------|
| 401  | `jeton_absent`       | Aucun en-tête `Authorization: Bearer`                   |
| 401  | `cle_inconnue`       | La clé n'existe pas ou le secret est faux               |
| 401  | `revoquee`           | La clé a été révoquée                                   |
| 401  | `expiree`            | La date d'expiration est dépassée                       |
| 403  | `adresse_refusee`    | L'adresse IP de l'appel n'est pas dans la liste de la clé |
| 403  | `permission_absente` | La clé n'a pas la permission demandée                   |
| 403  | `entreprise_inactive`| L'entreprise n'est pas validée ou elle est désactivée   |

Toute réponse 401 porte l'en-tête `WWW-Authenticate: Bearer realm="api"`.

## Langue des messages

Les messages d'erreur sont rédigés en français, en néerlandais ou en anglais. La langue suit l'en-tête `Accept-Language` (`fr`, `nl`, `en`). Sans cet en-tête, c'est la langue du compte de l'entreprise une fois la clé acceptée ; un refus de clé (401, 403) et la limite de débit (429) répondent alors en français.

## Limite de débit

Chaque clé peut faire 120 appels par minute, et chaque adresse IP 300 au total. Au-delà, l'API répond `429 Too Many Requests`, avec le motif `limite_depassee` et un en-tête `Retry-After` qui donne le nombre de secondes à attendre.

## Journal des appels

Chaque appel qui atteint le contrôle de la clé est inscrit au journal, accepté ou refusé : méthode, chemin, statut, durée, adresse IP et motif d'un refus. Les appels arrêtés par la limite de débit y figurent aussi, avec le motif `limite_depassee` : le premier de chaque minute pour une même clé et une même adresse IP, et cinq au plus par minute pour une adresse IP. Un flot d'appels ne remplit donc pas le journal. L'administrateur le consulte sur l'écran **API REST**.

## Lister les expéditions

`GET /expeditions` (permission `lecture`)

| Paramètre  | Type         | Description                              |
|------------|--------------|------------------------------------------|
| `statut`   | texte        | `PENDING`, `ASSIGNED`, `IN_PROGRESS`, `DELIVERED`, `CANCELLED` |
| `depuis`   | date         | Expéditions créées à partir de ce jour (`AAAA-MM-JJ`) |
| `par_page` | entier 1–100 | 25 par défaut                            |

```json
{
  "data": [ { "numero": "TRK-2026-00042", "statut": "PENDING", "priorite": "NORMAL", "depart": "3500 Hasselt", "arrivee": "1000 Bruxelles", "pays_depart": "BE", "pays_arrivee": "BE", "enlevement_prevu": "2026-10-05", "enlevement_prevu_a": "2026-10-05T07:00:00+02:00", "livraison_prevue": "2026-10-09", "livraison_reelle": null } ],
  "meta": { "page": 1, "par_page": 25, "total": 1, "pages": 1 },
  "liens": { "suivante": null, "precedente": null }
}
```

## Lire une expédition

`GET /expeditions/{numero}` (permission `lecture`)

La réponse reprend les champs de la liste, avec en plus :

- les adresses complètes, le poids, la marchandise, `matieres_dangereuses`, `hayon`, `distance_km`, `prix_ht`, `tarif`, `expediteur`, `reference_chargement` et `creee_le` ;
- `code_suivi` et `suivi_url`, le lien de la page de suivi publique à transmettre au destinataire.

Une expédition inconnue, ou qui appartient à une autre entreprise, renvoie `404`.

## Déposer une expédition

`POST /expeditions` (permission `ecriture`, clé d'entreprise)

| Champ                  | Obligatoire | Description |
|------------------------|-------------|-------------|
| `enlevement`           | oui | Adresse d'enlèvement, avec le code postal et la localité |
| `livraison`            | oui | Adresse de livraison, avec le code postal et la localité |
| `poids`                | oui | En kg, jusqu'à la charge utile du plus gros camion |
| `volume`               | non | En m³ |
| `marchandise`          | oui | `Boissons`, `Colis express`, `Machines`, `Matériaux de construction`, `Matériel électronique`, `Mobilier`, `Palettes`, `Pièces automobiles`, `Produits alimentaires`, `Produits chimiques`, `Produits pharmaceutiques`, `Textile` ou `Autre` |
| `matieres_dangereuses` | selon la marchandise | Booléen. Obligatoire pour `Produits chimiques`, `Produits pharmaceutiques`, `Matériel électronique` et `Pièces automobiles` |
| `hayon`                | non | Booléen |
| `date_enlevement`      | oui | `AAAA-MM-JJ`, aujourd'hui au plus tôt, un jour ouvrable du pays d'enlèvement |
| `date_livraison`       | oui | `AAAA-MM-JJ`, au plus tôt le jour de l'enlèvement |
| `formule`              | non | `ECO`, `STANDARD` ou `EXPRESS`. Sans ce champ, l'API prend la moins rapide des formules qui tiennent le délai |
| `pays_livraison`       | non | Code ISO à deux lettres. Par défaut, le pays écrit dans l'adresse, sinon la Belgique |
| `pays_enlevement`      | non | Même règle que `pays_livraison` |
| `expediteur`, `telephone_expediteur` | hors de Belgique | Le contact sur le lieu de chargement |
| `reference_chargement` | non | Votre référence (60 caractères au plus), unique pour chaque envoi |
| `instructions`         | non | 500 caractères au plus |

Règles appliquées, les mêmes que pour une commande passée en ligne :

- **Pays** : le pays écrit dans une adresse doit correspondre au pays déclaré.
- **Heure d'enlèvement** : l'enlèvement est prévu à l'ouverture du quai. Le jour même, il est prévu dès maintenant.
- **Délai** : une formule demandée qui ne peut pas livrer à la date voulue, route comprise, est refusée. L'API ne la remplace pas en silence.
- **Priorité** : avec deux jours ou moins pour livrer, l'expédition est marquée `URGENT`.
- **Prix** : il est calculé par le serveur et renvoyé dans `prix_ht`.
- **Courriel** : les administrateurs et les responsables des commandes de l'entreprise reçoivent le code de suivi par e-mail.

La réponse `201 Created` contient l'expédition détaillée. L'en-tête `Location` pointe vers `GET /expeditions/{numero}`.

### Éviter les doublons

Un appel peut échouer côté réseau alors que l'expédition a bien été créée. Deux protections évitent de la créer deux fois :

- **En-tête `Idempotency-Key`**, par exemple un UUID, 100 caractères au plus. Si le même envoi est rejoué avec la même clé, l'API renvoie l'expédition déjà créée, avec l'en-tête `Idempotent-Replayed: true`.
- **`reference_chargement`** : une référence déjà déposée, sur une expédition non annulée, est refusée avec `409 Conflict`. La réponse donne le `numero` de l'expédition existante.

## Erreurs

| Code | Sens |
|------|------|
| 404  | Expédition ou adresse d'API inconnue |
| 409  | Référence de chargement déjà déposée |
| 422  | Données refusées. `message` explique la cause, et `errors` donne le détail par champ pour une erreur de validation. Avec `motif: encours`, l'entreprise a trois factures en retard, ou ses factures dues TTC, augmentées du prix TTC de cette expédition, dépasseraient son plafond de crédit |
| 429  | Limite de débit dépassée (motif `limite_depassee`) |
| 503  | Vérification des adresses momentanément indisponible. Réessayez après le délai de `Retry-After` |

## Exemple

```bash
curl -X POST https://<domaine>/api/v1/expeditions \
  -H "Authorization: Bearer nblt_xxxxxxx.secret" \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: 7f1c2d4e-0b7a-4f7e-9c1d-2a6b8e3f5a10" \
  -d '{
        "enlevement": "Rue Neuve 43, 3500 Hasselt",
        "livraison": "Avenue Louise 200, 1000 Bruxelles",
        "poids": 850,
        "marchandise": "Palettes",
        "date_enlevement": "2026-10-05",
        "date_livraison": "2026-10-09",
        "reference_chargement": "CH-2026-0142"
      }'
```
