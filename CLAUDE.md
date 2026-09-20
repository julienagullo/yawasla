# Yawasla — Contexte projet

## Présentation

Yawasla est une solution de newsroom en ligne destinée aux associations gérant des mosquées, pour les aider dans leur communication (annonces de prières Janaza, actualités, appels au don, etc.), diffusées par notification mail et push.

Le projet repose sur un noyau (core) minimal et léger en open-source (licence Apache 2.0), pensé pour être simple à installer et à utiliser, y compris par des débutants.

## ⚠️ Statut du projet : BETA

**Ce projet est actuellement développé dans le cadre d'une bêta en accès anticipé.**

Le périmètre fonctionnel décrit ci-dessous est volontairement restreint à cette bêta. Le projet final aura d'autres fonctionnalités qui seront ajoutées progressivement après le lancement de cette première version — ne pas anticiper ou développer de fonctionnalités hors de ce périmètre sans demande explicite.

## Stack technique

- **Backend** : PHP 8.0+, gestion des dépendances via Composer
- **Frontend** : React (compilé via npm)
- **Base de données** : MySQL 8.0+ ou MariaDB 10.11+ (SQLite disponible en option, non recommandé en production)
- **Serveur web** : Apache ou Nginx avec réécriture d'URL
- **SSL** : obligatoire (requis pour les notifications push)
- **Notifications** : API Brevo (mail), Service Workers (push) — l'intégration doit rester interchangeable, éviter le couplage fort à Brevo
- **Hébergement cible** : VPS OVH sous Debian
- **Templates mail** : pas de moteur de template PHP côté projet — on utilise les templates gérés dans Brevo, ou un mail standard (texte simple) en fallback si aucun template Brevo n'est défini

## Couche d'accès aux données (ORM maison)

Medoo est utilisé comme query builder de base, mais il retourne des tableaux bruts. Pour rester aligné avec l'esprit « noyau minimal et léger » du projet (pas de dépendance à un ORM tiers lourd type Doctrine/Eloquent), on construit un ORM maison très simple par-dessus Medoo : une classe abstraite `Model` (pattern Active Record simplifié) dont héritent les entités. Identifiants de code en anglais même si le reste du projet est documenté en français : `Organization`, `User`, `Announcement`, `Media`.

Portée volontairement réduite : pas de relations complexes, pas de lazy loading, pas de unit-of-work. Uniquement l'hydratation objet et le CRUD de base.

Interface complète (`find`, `all`, `first`, `count`, `exists`, `save`, `delete`, `fill`, `toArray`, `paginate`, config par entité via `table()`/`primaryKey()`/`fillable()`) : voir `src/Core/Model.php`, implémenté.

### Entités (`src/Entity/`)

`Organization`, `User`, `Announcement`, `Media` — schéma exact dans `database/migrations/` et les classes elles-mêmes, pas dupliqué ici. Décisions notables non visibles dans le code :
- `User.role` : simple colonne (`owner`/`admin`/`user`), pas de tables `roles`/`permissions` dédiées pour la bêta
- `Announcement.type` : VARCHAR libre pour le regroupement (janaza/actualité/don...), pas de champs structurés par type
- `Media` : indépendant des annonces, pas un attachement — confirmé par le cahier des charges ("Média" est un type de contenu à part, comme "Prières")
- `Organization.domain` : `Organization::forDomain($host)` retourne l'organization dont le domaine correspond, sinon la première créée (fallback) — couvre nativement le cas mono-organisme actuel de la bêta tout en restant utilisable en multi-organisme (auto-hébergement) sans logique supplémentaire
- Contraintes `FOREIGN KEY` en DB (via fragments SQL bruts dans `create()`, ex. `'FOREIGN KEY (x) REFERENCES y(id) ON DELETE ... ON UPDATE ...'`) : `organization_id` en `ON DELETE CASCADE` (supprimer une organization supprime son contenu), `announcements.author_id` en `ON DELETE SET NULL` (supprimer un `User` garde ses annonces, juste sans auteur — d'où `author_id` nullable). `ON UPDATE CASCADE` partout (les deux relations) : si jamais un `id` référencé changeait, propager la nouvelle valeur a toujours du sens, contrairement à `SET NULL` qui ne se justifie que quand la ligne référencée disparaît (delete). Ne contredit pas "pas de relations complexes" : ça reste de l'intégrité DB, pas du lazy-loading/traversal dans l'ORM
- Les colonnes `*_id` doivent être `BIGINT` (pas `INT`) pour matcher le type généré par `'@id'` sur MySQL (`BIGINT AUTO_INCREMENT`) — sinon la contrainte FK est rejetée (type incompatible), erreur qui n'apparaît que sur MySQL, pas sqlite (typage dynamique)
- **sqlite n'applique pas les FK par défaut** : `PRAGMA foreign_keys = ON` exécuté sur la connexion sqlite au bootstrap, sinon les contraintes existent dans le schéma mais ne sont jamais vérifiées (silencieux, aucune erreur)

### Cache objet (APCu, implémenté dans `find()`)

Objectif : amortir la charge quand plusieurs utilisateurs demandent la même donnée en même temps (ex. `Organization`, une annonce populaire) — pas une optimisation de latence individuelle, mais un enjeu de charge concurrente (ex. pic de visiteurs sur la page newsroom publique). D'où le choix d'APCu (partagé entre tous les process PHP) plutôt que la session (propre à chaque visiteur, inutile ici).

Portée volontairement limitée à `find($id)` — pas de cache sur `all()`/`paginate()`, l'invalidation y serait trop complexe (il faudrait invalider sur tout insert/update de la table). Fallback silencieux si APCu absent (fréquent en mutualisé bas de gamme) : cache désactivé, site fonctionnel sans le gain de perf.

### À prévoir (hors socle initial, à activer si besoin)

- Timestamps automatiques (`created_at` / `updated_at`) si activés sur l'entité
- Exceptions dédiées (ex. `ModelNotFoundException`) plutôt que des retours `null` silencieux dans les cas critiques
- Suppression d'une `Organization` : `ON DELETE CASCADE` supprime tout son contenu (`users`/`announcements`/`media`) — voulu, pas de `RESTRICT` (on ne va pas faire supprimer chaque élément à la main). À terme, proposer une option pour migrer les données vers une autre `Organization` avant suppression, plutôt que la perte définitive comme seule option

## Bootstrap applicatif (back-office)

- **`.env`** : chargé systématiquement via `vlucas/phpdotenv`, en dev comme en prod — pas de dépendance aux vraies variables d'environnement du serveur, pour rester simple à installer (cohérent avec l'objectif du projet d'être utilisable par des débutants)
- **Connexion Medoo** : initialisée avant le routage (fail-fast) — cohérent avec le fait que quasiment toutes les routes de l'app touchent la DB, autant échouer proprement (log + 503) tout de suite plutôt qu'en plein milieu d'un controller
- **Conteneur DI maison** (PSR-11, `src/Core/Container.php`) : autowiring par réflexion, pas de dépendance externe (PHP-DI, league/container) — cohérent avec le choix déjà fait pour l'ORM maison plutôt que Doctrine/Eloquent
- **Limite volontaire de l'autowiring** : pas de résolution de paramètres scalaires (ex. `string $dbHost`) — toute donnée de configuration passe par un objet `Config` typé injecté comme n'importe quel service, plutôt que du binding de valeurs une par une
- **Gestion des erreurs** (`src/Core/JsonErrorHandler.php`) : API 100% JSON, pas de `APP_DEBUG` — comportement identique dev/prod par choix : un identifiant de corrélation loggé côté serveur remplace l'affichage de la stack trace, jamais exposée au client même en dev (elle serait inutilisable dans une réponse JSON de toute façon)
- **`back/var/{APP_ENV}/`** : logs, cache de routes et fichier sqlite séparés par environnement — dossiers créés automatiquement au boot, jamais commités (seul `var/.gitkeep` est versionné)
- **`DB_CONNECTION`** (`.env`) : `mysql` (défaut) ou `sqlite` — pour sqlite, `DB_DATABASE` ne contient que le nom du fichier (ex. `database.sqlite`), le chemin complet dans `var/{APP_ENV}/db/` est géré par le bootstrap ; sqlite reste réservé au dev/tests, non recommandé en prod (cf. stack technique)

## Installation et mises à jour (mécanisme de versioning)

Une installation fraîche est traitée comme une mise à jour depuis la version 0 — même mécanisme pour les deux cas.

- **`APP_VERSION`** : chaîne `x.y.z` définie dans `public/index.php` avant de charger le bootstrap, ex. `define('APP_VERSION', '0.0.1');`. Règle d'incrémentation façon compteur : à chaque mise à jour on incrémente le dernier chiffre (patch) ; s'il atteint 9, on repasse à 0 et on incrémente le second chiffre (minor) ; si le minor atteint 9, on repasse à 0.0 et on incrémente le premier chiffre (major). Seul le major peut dépasser 9 (10, 11, ...), minor et patch restent des chiffres uniques (0-9)
- **`"0.0.0"` est réservé** comme marqueur interne "jamais installé" (voir plus bas) — ne jamais l'utiliser comme valeur de `APP_VERSION` réellement livrée, ça bloquerait le mécanisme (aucune migration ne peut satisfaire à la fois `> 0.0.0` et `<= 0.0.0`). Tant que le projet est en bêta, on reste en `0.x` (première version réelle : `0.0.1`), `1.0.0` étant réservé à la première version stable
- **Comparaison** : jamais de comparaison de chaîne brute (`"10.0.0" < "9.0.0"` serait vrai lexicographiquement, donc faux) — toujours `version_compare()` (natif PHP, gère correctement les segments à plusieurs chiffres)
- **Table `version`** (append-only, créée automatiquement par `Migrator`) : une ligne par version atteinte (`id` auto-increment, `version VARCHAR(20)`, `installed_at DATETIME`). La version actuelle de la DB = version de la dernière ligne insérée (triée par `id DESC`) ; `"0.0.0"` si la table est vide → jamais installé
- **Garde-fou de cohérence** : une table `version` vide ne veut dire "jamais installé" que si aucune autre table n'existe en base. Si d'autres tables sont déjà présentes (restauration partielle, suppression accidentelle des lignes de `version`...), `Migrator::currentVersion()` lève une exception plutôt que de relancer silencieusement l'assistant d'installation sur un site qui a déjà des données
- **Migrations** : fichiers PHP dans `back/database/migrations/`, nommés `AAAAMMJJ-x.y.z.php` (ex. `20260920-1.0.0.php`) — la date est une convention de lisibilité/traçabilité, seul le numéro de version après le tiret sert à l'ordonnancement (`uksort` + `version_compare`) et à la comparaison. Chaque fichier retourne une closure `function (Medoo $db): void` qui utilise l'API de Medoo (`create()`, la syntaxe `'@id'` pour les colonnes identité, etc.) plutôt que du SQL brut, pour rester portable entre mysql et sqlite (ex. gestion différente de l'auto-increment selon le moteur)
- **`Yawasla\Core\Migrator`** : lit la version courante, détermine les migrations en attente (`version_compare($version, $courante, '>') && version_compare($version, $cible, '<=')`), les exécute dans l'ordre croissant, insère une ligne `version` après chacune
- **Aiguillage au boot, 3 états** (`src/bootstrap.php`) :
  - `dbVersion === '0.0.0'` → `src/install-routes.php` (installation fraîche — exécute les migrations ; le `POST /install` a un TODO pour créer réellement l'`Organization` + le premier `User` à partir des données reçues)
  - `version_compare(dbVersion, APP_VERSION, '<')` (et non "0.0.0") → `src/update-routes.php` (exécute uniquement les migrations en attente, ne redemande jamais organisme/admin)
  - `dbVersion === APP_VERSION` → `src/routes.php` (fonctionnement normal)
- **Cache de routes** : n'est activé que dans l'état "normal" (`dbVersion === APP_VERSION` et `!APP_ENV=dev`) — pendant install/update, l'ensemble de routes chargé change selon l'état de la DB, un cache figé sur le mauvais ensemble resterait servi indéfiniment après transition d'état

Séquence de boot : env → `Config` → `Logger` → connexion `Medoo` → `Model::setConnection()` → `Container` → App Slim → routes → `run()`

## Périmètre fonctionnel de la bêta

### Back-office
- Formulaire de connexion (mail + mot de passe, mot de passe oublié, mail d'alerte utilisateur si activé)
- Gestion de l'organisme (informations de l'entité : nom, adresse, etc.)
- Gestion des annonces (`Announcement` — création, édition, publication)
- Gestion des médias (`Media` — pages de média audio, indépendantes des annonces : titre, description, fichier audio, publication)
- Système multi-utilisateur : la base (rôles/permissions) doit être posée dès le départ dans l'architecture, mais le module de création/gestion d'utilisateurs sera activé un peu plus tard dans la bêta (pas au tout premier lancement)

### Front-office
- Page newsroom publique (bandeau personnalisé, infos de l'organisme, liste des annonces)
- Liste des médias (pages audio publiques, distinctes des annonces)
- Inscription aux notifications push

## Hors périmètre pour cette bêta

Ne pas développer sans demande explicite : tout ce qui n'est pas listé ci-dessus dans le périmètre fonctionnel (d'autres fonctionnalités sont prévues pour la version finale mais ne sont pas précisées ici pour rester concentré sur la bêta).

---

## TODO

### T1 2027

- [ ] Mise en place du socle technique (installation PHP/Composer, structure projet, connexion base de données)
- [ ] Système d'authentification (formulaire de connexion, mot de passe oublié, mail d'alerte)
- [ ] Architecture des rôles/permissions (base technique pour le multi-utilisateur)
- [ ] Module gestion de l'organisme (back-office)
- [ ] Module gestion des annonces (back-office)
- [ ] Tableau de bord simplifié
- [ ] Front-office : page newsroom publique (bandeau, infos organisme, liste des annonces)
- [ ] Front-office : formulaire d'inscription aux notifications push
- [ ] Tests d'installation (vérifier la facilité de mise en place pour un développeur débutant)
- [ ] Page statique de présentation sur le domaine yawasla.org (préparer le référencement)

### T2 2027

- [ ] Intégration Brevo pour l'envoi de mail (pensé interchangeable)
- [ ] Mise en place des notifications push (Service Worker, SSL, gestion des permissions navigateur)
- [ ] Front-office : formulaire d'inscription aux annonces par mail

### T3 2027

- [ ] Intégration du module de création/gestion des utilisateurs (multi-user complet)
- [ ] Intégration de la gestion du compte et des paramètres d'affichage

### T4 2027

- [ ] Ajout du tableau de bord et des graphiques
- [ ] Site vitrine de présentation sur le domaine yawasla.org
- [ ] Documentation technique pour l'installation et la mise en place

### T1 2028

- [ ] Gestion des modules
- [ ] Gestion des prières
- [ ] Gestion utilisateur complet
