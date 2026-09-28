# Make Cars : déploiement gratuit (pilote)

Guide pas à pas pour mettre en ligne le pilote sur des hébergements gratuits :

| Élément | Hébergeur | Adresse obtenue |
|---|---|---|
| API Laravel (`backend/`) | **Render**, offre gratuite, région **Francfort** | `https://makecars-api.onrender.com` (exemple) |
| Site web (`frontend-web/`) | **Cloudflare Pages** | `https://makecars.pages.dev` (exemple) |
| Base de données et fichiers | **Supabase** (projet de production, `eu-central-1`, Francfort) | — |
| Emails | **Resend** (par API) | — |

Ce guide est à suivre **dans l'ordre**. Les textes entre guillemets sont ceux que vous devez voir à l'écran (les libellés exacts peuvent légèrement changer selon les mises à jour des sites).

> **À retenir avant de commencer**
> - **Aucun secret dans le dépôt** : mots de passe, clés API et `APP_KEY` se saisissent uniquement dans les tableaux de bord Render et Cloudflare.
> - **Aucune migration automatique** : la base de production se migre à la main (étape 3).
> - **Sauvegarde manuelle obligatoire** avant toute migration de la production et avant chaque mise en ligne : Supabase gratuit n'en fait aucune (section « Sauvegarde de la base » ci-dessous).
> - **Pilote seulement** : ne faites envoyer **aucune vraie pièce d'identité** tant que la case « Protection des données personnelles » de PASSATION.md (« Bloquant avant la mise en production ») n'est pas cochée. Utilisez des documents fictifs.

## Ce que l'hébergement gratuit impose, et ce qui a été fait contre la lenteur

| Contrainte de Render gratuit | Conséquence | Réponse dans le code |
|---|---|---|
| Mise en veille après 15 minutes sans visite ; réveil en 30 à 60 s | La première visite après une pause est très lente | Tâche externe qui appelle `/api/health` toutes les 10 minutes (étape 6). Cette route ne touche pas la base. |
| Projet Supabase mis en pause après 7 jours sans activité sur la base | L'API ne peut plus lire ni écrire ; il faut restaurer le projet à la main | Seconde tâche externe qui appelle `/api/health/db` (une requête `SELECT 1`) une fois par jour (étape 6). |
| 512 Mo de RAM, 0,1 CPU | Peu de calcul disponible | nginx + PHP-FPM, 4 processus fixes, OPcache sans vérification des fichiers, caches Laravel (`php artisan optimize`) au démarrage |
| Disque effacé à chaque redémarrage | Fichiers envoyés perdus | Tous les fichiers sur Supabase Storage (étape 2) |
| SMTP sortant bloqué | Aucun email par SMTP | Emails par l'API Resend (`MAIL_MAILER=resend`) |
| Pas de worker de file d'attente | — | Emails envoyés juste après la réponse, dans le même processus : l'utilisateur n'attend plus l'envoi |

Autres réglages anti-lenteur (tous dans `render.yaml`) :
- **Même région** (Francfort) pour Render et Supabase : chaque requête SQL fait un aller-retour de quelques millisecondes.
- **Aucune requête SQL** pour le cache, les limites de débit et les sessions (`CACHE_STORE=file`, `SESSION_DRIVER=array`), ni pour la date de dernière utilisation des jetons (`SANCTUM_TRACK_LAST_USED_AT=false`). Mesuré : la connexion passe de 19 à 7 requêtes SQL, `/api/health` de 8 à 0.
- **Requêtes lentes journalisées** : toute requête de plus d'1 seconde apparaît dans l'onglet « Logs » de Render (`Requête lente {"route":…,"duration_ms":…,"sql_queries":…}`).

---

## Sauvegarde de la base (manuelle, obligatoire)

Le projet Supabase de production est sur l'offre gratuite : la page « Database » → « Backups » affiche **« No backups »**. Supabase ne fait **aucune** sauvegarde : c'est à vous de la faire, depuis votre ordinateur.

> **Règle** : une sauvegarde de la production est **obligatoire**
> - avant **toute migration** de la base de production (étape 3, et toute migration future) ;
> - avant **chaque mise en ligne** (chaque `git push` sur `main` qui redéploie Render, étape 4.6).
>
> Pas de sauvegarde du jour → pas de migration, pas de mise en ligne.

### Faire une sauvegarde

Depuis `backend/` :
```
bin/backup-db.sh prod
```
- Les paramètres de connexion sont lus dans `backend/.env.production`, **quel que soit l'environnement actif** ; le mot de passe n'est jamais affiché (il est transmis à `pg_dump` par une variable d'environnement).
- Format compressé de `pg_dump` (`.dump`), fichier daté, par exemple `makecars-production-2026-09-27_174047.dump`.
- Contenu : toutes les tables de l'application (schéma `public`, table `migrations` comprise). **Pas** les fichiers de Supabase Storage (justificatifs, photos) : ils ne sont pas dans la base.
- Vous devez voir à la fin :
  ```
  Base      : production (.env.production)
  Serveur   : aws-0-eu-central-1.pooler.supabase.com:5432, utilisateur postgres.aowpgpeabffpqneflzas
  Fichier   : /home/<vous>/makecars-sauvegardes/makecars-production-2026-09-27_174047.dump
  Outils    : conteneur postgres:17 via podman (machine : 16, serveur 17)

  Sauvegarde terminée : /home/<vous>/makecars-sauvegardes/makecars-production-…dump (160K, 42 tables)
  ```
  (Mesuré sur la base de développement le 2026-09-27 : 156 Ko, 42 tables.)
- `bin/backup-db.sh dev` sauvegarde de la même façon la base de développement.

### Où ranger les fichiers

- Par défaut dans `~/makecars-sauvegardes/` (dossier créé par le script, lisible par vous seul ; fichiers en droits `600`). Autre dossier : `BACKUP_DIR=/chemin bin/backup-db.sh prod`.
- **Jamais dans le dépôt Git** (le `.gitignore` refuse de toute façon les fichiers `*.dump`) : une sauvegarde contient des données personnelles (noms, emails, téléphones, IFU, NPI, mots de passe hachés).
- Garder **une deuxième copie hors de l'ordinateur** (clé USB ou disque externe rangé à part, ou stockage chiffré), pour survivre à la perte de l'ordinateur.
- Conserver au minimum les 7 dernières sauvegardes et celle qui précède chaque migration ; supprimer les plus anciennes (durée à aligner sur la future politique de protection des données, PASSATION.md, liste bloquante).

### Tester une sauvegarde (base de DÉVELOPPEMENT uniquement)

Une sauvegarde jamais relue n'est pas une sauvegarde. Pour vérifier qu'elle est utilisable, la restaurer dans la base de **développement** :
```
bin/restore-db.sh ~/makecars-sauvegardes/makecars-production-2026-09-27_174047.dump
```
- La cible est **toujours** la base de développement (`.env.development`), jamais la production ; le script refuse si les deux fichiers `.env` désignent le même projet, et demande de taper `RESTAURER`.
- Il remplace toutes les tables de l'application de la base de développement par celles de la sauvegarde, **en une seule transaction** : en cas d'erreur, rien n'est modifié.
- Toutes les tables, vues et séquences du schéma `public` de la base de développement sont d'abord supprimées (avec `CASCADE`), **y compris celles qui n'existent pas dans la sauvegarde** (migrations plus récentes) ; le schéma `public` lui-même n'est jamais supprimé (il porte les droits accordés par Supabase) et aucun autre schéma n'est touché.
- Fonctionnement (pour information) : `pg_restore --no-owner --no-privileges --use-list=<liste> --file=<script SQL> <fichier>` (liste = tout sauf le schéma `public` et les fonctions de Supabase), puis `psql --single-transaction --set=ON_ERROR_STOP=1` avec le vidage suivi de ce script. Les fichiers temporaires sont écrits dans le dossier des sauvegardes (droits `600`) et supprimés à la fin.
- Pourquoi pas `pg_restore --clean` (utilisé jusqu'au 2026-09-28) : il ne supprime que les objets présents dans la sauvegarde. Une base plus récente que la sauvegarde (cas d'une restauration après une migration interrompue) le fait échouer (`cannot drop constraint users_pkey … because other objects depend on it`), et il supprimait puis recréait le schéma `public`, en perdant ses droits Supabase. Détail : PASSATION.md, « Sauvegardes ».
- Vérifier ensuite : `bin/switch-env.sh status` (doit afficher `.env.development`), `php artisan migrate:status`, puis connexion à l'application en local.
- Après le test, la base de développement contient les données de production : **relancer les seeders** si vous voulez retrouver les comptes de test (PASSATION.md §5), et ne pas partager cette base.
- **Aucune restauration n'a été validée avant le 2026-09-28** : l'essai du 2026-09-27 (base de développement restaurée dans elle-même) ne signalait rien mais utilisait l'ancienne méthode, et la restauration de la sauvegarde de production du 2026-09-28 a échoué (annulée sans dommage). Le script corrigé a été validé sur un PostgreSQL 17 local jetable ; la restauration réelle dans la base de développement Supabase reste à faire.
- Après une restauration d'une sauvegarde plus ancienne, `php artisan migrate` (environnement dev) réapplique les migrations plus récentes.
- **Restaurer la production** n'est volontairement pas automatisé : c'est une décision exceptionnelle (perte de données), à prendre explicitement, après une nouvelle sauvegarde de l'état actuel, avec la même commande `pg_restore` et la connexion de production.

### Si `pg_dump` manque ou n'a pas la bonne version

`pg_dump` et `pg_restore` doivent avoir une version majeure **au moins égale** à celle du serveur. Serveurs Supabase (production et développement) : **PostgreSQL 17.6** (relevé le 2026-09-27). Sur cet ordinateur (Fedora 40), `pg_dump` est en version **16.8** : il refuserait (« server version mismatch »).

- **Rien à installer dans ce cas** : les scripts le détectent et utilisent automatiquement l'image officielle `postgres:17` avec **podman** (installé par défaut sur Fedora). Le premier lancement télécharge l'image (environ 150 Mo).
- Vérifier la version installée : `pg_dump --version`.
- Installer un vrai `pg_dump` 17 sur l'ordinateur (facultatif) :
  - **Fedora 40** : ni Fedora 40 ni le dépôt officiel PostgreSQL (qui ne prend plus en charge que Fedora 42 et suivantes) ne proposent PostgreSQL 17. Garder la solution podman, ou mettre Fedora à jour.
  - **Fedora 43 ou plus récente**, dépôt officiel PostgreSQL :
    ```
    sudo dnf install https://download.postgresql.org/pub/repos/yum/reporpms/F-43-x86_64/pgdg-fedora-repo-latest.noarch.rpm
    sudo dnf install postgresql17
    export PATH=/usr/pgsql-17/bin:$PATH    # à ajouter dans ~/.bashrc
    pg_dump --version                     # doit afficher 17.x
    ```
    (Remplacer `F-43` par votre version de Fedora.)
- Si podman n'est pas installé : `sudo dnf install podman`.
- Quand Supabase passera à une version plus récente (18…), les scripts prendront automatiquement l'image correspondante.

## Étape 1 : créer les comptes

1. **Render** : https://render.com → « Get Started » → connexion avec **GitHub** (le plus simple : Render lira directement le dépôt). Aucune carte bancaire n'est demandée pour l'offre gratuite.
2. **Cloudflare** : https://dash.cloudflare.com/sign-up → email et mot de passe, puis confirmer l'email.
3. **Resend** (emails) : https://resend.com → « Sign up ».
   - **Important** : sans domaine vérifié, Resend n'envoie qu'à **votre propre adresse** (celle du compte), depuis `onboarding@resend.dev`. Pour que de vrais professionnels reçoivent leur code d'inscription, il faut **un nom de domaine** (ex. `makecars.bj` ou un domaine acheté quelques euros par an) et le vérifier dans Resend : « Domains » → « Add Domain » → ajouter chez le gestionnaire du domaine les enregistrements DNS affichés (SPF, DKIM, et de préférence DMARC). Attendre « Verified ».
   - Créer une clé : « API Keys » → « Create API Key », permission « Sending access ». Vous voyez une valeur `re_…` **une seule fois** : copiez-la pour l'étape 4.
   - Offre gratuite : 3 000 emails par mois, 100 par jour — suffisant pour un pilote.

## Étape 2 : Supabase (projet de production)

1. Ouvrir https://supabase.com/dashboard et choisir le projet de **production** (identifiant `aowpgpeabffpqneflzas`, voir CLAUDE.md §4).
2. **Vérifier qu'il est actif** : en haut de la page, aucun bandeau « Project is paused ». S'il est en pause : « Restore project ».
   - Sur l'offre gratuite de Supabase, un projet sans aucune activité pendant 7 jours sur la base est mis en pause. La seconde tâche de l'étape 6 (`/api/health/db`, une fois par jour) l'en empêche ; la première (`/api/health`, toutes les 10 minutes) ne touche pas la base.
3. **Vérifier la région** : « Project Settings » → « General » : « Region » doit indiquer **Central EU (Frankfurt)** / `eu-central-1`.
4. **Créer les deux buckets** : menu « Storage » → « New bucket ».
   - `documents-prives` : **« Public bucket » décoché**. Contiendra justificatifs (registre de commerce, CIP), images du chat, photos des réclamations, pièces jointes des demandes de réactivation, PDF des devis et factures. Aucune adresse publique : ces fichiers ne sont lisibles qu'à travers l'API, après vérification des droits.
   - `medias-publics` : **« Public bucket » coché**. Contiendra les photos des profils, produits et services.
   - Vous devez voir les deux buckets dans la liste, le second avec l'étiquette « Public ».
   - Laisser vides les limites de taille et de type de fichier du bucket (l'API les vérifie déjà ; si vous en mettez une, au moins 12 Mo).
5. **Clés d'accès S3** : « Project Settings » → « Storage » (ou « Storage » → « Settings ») → section « S3 Connection ».
   - Vérifier que « Enable connection via S3 protocol » est activé.
   - Noter l'**Endpoint** (`https://<projet>.storage.supabase.co/storage/v1/s3`) et la **Region** (`eu-central-1`).
   - « S3 Access Keys » → « New access key » → description « render » → vous voyez un **Access key ID** et un **Secret access key** (affiché une seule fois) : copiez-les pour l'étape 4.
   - Ces clés donnent un accès complet à tous les buckets du projet : ne les mettez que dans Render.
6. **Adresse publique du bucket public** : `https://<projet>.supabase.co/storage/v1/object/public/medias-publics` (avec `<projet>` = `aowpgpeabffpqneflzas`).
7. **Mot de passe de la base** : « Project Settings » → « Database » → « Connection string » → onglet « Session pooler » : noter l'utilisateur `postgres.aowpgpeabffpqneflzas` et le mot de passe (réinitialisable via « Reset database password » si vous ne l'avez plus — attention, cela coupe la connexion du `.env.production` local, à mettre à jour à la main).

## Étape 3 : base de production, migrations en attente

Le code du pilote a besoin de **toutes** les migrations appliquées sur la base de production (connexion, inscription, dossier, réactivation, mot de passe oublié en dépendent).

1. **Sauvegarde d'abord (obligatoire)** : `bin/backup-db.sh prod` depuis `backend/` (section « Sauvegarde de la base »). Vous devez obtenir « Sauvegarde terminée : …dump ». Idéalement, la tester avec `bin/restore-db.sh` (base de développement) avant de migrer.
2. Appliquer **la procédure de PASSATION.md**, case « Toutes les migrations en attente appliquées sur la base de production » : `switch-env.sh prod`, `switch-env.sh status`, `php artisan migrate:status` (noter la liste « Pending »), `php artisan migrate`, nouveau `migrate:status` (plus aucune « Pending »), puis **retour sur `dev`** (`switch-env.sh dev`).
3. Aucune migration ne sera jamais lancée par Render.

## Étape 4 : Render, le service de l'API

1. Générer une clé d'application, sur votre poste, depuis `backend/` :
   ```
   php artisan key:generate --show
   ```
   Vous voyez une ligne `base64:…` : c'est `APP_KEY`. Ne la changez plus ensuite : elle signe notamment les liens envoyés par email (décision de devis, activation de compte), qui deviendraient tous invalides.
2. Render → « New + » → **« Blueprint »** → choisir le dépôt GitHub `make_cars` → Render lit `render.yaml` et affiche « makecars-api — Web Service — Docker — Frankfurt — Free ».
3. Render demande les variables marquées `sync: false`. Liste complète des variables du service :

   | Variable | Valeur |
   |---|---|
   | `APP_KEY` | la ligne `base64:…` de l'étape 4.1 |
   | `APP_URL` | l'adresse du service : `https://makecars-api.onrender.com` (visible après création ; si le nom est pris, Render ajoute un suffixe — reprendre l'adresse exacte) |
   | `FRONTEND_URL` | l'adresse Cloudflare Pages de l'étape 5 (`https://makecars.pages.dev`), sans `/` final. Si vous ne la connaissez pas encore, mettez une valeur provisoire et corrigez-la après l'étape 5. |
   | `CORS_ALLOWED_ORIGINS` | la même valeur que `FRONTEND_URL` |
   | `DB_USERNAME` | `postgres.aowpgpeabffpqneflzas` |
   | `DB_PASSWORD` | mot de passe de la base (étape 2.7) |
   | `RESEND_API_KEY` | `re_…` (étape 1.3) |
   | `MAIL_FROM_ADDRESS` | ex. `no-reply@<votre domaine vérifié>` (ou `onboarding@resend.dev` pour un essai vers votre seule adresse) |
   | `SUPABASE_STORAGE_KEY` | Access key ID (étape 2.5) |
   | `SUPABASE_STORAGE_SECRET` | Secret access key (étape 2.5) |
   | `SUPABASE_STORAGE_ENDPOINT` | `https://aowpgpeabffpqneflzas.storage.supabase.co/storage/v1/s3` (celui affiché à l'étape 2.5) |
   | `SUPABASE_PUBLIC_URL` | `https://aowpgpeabffpqneflzas.supabase.co/storage/v1/object/public/medias-publics` |

   Déjà renseignées par `render.yaml` (ne rien faire) : `APP_NAME=Make Cars`, `APP_ENV=production`, `APP_DEBUG=false`, `APP_LOCALE=fr`, `TRUSTED_PROXIES=*` (le conteneur n'est joignable que par le proxy de Render), `LOG_CHANNEL=stderr`, `LOG_LEVEL=warning`, `SLOW_REQUEST_THRESHOLD_MS=1000`, `DB_CONNECTION=pgsql`, `DB_HOST=aws-0-eu-central-1.pooler.supabase.com`, `DB_PORT=5432`, `DB_DATABASE=postgres`, `DB_SSLMODE=require`, `CACHE_STORE=file`, `SESSION_DRIVER=array`, `QUEUE_CONNECTION=sync`, `SANCTUM_TRACK_LAST_USED_AT=false`, `MAIL_MAILER=resend`, `MAIL_FROM_NAME=Make Cars`, `KYC_DOCUMENTS_DISK=supabase`, `PRIVATE_MEDIA_DISK=supabase`, `PUBLIC_MEDIA_DISK=supabase_public`, `SUPABASE_STORAGE_REGION=eu-central-1`, `SUPABASE_STORAGE_BUCKET=documents-prives`, `SUPABASE_PUBLIC_BUCKET=medias-publics`.

   Non utilisées par le pilote web (à ajouter plus tard) : `GOOGLE_CLIENT_ID`, `FCM_SERVER_KEY`.

4. « Apply ». La construction de l'image prend 5 à 10 minutes la première fois. Dans « Logs », vous devez voir à la fin :
   ```
   INFO  Caching framework bootstrap, configuration, and metadata.
   config ... DONE
   routes ... DONE
   NOTICE: ready to handle connections
   ==> Your service is live 🎉
   ```
5. Vérifier : ouvrir `https://makecars-api.onrender.com/api/health` dans le navigateur. Vous devez voir :
   ```
   {"status":"ok","app":"Make Cars"}
   ```
6. Chaque `git push` sur `main` redéploie automatiquement le service (`autoDeploy`). Une variable modifiée dans « Environment » redéploie aussi. **Avant chaque mise en ligne** (push sur `main`), faire une sauvegarde de la production : `bin/backup-db.sh prod`.

## Étape 5 : Cloudflare Pages, le site web

1. Cloudflare → « Workers & Pages » → « Create » → onglet **« Pages »** → « Connect to Git » → autoriser GitHub → choisir `make_cars`.
2. Réglages de build :

   | Champ | Valeur |
   |---|---|
   | Production branch | `main` |
   | Framework preset | `Vue` (ou « None ») |
   | Build command | `npm run build` |
   | Build output directory | `dist` |
   | Root directory (advanced) | `frontend-web` |
   | Environment variables | `VITE_API_BASE_URL` = `https://makecars-api.onrender.com/api` (adresse de l'étape 4, **avec** `/api`, sans `/` final) |

   Node 22 est choisi par le fichier `frontend-web/.nvmrc`.
3. « Save and Deploy ». Vous devez voir « Success! Your project is deployed » et l'adresse `https://<projet>.pages.dev`.
4. **`VITE_API_BASE_URL` est lue au moment du build** : si elle change, relancer un déploiement (« Deployments » → « Retry deployment »).
5. Revenir dans Render si besoin : `FRONTEND_URL` et `CORS_ALLOWED_ORIGINS` = l'adresse exacte `https://<projet>.pages.dev`.
6. Déjà prévu dans le dépôt : `frontend-web/public/_headers` (fichiers `/assets/*` gardés un an, `index.html` jamais mis en cache) ; les adresses comme `/garage/profile` sont servies par `index.html` grâce au mode « application à page unique » de Pages (pas de fichier `404.html`, ne pas en ajouter).

## Étape 6 : garder le serveur éveillé

1. https://cron-job.org → créer un compte gratuit → « Create cronjob ».
2. Title : `Make Cars - réveil API` ; URL : `https://makecars-api.onrender.com/api/health` ; Schedule : **toutes les 10 minutes** ; « Create ».
3. Après quelques exécutions, « History » doit montrer des réponses `200 OK` en moins d'une seconde.
4. **Seconde tâche, pour la base** : « Create cronjob » à nouveau. Title : `Make Cars - activité base` ; URL : `https://makecars-api.onrender.com/api/health/db` ; Schedule : **une fois par jour** (par exemple chaque jour à 6 h) ; « Create ».
   - **Pourquoi deux routes distinctes** : les deux hébergeurs ne s'endorment pas pour la même raison. Render met le serveur en veille après **15 minutes** sans visite : il faut une visite fréquente, et `/api/health` ne touche volontairement pas la base (aucune requête SQL toutes les 10 minutes). Supabase met le projet en pause après **7 jours** sans activité **sur la base** : une visite de `/api/health` ne compte pas, il faut une vraie requête SQL, et une par jour suffit. `/api/health/db` exécute cette requête minimale (`SELECT 1`, interrompue au bout de 5 secondes).
   - Réponse attendue : `{"status":"ok","db":"ok"}`. Un `503` (`{"status":"error","db":"unavailable"}`) signifie que la base ne répond pas : vérifier dans Supabase qu'aucun bandeau « Project is paused » n'est affiché (étape 2) et les journaux Render.
   - Dans les réglages de la tâche, activer la notification par email en cas d'échec : c'est la seule alerte si la base tombe.
5. Bon à savoir : l'offre gratuite de Render donne 750 heures par mois, ce qui couvre un seul service éveillé en permanence. N'en créez pas un second éveillé de la même façon.

## Étape 7 : vérifications finales

Cochez au fur et à mesure.

- [ ] **HTTPS** : les deux adresses s'ouvrent en `https://` avec le cadenas ; `http://` redirige vers `https://`.
- [ ] **Mode production** : `https://makecars-api.onrender.com/api/n-existe-pas` renvoie un JSON court (`{"message":"…"}`), sans trace, nom de fichier ni requête SQL.
- [ ] **Connexion** avec un compte existant de la base de production. Si la connexion échoue avec une erreur réseau dans le navigateur (console : « CORS »), vérifier que `CORS_ALLOWED_ORIGINS` est exactement l'adresse du site (sans `/` final).
- [ ] **Actualisation** : sur `/garage/profile` (ou toute page interne), appuyer sur F5 : la page se recharge, pas d'erreur 404.
- [ ] **Inscription avec un vrai code reçu par email** : parcours d'inscription d'un garagiste avec une adresse réelle ; le code doit arriver en **boîte de réception** (pas en indésirables) en moins d'une minute.
- [ ] **Envoi d'un justificatif** : sur « Mon profil », envoyer un document **fictif** (registre de commerce), puis le consulter. Dans Supabase → Storage → `documents-prives`, un dossier `registration-documents/…` doit apparaître.
- [ ] **Photo publique** : ajouter une photo au profil ; elle s'affiche, et son adresse commence par `https://aowpgpeabffpqneflzas.supabase.co/storage/v1/object/public/medias-publics/`.
- [ ] **Redémarrage** : Render → le service → « Manual Deploy » → « Restart service ». Une fois « live » à nouveau, le justificatif et la photo sont **toujours consultables**.
- [ ] **IP réelle des visiteurs** (limites de débit) : procédure de PASSATION.md, case « Proxy de confiance » (échecs de connexion depuis deux réseaux différents : le blocage de l'un ne touche pas l'autre).
- [ ] **Journal** : Render → « Logs ». Des lignes `Requête lente` peuvent apparaître : elles indiquent la route, la durée et le nombre de requêtes SQL.

## Étape 8 : rappel

Cet hébergement sert à un **pilote** et à des démonstrations. Tant que la case « Protection des données personnelles » de la liste bloquante (PASSATION.md) n'est pas cochée, **ne faites envoyer aucune vraie pièce d'identité** (CIP) ni vrai document officiel : utilisez des documents fictifs. Le passage à un hébergement payant se fera avec le même code : seules les variables d'environnement changent.

## En cas de problème

| Symptôme | Piste |
|---|---|
| Première page très lente (30 à 60 s) | Le service dormait : vérifier la tâche cron-job.org (étape 6). |
| « Server Error » à la connexion | Render → Logs : souvent `DB_PASSWORD`/`DB_USERNAME` erronés, projet Supabase en pause, ou migrations manquantes (étape 3). |
| Code d'inscription jamais reçu | Resend → « Emails » : l'envoi y apparaît-il ? Sans domaine vérifié, seule l'adresse du compte Resend reçoit. Render → Logs : une erreur d'envoi y est écrite. |
| Envoi de fichier en échec | Render → Logs (erreurs de stockage journalisées) : clés S3, endpoint ou nom de bucket. |
| Photos qui ne s'affichent pas | `SUPABASE_PUBLIC_URL` et case « Public bucket » du bucket `medias-publics`. |
| Tout le monde bloqué par « Trop de tentatives » | `TRUSTED_PROXIES` (étape 7, IP réelle). |
