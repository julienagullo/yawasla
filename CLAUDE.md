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
- **Base de données** : MySQL 8.0+ ou MariaDB 10.11+
- **Serveur web** : Apache ou Nginx avec réécriture d'URL
- **SSL** : obligatoire (requis pour les notifications push)
- **Notifications** : API Brevo (mail), Service Workers (push) — l'intégration doit rester interchangeable, éviter le couplage fort à Brevo
- **Hébergement cible** : VPS OVH sous Debian

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

### T4 2026

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

### T1 2027

- [ ] Intégration Brevo pour l'envoi de mail (pensé interchangeable)
- [ ] Mise en place des notifications push (Service Worker, SSL, gestion des permissions navigateur)
- [ ] Front-office : formulaire d'inscription aux annonces par mail

### T2 2027

- [ ] Intégration du module de création/gestion des utilisateurs (multi-user complet)
- [ ] Intégration de la gestion du compte et des paramètres d'affichage

### T3 2027

- [ ] Ajout du tableau de bord et des graphiques
- [ ] Site vitrine de présentation sur le domaine yawasla.org
- [ ] Documentation technique pour l'installation et la mise en place

### T4 2027

- [ ] Gestion des modules
- [ ] Gestion des prières
- [ ] Gestion utilisateur complet
