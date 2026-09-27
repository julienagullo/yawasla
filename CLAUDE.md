# Yawasla — Contexte projet

## Présentation

Yawasla est une solution de newsroom en ligne destinée aux associations gérant des mosquées, pour les aider dans leur communication (annonces de prières Janaza, actualités, appels au don, etc.), diffusées par notification mail et push.

Le projet repose sur un noyau (core) minimal et léger en open-source (licence Apache 2.0), pensé pour être simple à installer et à utiliser, y compris par des débutants.

## ⚠️ Statut du projet : BETA

**Ce projet est actuellement développé dans le cadre d'une bêta en accès anticipé.**

Le périmètre fonctionnel décrit ci-dessous est volontairement restreint à cette bêta. Le projet final aura d'autres fonctionnalités qui seront ajoutées progressivement après le lancement de cette première version — ne pas anticiper ou développer de fonctionnalités hors de ce périmètre sans demande explicite.

## Stack technique

- **Backend** : PHP 8.0+, gestion des dépendances via Composer
  - La compatibilité PHP 8.0 prime : aucune syntaxe 8.1+ (`readonly`, `enum`, `never`, `new` dans les initialiseurs, callables `f(...)`, `array_is_list`…)
  - `config.platform.php` est fixé à `8.0.30` dans `composer.json` : Composer ne résout que des dépendances compatibles 8.0 (d'où `monolog/monolog ^2`, la v3 exige 8.1)
- **Frontend** : React (compilé via npm)
- **Base de données** : MySQL 8.0+ ou MariaDB 10.11+ (SQLite disponible en option, non recommandé en production)
- **Serveur web** : Apache ou Nginx avec réécriture d'URL
- **SSL** : obligatoire (requis pour les notifications push)
- **Notifications** : API Brevo (mail), Service Workers (push) — l'intégration doit rester interchangeable, éviter le couplage fort à Brevo
- **Hébergement cible** : VPS OVH sous Debian
- **Templates mail** : pas de moteur de template PHP côté projet — on utilise les templates gérés dans Brevo, ou un mail standard (texte simple) en fallback si aucun template Brevo n'est défini

## Couche d'accès aux données (ORM maison)

Active Record simplifié sur Medoo : classe abstraite `Model` (`src/Core/Model.php`), entités dans `src/Entity/` (`Organization`, `User`, `Announcement`, `Media`, identifiants en anglais). Hydratation + CRUD uniquement : pas de relations, lazy loading ni unit-of-work.

- **Colonnes = propriétés publiques typées** (snake_case, même nom que la colonne) ; le type sert de cast dans les deux sens (`"42"` → `int`, `DATETIME` → `DateTimeImmutable`)
- **Défauts calqués sur la migration** : `NULL` → `= null`, `NOT NULL` sans `DEFAULT` → pas de défaut (non initialisée = obligatoire, `save()` lève une `LogicException`), `DEFAULT` → même défaut. Tout changement de schéma se répercute sur l'entité
- `fillable()` = protection contre le mass-assignment uniquement ; `__get`/`__set` lèvent une `LogicException` sur une propriété inconnue (évite les propriétés dynamiques silencieuses en PHP < 8.2)
- **Cache APCu** sur `find($id)` uniquement, désactivé en silence si APCu est absent

### Décisions de schéma (détail dans `database/migrations/`)

- `User.role` : simple colonne (`owner`/`admin`/`user`), pas de tables de rôles pour la bêta
- `Announcement.type` : VARCHAR libre (janaza/actualité/don…)
- `Organization::forDomain($host)` : organisme du domaine, sinon le premier créé → mono comme multi-organisme sans logique en plus
- **FK** : `organization_id` en `ON DELETE CASCADE`, `author_id` en `ON DELETE SET NULL` (d'où nullable), `ON UPDATE CASCADE` partout. Colonnes `*_id` en `BIGINT` pour matcher `'@id'` sur MySQL (sinon FK rejetée, invisible sous sqlite). sqlite : `PRAGMA foreign_keys = ON` au bootstrap, sinon FK ignorées sans erreur

### À prévoir

- Timestamps automatiques (`created_at`/`updated_at`), exceptions dédiées (`ModelNotFoundException`)
- Suppression d'une `Organization` : proposer de migrer ses données vers une autre plutôt que tout supprimer en cascade

## Bootstrap applicatif (back-office)

- **`.env`** : chargé systématiquement via `vlucas/phpdotenv`, en dev comme en prod — pas de dépendance aux vraies variables d'environnement du serveur, pour rester simple à installer (cohérent avec l'objectif du projet d'être utilisable par des débutants)
- **Connexion Medoo** : initialisée avant le routage (fail-fast) — cohérent avec le fait que quasiment toutes les routes de l'app touchent la DB, autant échouer proprement (log + 503) tout de suite plutôt qu'en plein milieu d'un controller
- **Conteneur DI maison** (PSR-11, `src/Core/Container.php`) : autowiring par réflexion, sans dépendance externe
- **Limite de l'autowiring** : pas de résolution de paramètres scalaires (ex. `string $dbHost`) — toute donnée de configuration passe par un objet `Config` typé injecté comme n'importe quel service
- **Gestion des erreurs** (`src/Core/JsonErrorHandler.php`) : API 100% JSON, pas de `APP_DEBUG`, comportement identique en dev et en prod. Un identifiant de corrélation loggé côté serveur remplace l'affichage de la stack trace, jamais exposée au client
- **`back/var/{APP_ENV}/`** : logs, cache de routes et fichier sqlite séparés par environnement — dossiers créés automatiquement au boot, jamais commités (seul `var/.gitkeep` est versionné)
- **`DB_CONNECTION`** (`.env`) : `mysql` (défaut) ou `sqlite` — pour sqlite, `DB_DATABASE` ne contient que le nom du fichier (ex. `database.sqlite`), le chemin complet dans `var/{APP_ENV}/db/` est géré par le bootstrap ; sqlite reste réservé au dev/tests, non recommandé en prod (cf. stack technique)

## Installation et mises à jour

Une installation fraîche = une mise à jour depuis la version 0, même mécanisme (`Core/Migrator`).

- **`APP_VERSION`** (`public/index.php`) : compteur `x.y.z`, minor et patch de 0 à 9 (`0.0.9` → `0.1.0`, `0.9.9` → `1.0.0`). `0.x` pendant la bêta, `1.0.0` = première stable. Toujours `version_compare()`
- **Migrations** : `back/database/migrations/AAAAMMJJ-x.y.z.php` (seule la version ordonne, la date est indicative), closure `function (Medoo $db): void` via l'API Medoo plutôt que du SQL brut (portabilité mysql/sqlite), `IF NOT EXISTS` pour rester rejouables
- **Table `version`** (append-only) : une ligne par migration appliquée, la dernière = version de la base. Absente ou vide = jamais installé (`0.0.0`), sauf si `organizations`/`users` contiennent des données : exception plutôt que réinstaller par-dessus
- **À jour = aucune migration en attente** : une release sans migration ne déclenche pas de mise à jour. En `update_required`, le site public reste en ligne, seule l'administration propose la mise à jour (lancement réservé aux connectés, à brancher avec l'authentification)
- **5 états au boot** (`Core/AppState`, une classe de routes par état dans `src/Routes/`) : `config_required` (pas de `.env` ou base inexistante, erreur 1049), `install_required` (reprise possible après interruption), `update_required`, `ready`, `unavailable`. Toute autre erreur de connexion = **503** (ouvrir l'assistant pendant une panne MySQL laisserait réécrire la config)
- **Cache de routes** : uniquement en `ready` hors dev, sinon un cache figé sur les routes d'install/update resterait servi
- Actions de routes en méthodes (`[$this, 'action']`), jamais en closures (Slim les lie au conteneur). Collation `utf8mb4_unicode_ci` (`0900_ai_ci` absent de MariaDB)

Boot (`src/bootstrap.php`) : env → `Config` → `Paths` → `Logger` → `AppState::resolve()` → `Container` → Slim → routes de l'état + `Http/ShellAction` → `run()`

## Distribution et passerelle PHP → React

- **Point d'entrée unique** `public/index.php` : `/api/*` → Slim, tout le reste → `Http/ShellAction` + `AppShell` servent `resources/app.html` (index.html de Vite, distribution uniquement) en injectant à la place de `<!-- yawasla:boot -->` un `<base href>` et un JSON (`basePath`, `apiBase`, `status` de `GET /api/`). La sous-requête vers `/api/` doit être une requête neuve (sinon Slim réutilise le routage courant → boucle infinie)
- **Front** : `src/app/boot.ts` lit ces données, toutes optionnelles (absentes en dev avec Vite). Build en `base: './'` → marche à la racine comme en sous-dossier
- **Release** : `node scripts/release.mjs` → `dist/yawasla-dev.zip` + `.sha256` (build de développeur : modifications non commitées ou absence de git tolérées, simple avertissement) ; `--release` → `dist/yawasla-x.y.z.zip`, version lue dans `APP_VERSION`, dépôt git et arbre propre exigés. Assemblage isolé dans `build/` (supprimé si succès) : `npm ci` + build du front, `composer install --no-dev`, `php -l`, ZIP sans dépendance. Composer hors PATH : `COMPOSER_BIN`
- **Icône** : un seul fichier `front/public/icon.png` (PNG carré ≥ 512 px, pas de `.ico` ni de jeu de tailles) servant de favicon et d'`apple-touch-icon` — prévu pour être remplacé plus tard par l'icône de l'organisme envoyée depuis l'administration
- **Sécurité** : racine du site sur `public/` ; `back/.htaccess` (`Require all denied`) protège le reste si tout est déposé à la racine, `public/.htaccess` réautorise

## Internationalisation (i18n)

Lancement en France (fr uniquement), mais multilingue prévu dès maintenant, arabe compris. Socle en place, assistant d’installation migré.

- **Back** : ne traduit rien, les erreurs affichables sont des `ApiException` (code, message fr, params, statut) rendues en `{ "error": { "code", "params", "message" } }` — le front traduit via la clé `api.<code>` (param `field` traduit via `api.fields`), `message` sert de fallback. Exception future : les mails
- **Front** : dictionnaire maison sans dépendance dans `src/i18n/` — interface `Messages` à part (`messages.ts`), `locales/{en,fr}.json` typés via `const fr: Messages = frJson`, `t()` dans `i18n.ts` (fr par défaut, `lang`/`dir` sur `<html>`, choix du sélecteur mémorisé en `localStorage`, changement à chaud via `useLocale()` dans `App` et `LocaleRoot` du routeur, qui remonte les pages car les éléments de route sont créés une fois et jamais re-rendus), pluriels via `Intl.PluralRules`, dates/nombres via `Intl`
- **Interpolation** `{name}` en une seule passe (callback), sinon une valeur contenant `{autre}` serait remplacée à son tour
- **`<Trans>`** (`src/i18n/`, pas de rendu propre) : phrase entière avec balises dans le JSON, `components={{ code: <code /> }}`. Découpage `split(/<(\w+)>([\s\S]*?)<\/\1>/)`, balises non déclarées = texte, pas d'attributs ni d'imbrication, jamais de `dangerouslySetInnerHTML`, interpolation après découpage
- **Langue** : interface (navigateur à l'install, `User.locale` en admin, `Organization.locale` en public) ≠ contenu (annonces non traduites)
- **RTL** : propriétés CSS logiques (`inset-inline-end`…) plutôt que `left`/`right` ; police arabe à prévoir (Fraunces/Inter sans glyphes arabes)

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

- [x] Mise en place du socle technique (installation PHP/Composer, structure projet, connexion base de données)
- [x] Système d'installation de la base de données + création organisme et utilisateur principal
- [x] Tests d’installation (vérifier la facilité de mise en place pour un développeur débutant)
- [ ] Système d'authentification (formulaire de connexion, mot de passe oublié, mail d'alerte)
- [ ] Architecture des rôles/permissions (base technique pour le multi-utilisateur)
- [ ] Module gestion de l'organisme (back-office)
- [ ] Module gestion des annonces (back-office)
- [ ] Tableau de bord simplifié
- [ ] Front-office : page newsroom publique (bandeau, infos organisme, liste des annonces)
- [ ] Front-office : formulaire d'inscription aux notifications push
- [ ] Front-office : page mention légale dynamique
- [x] Page statique de présentation sur le domaine yawasla.org (préparer le référencement)

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
