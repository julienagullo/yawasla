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

Medoo est utilisé comme query builder de base, mais il retourne des tableaux bruts. Pour rester aligné avec l'esprit « noyau minimal et léger » du projet (pas de dépendance à un ORM tiers lourd type Doctrine/Eloquent), on construit un ORM maison très simple par-dessus Medoo : une classe abstraite `Model` (pattern Active Record simplifié) dont héritent les entités (`Organisme`, `Annonce`, plus tard `User`/`Role`).

Portée volontairement réduite : pas de relations complexes, pas de lazy loading, pas de unit-of-work. Uniquement l'hydratation objet et le CRUD de base.

### Fonctions nécessaires (classe abstraite `Model`)

- `find(int|string $id): ?static` — récupérer une entité par clé primaire
- `all(array $where = []): static[]` — récupérer une liste, avec filtres optionnels
- `first(array $where): ?static` — récupérer un seul enregistrement selon des critères
- `count(array $where = []): int` — compter les enregistrements
- `exists(): bool` — vérifier si l'entité existe déjà en base
- `save(): bool` — insert ou update selon que l'entité est nouvelle ou existante
- `delete(): bool` — suppression de l'entité
- `fill(array $data): static` — hydratation de l'objet depuis un tableau (ex. résultat Medoo, données de formulaire)
- `toArray(): array` — sérialisation de l'objet vers un tableau
- `paginate(int $page, int $perPage, array $where = []): array` — récupération paginée, utile aussi bien pour la liste d'annonces en back-office que pour la page newsroom publique

### Configuration par entité (à définir dans chaque classe fille)

- `table(): string` — nom de la table associée
- `primaryKey(): string` — nom de la clé primaire (défaut `id`)
- `fillable(): array` — liste des champs autorisés à l'hydratation de masse

### Cache objet

Objectif : amortir la charge quand plusieurs utilisateurs demandent la même donnée en même temps (ex. `Organisme`, une annonce populaire) — pas une optimisation de latence individuelle, mais un enjeu de charge concurrente (ex. pic de visiteurs sur la page newsroom publique).

- **Backend** : APCu (mémoire partagée entre tous les process PHP du serveur, contrairement à la session qui est propre à chaque visiteur et donc inutile pour ce besoin)
- **Portée** : uniquement les lookups par clé primaire (`find($id)`) — pas de cache sur les listes/requêtes filtrées (`all()`, `paginate()`), l'invalidation y serait trop complexe (il faudrait invalider sur tout insert/update de la table)
- **Invalidation** : suppression de la clé de cache correspondante à chaque `save()`/`delete()` de l'entité concernée
- **Fallback** : si l'extension APCu n'est pas disponible (fréquent sur hébergement mutualisé bas de gamme, contrairement au VPS ciblé où elle s'installe en une commande), le cache est simplement désactivé silencieusement — aucune erreur, le site reste fonctionnel sans le gain de perf

### À prévoir (hors socle initial, à activer si besoin)

- Timestamps automatiques (`created_at` / `updated_at`) si activés sur l'entité
- Exceptions dédiées (ex. `ModelNotFoundException`) plutôt que des retours `null` silencieux dans les cas critiques

## Périmètre fonctionnel de la bêta

### Back-office
- Formulaire de connexion (mail + mot de passe, mot de passe oublié, mail d'alerte utilisateur si activé)
- Gestion de l'organisme (informations de l'entité : nom, adresse, etc.)
- Gestion des annonces (création, édition, publication)
- Système multi-utilisateur : la base (rôles/permissions) doit être posée dès le départ dans l'architecture, mais le module de création/gestion d'utilisateurs sera activé un peu plus tard dans la bêta (pas au tout premier lancement)

### Front-office
- Page newsroom publique (bandeau personnalisé, infos de l'organisme, liste des annonces)
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
