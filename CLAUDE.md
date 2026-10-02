# Yawasla — Contexte projet

Newsroom en ligne pour les associations gérant des mosquées (annonces Janaza, actualités, appels au don…), diffusées par mail et push. Noyau open-source (Apache 2.0) minimal, **simple à installer par des débutants** : ce critère arbitre les choix techniques.

## ⚠️ BETA

Périmètre volontairement restreint (voir plus bas). Ne rien développer hors périmètre sans demande explicite.

## Contraintes

- **PHP 8.0** : aucune syntaxe 8.1+ (`readonly`, `enum`, `never`, `new` en initialiseur, `f(...)`, `array_is_list`…). `config.platform.php` = `8.0.30` dans `composer.json` (d'où `monolog ^2`)
- **MySQL 8 / MariaDB 10.11** ; SQLite pour le dev uniquement
- **SSL obligatoire** (push). Hébergement cible : VPS OVH Debian, mais aussi mutualisé
- **Mail** : deux voies distinctes. Transactionnel/sécurité (lien de connexion, reset) via `Mail\Mailer` (PHPMailer, SMTP ou `mail()` natif selon `MAIL_*` du `.env`), **indépendant de Brevo** pour ne pas coupler la connexion admin au provider de diffusion. Diffusion (annonces) via Brevo, interchangeable. Pas de moteur de template PHP : templates Brevo, sinon texte simple. Prod : SPF/DKIM/DMARC + PTR requis sinon spam
- **`.env` toujours chargé** (dev comme prod), jamais de variables d'environnement serveur : plus simple à installer
- **Pas de `APP_DEBUG`** : comportement identique dev/prod, jamais de stack trace au client (identifiant de corrélation loggé)
- Autowiring du conteneur maison : pas de scalaires, la configuration passe par l'objet `Config`

## Décisions et pièges

**Données**
- ORM maison volontairement limité (hydratation + CRUD) : pas de relations ni de lazy loading
- Les propriétés d'une entité reflètent la migration (nullable/défaut) : tout changement de schéma se répercute sur l'entité
- `User.role` en simple colonne, `Announcement.type` en VARCHAR libre : pas de tables dédiées pendant la bêta
- Colonnes `*_id` en `BIGINT` (sinon FK rejetée par MySQL, invisible sous SQLite). Collation `utf8mb4_unicode_ci` (`0900_ai_ci` absent de MariaDB)
- À prévoir : timestamps auto, `ModelNotFoundException`, suppression d'organisme en migrant ses données plutôt qu'en cascade

**Installation / mises à jour**
- Installation fraîche = mise à jour depuis `0.0.0`. `APP_VERSION` : minor et patch de 0 à 9. Une release sans migration ne déclenche pas de mise à jour
- Table `version` vide mais données présentes → exception, jamais de réinstallation par-dessus
- Erreur de connexion autre que « base inexistante » → **503**, jamais l'assistant (sinon une panne MySQL permettrait de réécrire la config)
- En `update_required`, le site public reste en ligne ; la migration est lancée par un admin connecté (`POST /admin/update/migrate`)
- Mise à jour du code (`Core\Updater`) : `version.json` (généré par `release.mjs`, build dev compris, jamais édité à la main) sur `dist.yawasla.org` (`UPDATE_URL`), vérifié à chaque connexion d’un admin (résultat en cache). En un clic : téléchargement HTTPS, SHA-256, écrasement des fichiers (`index.php` en dernier, dossier public réel même renommé), puis `update_required` s'il y a des migrations. Volontairement simple : ni signature, ni sauvegarde, ni suppression des fichiers retirés d'une version à l'autre. Refusée hors distribution (sans `resources/app.html`)
- Cache de routes seulement en `ready` hors dev ; `var/{env}/cache/` vidé à chaque changement d'`APP_VERSION` (fichier `version`)
- Actions Slim en méthodes, jamais en closures (Slim les lie au conteneur)

**Front / distribution**
- `AppShell` injecte l'état dans la page : la sous-requête vers `/api/` doit être une requête neuve (sinon boucle infinie de routage)
- Build en `base: './'` : l'app doit marcher à la racine comme en sous-dossier
- Une seule icône PNG, remplacée plus tard par celle de l'organisme

**Authentification** — sujet sensible, on part du principe qu'il y aura des attaques
- Session PHP native (pas de JWT), fichiers dans `var/{env}/sessions` (0700) : le stockage par défaut est partageable en mutualisé et purgé par le cron Debian après 24 min. 24 h max, cookie `Secure` hors dev (proxy/CDN peut masquer le HTTPS)
- Admin = rôles `owner`/`admin` uniquement pour l'instant. `/auth/me` → `{ user: null }` pour un visiteur (pas de 401). `User` jamais en cache APCu (hash)
- `LoginThrottle` : réponse graduée par IP (`REMOTE_ADDR`, fichier, IP hachées, fenêtre 1 h) qui n'annonce jamais le blocage. Réponse d'échec strictement uniforme (code, corps et temps), quelle que soit la raison — ne pas introduire d'écart. Ne pas documenter les détails
- Écartés volontairement : CSP/HSTS, CSRF au-delà de `SameSite=Lax`, clé d'installation (fenêtre de quelques minutes)

**i18n**
- Lancement en fr, multilingue arabe compris prévu dès maintenant. Le back ne traduit rien (codes d'erreur traduits par le front), sauf les mails à venir
- Langue d'interface ≠ langue du contenu (annonces non traduites)
- RTL : propriétés CSS logiques uniquement ; police arabe à prévoir
- `<Trans>` : jamais de `dangerouslySetInnerHTML`

## Périmètre de la bêta

**Back-office** : connexion (mot de passe oublié, mail d'alerte), organisme, annonces, médias audio (indépendants des annonces), multi-utilisateur (architecture dès maintenant, module activé plus tard).

**Front-office** : newsroom publique (bandeau, infos organisme, annonces), liste des médias, inscription aux notifications push.

## TODO

### T1 2027

- [x] Socle technique
- [x] Installation de la base + organisme et utilisateur principal
- [x] Tests d'installation (débutant)
- [x] Authentification
- [x] Mise à jour automatique (dist.yawasla.org)
- [ ] Architecture des rôles/permissions
- [ ] Module organisme (back-office)
- [ ] Module utilisateur (back-office)
- [ ] Module annonces (back-office)
- [ ] Tableau de bord simplifié
- [ ] Newsroom publique (bandeau, infos organisme, annonces)
- [ ] Inscription aux notifications push
- [ ] Page mentions légales dynamique
- [x] Page statique sur yawasla.org

### T2 2027

- [ ] Intégration Brevo (interchangeable)
- [ ] Notifications push (Service Worker, permissions navigateur)
- [ ] Inscription aux annonces par mail

### T3 2027

- [ ] Gestion multi-utilisateurs complet
- [ ] Gestion du compte et paramètres d'affichage

### T4 2027

- [ ] Tableau de bord et graphiques
- [ ] Site vitrine sur yawasla.org
- [ ] Documentation technique d'installation

### T1 2028

- [ ] Gestion des modules
- [ ] Gestion des prières
- [ ] Gestion utilisateur complète
