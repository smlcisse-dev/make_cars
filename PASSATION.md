# Make Cars : document de passation (état au 2026-09-24)

Remplace la version du 2026-09-20 (commit `473b70c`). Chaque point de la section « Vérifié » a été contrôlé le 2026-09-23, puis mis à jour le 2026-09-24 (lot v0.29, fiche admin d'examen, puis liens email v0.30), par une commande ou une lecture de code. Les points qui n'ont pas pu l'être sont signalés comme tels.

**Vérifié dans le code / par commande**
- **Git** : `main` est synchronisée avec `origin/main` (0 commit d'avance, 0 de retard après `git fetch`). Seul élément non suivi : `.claude/`. **112 commits** au total après le lot v0.30 (liens email) du 2026-09-24, ce commit de documentation compris (109 après la fiche admin d'examen, 106 après le lot v0.29, 98 après les pièces jointes de la demande de réactivation, 69 à la rédaction initiale de ce document).
- **Dernier commit de code** : `82b733f` feat(frontend): pages de confirmation des liens email (v0.30), suivi du commit de documentation qui met ce fichier à jour.
- **Tests backend** (`php artisan test`, SQLite en mémoire via `phpunit.xml`, donc sans toucher Supabase) : **596 tests, 596 réussis, 1995 assertions** après le lot v0.30 (586 tests / 1937 assertions après la fiche admin d'examen, 1932 après le lot v0.29, 571 après les pièces jointes de la demande de réactivation, 558 après v0.27/v0.28, 523 après le backend v0.26).
- **Environnement actif** (`backend/bin/switch-env.sh status`) : `.env.development`, `DB_USERNAME=postgres.ppfflfwzqmckciqikhzn` (projet Supabase de développement, pooler `eu-central-1`).
- **Routes** (`php artisan route:list`) : **195 routes** au total (183 après v0.26 ; +3 par espace pro et +1 admin pour v0.27/v0.28 ; +1 par espace pro et +1 admin pour les pièces jointes de la demande de réactivation ; +2 pour le mot de passe oublié, v0.29 ; v0.30 : les 2 routes `GET` de décision de devis deviennent 1 `GET` + 1 `POST`, total inchangé).

| Espace | Routes | Détail |
|---|---|---|
| `api/garage/*` | 57 | profil 13 (dont dossier v0.26 : `profile/legal`, `profile/legal/document` ×2, `profile/submit` ; CIP v0.27 : `profile/legal/identity-document` ×2 ; v0.28 : `profile/reactivation-request`, `profile/reactivation-requests/{reactivationRequest}/attachments/{attachment}`), services 5, produits 5, RDV 7, devis 10, commandes 4, chat 4, réclamations 4, notifications 2, avis 1, client express 1, jeton FCM 1 |
| `api/market-space/*` | 34 | profil 13 (mêmes routes de dossier v0.26, v0.27 et v0.28), produits 5, commandes 4, chat 4, réclamations 4, notifications 2, avis 1, jeton FCM 1 |
| `api/admin/*` | 41 | inscriptions 9 (dont `reactivation-request/refuse` et `reactivation-requests/{reactivationRequest}/attachments/{attachment}`, v0.28), litiges 7, services 4, produits 4, avis 3, statistiques 1, supervision en lecture 12 (garages, boutiques, RDV, devis, commandes, conversations : 2 chacun) |
| `api/mobile/*` | 39 | automobiliste (voir §3) |
| `api/auth/*` | 11 | login, login Google, logout, me, inscription automobiliste, inscription pro en 3 routes (`register/professionnel`, `{uuid}/verify`, `{uuid}/resend`), `express-claim`, mot de passe oublié en 2 routes (`password/forgot`, `password/reset`, v0.29) |
| Publiques hors auth | 8 | `locations/*` 3, décision de devis par email 2 (`GET` lecture seule + `POST` décision, même URL signée, v0.30), réclamation de compte express 2, `health` 1 |
| Hors `api/` | 5 | `sanctum/csrf-cookie`, `storage/{path}` ×2, `up`, `_boost/browser-logs` |

**Niveau de vérification des écrans**

Aucun test automatisé côté frontend : `package.json` n'a pas de script de test. On distingue deux niveaux.

- **Testé manuellement en navigateur, écran par écran** : le **dashboard Garagiste au complet**, c'est-à-dire Services, Produits, Rendez-vous, Devis/Factures, Chat, Commandes, Avis, Réclamations et Notifications (plus Mon profil).
  - *Source : déclaration de l'utilisateur. Rien dans le dépôt ne permet de le constater.*
- **« Mon profil » v0.26 (espaces Garagiste et Market Space)** : testé manuellement en navigateur — remplissage et soumission, verrouillage pendant l'examen, approbation puis « Actualiser », bandeau de suspension, dossier refusé corrigeable.
  - *Source : déclaration de l'utilisateur, 2026-09-23.*
- **Lot du 2026-09-23 (corrections, CIP, demande de réactivation)** : testé manuellement en navigateur —
  - corrections issues des tests de « Mon profil » : champs grisés, badge « Profil à compléter », suppression des références techniques à l'écran, rubrique active du menu ;
  - CIP (v0.27) côté professionnel et côté admin ;
  - demande de réactivation (v0.28) complète : demande, refus avec motif, nouvelle demande, réactivation, historique.
  - *Source : déclaration de l'utilisateur, 2026-09-23.* Les **pièces jointes** de la demande de réactivation, ajoutées ensuite (§8), sont **considérées comme validées par l'utilisateur, sans test en navigateur (2026-09-23)**.
- **Parcours d'inscription garagiste (v0.26)** : testé manuellement en navigateur — choix du type de compte, formulaire, code faux (essais restants affichés), blocage après 5 essais et ses deux boutons, « Modifier l'email » (champs conservés), renvoi du code avec décompte, confirmation, lien de l'email « compte créé », connexion, « Mon profil », soumission du dossier, email déjà utilisé refusé.
  - *Source : déclaration de l'utilisateur, 2026-09-24.* Les corrections qui en sont issues (§8, lot du 2026-09-24) ne sont, elles, pas encore revues en navigateur.
- **Parcours d'inscription boutique (Market Space)** : vérifié par un **test automatique backend** de bout en bout (`tests/Feature/MarketSpace/MarketSpaceSignupJourneyTest.php` : inscription, code, compte et profil vides, lien de l'email vers `/market-space/profile`, connexion, profil, informations légales, registre de commerce, CIP, soumission, approbation admin, routes métier ouvertes) et par **relecture du code frontend** (titre « Créer mon compte boutique », `account_type=market_space` envoyé, « Modifier l'email » qui revient à `/inscription/boutique`, redirection après connexion vers `/market-space/profile`, libellé « Nom de la boutique »). **Pas testé en navigateur** ; affichage sur téléphone non testé.
- **Mot de passe oublié (v0.29)** : construit, couvert par les tests backend (`tests/Feature/Auth/PasswordResetTest.php`), considéré comme validé par l'utilisateur, sans test en navigateur (2026-09-24).
- **Fiche admin d'examen d'un dossier (2026-09-24)** : construite, **pas encore testée en navigateur**.
- **Pages des liens email (v0.30, 2026-09-24)** : `/devis/decision` et `/compte/activer`, construites, couvertes côté backend par les tests (`QuoteEmailDecisionTest`, `ExpressClaimTest`), **pas encore testées en navigateur** (ni sur téléphone).
- **Construit et vérifié par relecture de code, jamais testé en navigateur** :
  - le **dashboard Market Space**. Depuis le 2026-09-23, ses écrans (hors tableau de bord) partagent le code du Garagiste (`views/shared/`, prop `space`) : ce partage de code ne vaut pas test, l'espace reste à vérifier en navigateur ;
  - le **dashboard Admin** (6 écrans, antérieurs à cette période de travail).

---

## 1. Frontend web : écrans existants

Toutes les routes sont dans `frontend-web/src/router/index.ts`, avec garde de navigation par rôle et garde « profil incomplet » (v0.20).

**Contrôle des écrans orphelins : aucun.**
- Chaque fichier de `src/views/` est déclaré dans le routeur.
- Chaque écran de liste figure dans le menu de son layout.
- Chaque écran de détail ou de création est atteint depuis sa liste (`router.push`), vérifié par `grep`.
- `views/garage/QuoteLineEditor.vue` n'est pas une page : c'est un composant importé par `QuoteCreateView` et `QuoteDetailView`.

**Commun**
- `/login` : connexion email/mot de passe, avec lien vers l'inscription professionnelle et lien « Mot de passe oublié ? » (v0.29).
- `/mot-de-passe-oublie` (v0.29) : email, puis code + nouveau mot de passe sur la même page ; retour sur `/login?reset=1` avec message de confirmation. Considéré comme validé par l'utilisateur, sans test en navigateur (2026-09-24).
- `/inscription` et ses étapes (v0.26) : choix du type de compte, formulaire court, saisie du code, confirmation (parcours garagiste testé en navigateur le 2026-09-24, parcours boutique vérifié par test automatique backend et relecture).
- `/devis/decision` et `/compte/activer` (v0.30) : pages publiques ouvertes depuis un lien reçu par email (décision de devis par un client express, activation d'un compte express), sans `guestOnly`, dans `views/public/`. Pas encore testées en navigateur.
- `/403` et page 404.
- Composants partagés (`src/shared/components`) : `AppButton`, `AppTable`, `AppPagination`, `BaseModal`, `ReasonPromptModal` (libellé du champ et couleur du bouton paramétrables depuis v0.28), `ReactivationRequestModal` (message + pièces jointes de la demande de réactivation, v0.28), `ReactivationAttachmentList` (pièces jointes d'une demande sur la fiche admin, ouverture en blob authentifié), `StatusBadge`, `LocationSelect`, `LegalDocumentField` (justificatif privé du dossier, v0.27), `SuspensionBanner` (bandeau de suspension et demande de réactivation, v0.28), `ConfirmDialog` (fenêtre de confirmation unique, montée dans `App.vue`, pilotée par `confirmAction()` de `utils/confirmDialog.ts` — remplace tous les `window.confirm`, 2026-09-24), `CodeInput` (champ du code à 6 chiffres, commun à l'inscription et au mot de passe oublié).
- Layouts : `AdminLayout`, `GarageLayout`, `MarketSpaceLayout`, tous construits sur `DashboardShell`.

**Espace Admin (`/admin`)** : construit, relu, jamais testé en navigateur

| Menu | Routes | Périmètre |
|---|---|---|
| Tableau de bord | `/admin` | Statistiques agrégées (`GET /admin/statistics`), en cartes et tableaux, sans graphiques |
| Inscriptions | `/admin/registrations`, `/:id` | Liste : filtres par statut dont « Profil à compléter » (2026-09-24), colonnes date de soumission (`submitted_at`) et date de création du compte. Fiche d'examen (2026-09-24, pas encore testée en navigateur) : en-tête (soumission, création), représentant, structure avec « Voir sur la carte » (lien OpenStreetMap), horaires, photos, informations légales (RCCM, IFU, NPI + « Consulter le CIP »), justificatifs, historique des décisions. Fiche KYC (documents dont la CIP, v0.27). Approbation, rejet, suspension et réactivation (motif obligatoire). v0.28 : filtre et badge « Réactivation demandée », bloc de la demande en attente, « Refuser la demande » (motif obligatoire), historique des demandes — testé en navigateur (déclaration utilisateur, 2026-09-23) ; pièces jointes de la demande (bouton « Consulter », dans la demande en attente et dans l'historique) — considérées comme validées par l'utilisateur, sans test en navigateur (2026-09-23) |
| Services | `/admin/services`, `/:id` | Validation des services de garage |
| Produits | `/admin/products`, `/:id` | Validation des produits (mini-boutique et Market Space). Rendu résistant à un vendeur orphelin depuis `7095e8d` |
| Avis | `/admin/avis`, `/:id` | Masquage avec motif |
| Litiges | `/admin/litiges`, `/:id` | Instruction, décision (fondée + suspension ou avertissement, ou rejet), clôture |

**Espace Garagiste (`/garage`)** : testé manuellement en navigateur (déclaration utilisateur)

Ordre du menu dans `GarageLayout.vue`, identique au routeur :

| Menu | Routes | Vue(s) |
|---|---|---|
| Tableau de bord | `/garage` | `DashboardView.vue`. Simple message de bienvenue qui renvoie au menu, sans chiffres ni raccourcis |
| Services | `/garage/services` | `ServicesView.vue` : CRUD, disponibilité, image obligatoire à la création (v0.23) |
| Produits | `/garage/products` | `shared/ProductsView.vue` : CRUD, correction de stock, seuil bas, image obligatoire (v0.23) |
| Rendez-vous | `/garage/appointments`, `/:id` | `AppointmentsView`, `AppointmentDetailView` : confirmer, refuser (motif obligatoire, v0.24), reprogrammer |
| Devis | `/garage/quotes`, `/new`, `/:id` | `QuotesView`, `QuoteCreateView` (avec ou sans RDV, client express), `QuoteDetailView` (versions, envoi, démarrage, paiement manuel, abandon, PDF) ; lignes diagnostic / service / pièce / **prestation libre** |
| Commandes | `/garage/orders`, `/:id` | `shared/OrdersView`, `shared/OrderDetailView` : paiement manuel, PDF |
| Messages | `/garage/conversations` | `shared/ConversationsView.vue` : lien vers le devis depuis un message système |
| Avis | `/garage/reviews` | `shared/ReviewsView.vue` : lecture seule |
| Réclamations | `/garage/disputes`, `/:id` | `shared/DisputesView` (filtre par statut), `shared/DisputeDetailView` (réponse) |
| Notifications | `/garage/notifications` | `shared/NotificationsView.vue` |
| Mon profil | `/garage/profile` | `shared/ProfessionalProfileView.vue` (`space="garage"`). Depuis le 2026-09-23, porte aussi le dossier d'inscription v0.26 : bandeau d'état (`profile_incomplete` / `pending` avec « Actualiser » / `rejected` avec motif / `approved`), profil en lecture seule pendant l'examen, section « Informations légales » (RCCM, IFU, NPI, document du registre de commerce, et depuis v0.27 le Certificat d'Identification Personnelle), section « Soumettre mon dossier ». **Testé en navigateur (déclaration utilisateur, 2026-09-23)**, hors bloc CIP (v0.27, pas encore testé) |

**Espace Market Space (`/market-space`)** : construit, relu, jamais testé en navigateur

Ordre du menu dans `MarketSpaceLayout.vue`. Hors tableau de bord, chaque écran est le même fichier que côté Garagiste (`views/shared/`, prop `space="market-space"`) :

| Menu | Routes | Vue(s) |
|---|---|---|
| Tableau de bord | `/market-space` | `DashboardView.vue`. Simple message de bienvenue qui renvoie au menu, comme côté Garagiste |
| Produits | `/market-space/products` | `shared/ProductsView.vue` |
| Commandes | `/market-space/orders`, `/:id` | `shared/OrdersView`, `shared/OrderDetailView` |
| Messages | `/market-space/conversations` | `shared/ConversationsView.vue` (chat Market Space, v0.22 ; sans lien « Voir le devis ») |
| Avis | `/market-space/reviews` | `shared/ReviewsView.vue` |
| Réclamations | `/market-space/disputes`, `/:id` | `shared/DisputesView`, `shared/DisputeDetailView` |
| Notifications | `/market-space/notifications` | `shared/NotificationsView.vue` |
| Mon profil | `/market-space/profile` | `shared/ProfessionalProfileView.vue` (`space="market-space"`). Testé en navigateur (déclaration utilisateur, 2026-09-23), hors bloc CIP (v0.27) |

Tant que le dossier n'est pas approuvé ou que le profil est incomplet (`mustStayOnProfile`, store `auth`), les deux layouts ne proposent que « Mon profil ». Un compte suspendu voit un bandeau rouge avec le motif en haut de l'espace (`SuspensionBanner` dans `DashboardShell`), sans blocage d'accès ; depuis v0.28, il peut y demander la réactivation (testé en navigateur, déclaration utilisateur du 2026-09-23), avec pièces jointes facultatives (considérées comme validées par l'utilisateur, sans test en navigateur (2026-09-23)). La session est rafraîchie (`/auth/me`) au démarrage, sans bloquer l'affichage.

## 2. Backend construit et testé, sans écran frontend

- **Supervision admin en lecture** (12 routes, aucun écran, absentes du menu) : `admin/garages`, `admin/market-space-accounts`, `admin/appointments`, `admin/quotes`, `admin/orders`, `admin/conversations`, chacune en liste et en détail.
  - Depuis v0.26, `admin/garages` et `admin/market-space-accounts` (liste et fiche) ne portent que sur les structures au dossier `approved`, suspendues comprises (404 sur la fiche sinon) ; les dossiers en cours se consultent via `admin/registrations`. Même périmètre pour la répartition géographique de `admin/statistics` (données destinées aux autorités : structures validées uniquement).
- **Côté automobiliste (`/mobile/*`, 39 routes)** : l'API est complète, mais il n'existe pas d'application Flutter.
  - Listes et fiches garages et Market Space, avec leurs avis.
  - Recherche `nearby` : position, rayon, nom, service, produit, prix, tri.
  - RDV : création, annulation, acceptation d'une reprogrammation.
  - Devis : accepter/refuser une version, PDF.
  - Commandes : création, annulation, PDF.
  - Chat avec un garage **ou** une boutique.
  - Avis et réclamations sur un devis ou une commande.
  - Notifications, jeton d'appareil.
- **Auth et routes publiques** :
  - inscription automobiliste ;
  - inscription professionnelle en deux temps (v0.26) : formulaire court + code email, puis dossier (informations légales, document RCCM, soumission) depuis l'espace pro ;
  - login Google ;
  - `express-claim` ;
  - `locations/*` (utilisé par `LocationSelect`).
- **Jetons FCM des espaces pro** : `PUT garage|market-space/device-tokens` existent, mais le frontend web ne les appelle pas.
- **Transversal** :
  - notifications push avec envoi simulé tant que `FCM_SERVER_KEY` n'est pas renseignée ;
  - emails (devis au client express, décision de réclamation) ;
  - décrément de stock unique.

## 3. Ce qui n'existe nulle part

- **Application mobile Flutter** : le dépôt ne contient que `backend/` et `frontend-web/`.
- **Écran d'inscription automobiliste** côté web : volontairement absent (l'automobiliste passe par l'application mobile). Le parcours pro v0.26 est complet côté web : formulaire court et saisie du code (`/inscription`, 2026-09-23), puis informations légales, document, soumission et suivi du dossier sur « Mon profil ».
- **Tableaux de bord d'accueil** Garagiste et Market Space : un message de bienvenue seulement, sans chiffres clés ni raccourcis.
- **Écrans de supervision admin** : garages, boutiques, RDV, devis, commandes, conversations.
- **Paiement en ligne réel** : seul le paiement manuel V1 existe.
- **Chat temps réel** : ni WebSocket ni Reverb. Il faut recharger pour voir les nouveaux messages.
- **Envoi push FCM réel.**
- **Affichage carte** (frontend).
- **Authentification par téléphone/SMS.**
- **Extension multi-pays** : table `countries`, fuseau par structure.
- **Contestation d'un avis** par le professionnel.
- **Frontend** : aucun test automatisé, pas de design system.

## 4. Infrastructure de développement local

**Lancement du backend**
- Commande utilisée : `php artisan serve --no-reload`, depuis `backend/`, port par défaut **8000**.
  - Le frontend pointe sur `VITE_API_BASE_URL=http://127.0.0.1:8000/api` (`frontend-web/.env`).
- **Workers** : `PHP_CLI_SERVER_WORKERS=4` est défini dans `backend/.env.development` ; la ligne est commentée dans `.env.production`.
- **Pourquoi `--no-reload`** : `ServeCommand.php` n'honore `PHP_CLI_SERVER_WORKERS` qu'avec ce flag. Sans lui, Laravel affiche « Unable to respect the `PHP_CLI_SERVER_WORKERS` environment variable without the `--no-reload` flag » et ne lance qu'un seul serveur. Avec un seul worker, les requêtes lentes vers Supabase se bloquent les unes les autres.
- **Contrepartie** : le `.env` n'est jamais rechargé à chaud. Après tout `switch-env.sh` ou toute modification du `.env`, il faut tuer et relancer le serveur (c'était déjà la règle, CLAUDE.md §4).
- **Ne survit pas à un redémarrage** : ce mode n'est inscrit dans aucun script du dépôt. Après un reboot ou la fermeture du terminal, un simple `php artisan serve` repart avec un seul worker ; il faut relancer explicitement avec `--no-reload`.
- **Vérifié le 2026-09-23** : aucun processus `artisan serve` ni Vite ne tourne en ce moment. La commande et le port viennent de l'historique des sessions précédentes (le 2026-09-22, processus `php artisan serve --no-reload`, workers `php -S 127.0.0.1:…`). Le port 8001 a aussi été observé ce jour-là, quand 8000 était déjà occupé.

**Limites PHP d'envoi de fichiers**
- Relevées en local dans `/etc/php.d/99-makecars-uploads.ini` (fichier système, hors dépôt) : `upload_max_filesize = 12M`, `post_max_size = 50M`. Vérifié le 2026-09-23 avec `php -r 'echo ini_get(...)'`.
- Pourquoi : le document du registre de commerce peut peser jusqu'à 10 Mo. Les valeurs par défaut de PHP (2M / 8M) le refusaient avant même d'atteindre la validation Laravel. `post_max_size` couvre une requête qui envoie plusieurs fichiers à la fois.
- Conséquence : les pièces jointes de la demande de réactivation sont limitées à 5 Mo chacune (5 au plus). À 10 Mo, cinq fichiers atteindraient exactement 50 Mo, et PHP refuserait la requête avant Laravel ; à 5 Mo, une demande complète reste sous 25 Mo.
- **À reproduire sur le serveur de production** : même fichier `.ini` (ou équivalent selon l'hébergeur), plus la limite de corps de requête du serveur web s'il y en a une (ex. `client_max_body_size` pour Nginx).

**Emails en développement**
- `MAIL_MAILER=log` dans `.env.development` : aucun email n'est réellement envoyé, chacun est écrit dans `backend/storage/logs/laravel.log`.
- Lire le dernier code reçu (inscription ou mot de passe oublié), depuis `backend/` : `grep -A1 "letter-spacing:8px" storage/logs/laravel.log | tail -1 | tr -d ' \r'`. Vérifié le 2026-09-24 sur le vrai journal (les lignes y finissent par `\r`, d'où le `tr`). À adapter si le gabarit des emails de code change (la ligne qui suit ce style est celle du code).
- Depuis le 2026-09-24, un code faux ou tout autre refus métier (`ApiException`) n'écrit plus d'erreur dans ce journal.

**Latence Supabase et timeout Axios**
- **Ticket support Supabase SU-481692** : latence anormale du pooler `eu-central-1`.
  - *Référence fournie par l'utilisateur. Elle n'apparaît nulle part dans le dépôt, et son statut actuel n'a pas pu être vérifié.* À mettre à jour à la réception de la réponse de Supabase.
- **Timeout Axios** : `frontend-web/src/api/http.ts` est actuellement à `timeout: 30_000`, relevé depuis 15 s par le commit `528ef98` du 2026-09-22.
  - Le commentaire dans le fichier le marque comme **TEMPORAIRE**.
  - **À ramener à `15_000`** dès que la latence Supabase est redevenue normale. La valeur de 15 s est justifiée dans le même commentaire (jusqu'à ~10 s de latence mesurée en temps normal).

## 5. Identifiants de test (base de développement)

Mot de passe de tous les comptes seedés : **`password`** (vérifié dans `UserFactory`, `ProfessionalRegistrationTestSeeder`, `CatalogTestSeeder`).

| Compte | Email | Origine |
|---|---|---|
| Admin | `admin@makecars.test` | `DatabaseSeeder` |
| Garagiste « profil à compléter » (profil vide) | `garage.nouveau.incomplete@makecars.test` | `ProfessionalRegistrationTestSeeder` |
| Garagiste en attente, profil + infos légales complets | `garage.etoile.pending@makecars.test` | idem |
| Market Space en attente, complet | `pieces.express.pending@makecars.test` | idem |
| **Garagiste approuvé, profil complet** | `garage.excellence.approved@makecars.test` | idem |
| Market Space rejeté (avec motif) | `auto.pieces.rejected@makecars.test` | idem |
| **Market Space approuvé, profil complet** | `marche.pieces.approved@makecars.test` | `CatalogTestSeeder` |

- **Changement v0.26** : les seeders suivent le nouveau parcours (trait `SeedsProfessionalAccounts` : compte email vérifié, profil, informations légales avec IFU/NPI fictifs au bon format, document RCCM, soumission puis décision via `ProfessionalRegistrationService`). Aucun email réel n'est envoyé (`Mail::fake`). Nouveau compte `garage.nouveau.incomplete`.
- **Base de dev après la migration v0.26 (2026-09-23)** : les anciens dossiers `pending` (`garage.etoile`, `pieces.express`) sont passés `profile_incomplete` sans IFU/NPI ; les seeders n'ont **pas** été relancés sur Supabase — les relancer pour retrouver exactement les états du tableau.
- **Ordre d'exécution** : `ProfessionalRegistrationTestSeeder` puis `CatalogTestSeeder`. Les deux sont idempotents.
- **Relancés sur la base de dev le 2026-09-23** (lot v0.27/v0.28) : `garage.nouveau.incomplete`, `garage.etoile.pending`, `pieces.express.pending` et `auto.pieces.rejected` réinitialisés ; les deux comptes approuvés conservés.
- **Localisation et CIP (2026-09-23)** : les structures créées ou réinitialisées sont localisées à Cotonou (Littoral > Cotonou > arrondissement du quartier, recherche par slug) avec des coordonnées du quartier : Akpakpa → 2e arrondissement, Gbégamey → 10e, Fidjrossè → 12e, Ganhi → 4e (valeurs de test approximatives). Elles reçoivent aussi un faux CIP (exigé par la soumission depuis v0.27).
  - **Exception** : les deux comptes approuvés existants (`garage.excellence.approved`, `marche.pieces.approved`), jamais modifiés par les seeders, gardent leur ancienne localisation Alibori > Banikoara > Founougo et n'ont **pas de CIP** (sans blocage : pas d'exigence rétroactive). À corriger à la main si besoin, ou en supprimant ces comptes sans historique avant de relancer les seeders.
- **Historique toujours préservé (correctif du 2026-09-23)** : les seeders ne suppriment plus jamais de donnée ayant une trace réelle.
  - Comptes **approuvés** (`garage.excellence.approved`, `marche.pieces.approved`) : créés s'ils manquent, **jamais supprimés ni réinitialisés** s'ils existent, même dans un état différent du tableau.
  - Comptes d'**état d'inscription** (`garage.nouveau.incomplete`, `garage.etoile.pending`, `pieces.express.pending`, `auto.pieces.rejected`) : supprimés puis recréés (dans une transaction) **seulement** s'ils n'ont aucun historique : produits vendus en commande ou cités dans un devis, commandes, devis, RDV, conversations, avis, réclamations. Sinon ils sont conservés tels quels, avec un avertissement en console qui indique le compte et l'historique trouvé.
  - `CatalogTestSeeder` : un service ou produit de test référencé par une ligne de devis ou de commande (ou, pour un service, par un RDV) est conservé tel quel, avec un avertissement, et pas recréé en double. Il peut donc ne plus être `pending`.
  - Chaque passage affiche l'issue de chaque compte : créé, réinitialisé ou conservé.
  - Pourquoi : supprimer un compte supprime en cascade ses RDV, devis et conversations, et `order_lines.product_id` interdit de supprimer un produit déjà vendu.
- Après un `switch-env`, relancer `php artisan serve --no-reload`.
- Le mot de passe Supabase se renseigne à la main dans `.env.development`, jamais par l'assistant.

## 6. Points ouverts (§7 du CLAUDE.md)

1. **Cartographie (affichage carte)** : frontend uniquement. Candidat : OpenStreetMap via Leaflet (web) ou `flutter_map` (mobile). La **carte avec repère déplaçable** pour placer une structure est **reportée** (2026-09-24) : en attendant, « Mon profil » invite à relever les coordonnées au téléphone, sur place, et avertit quand la précision du navigateur dépasse 100 m (test réel : Vedoko placé vers Porto-Novo depuis un ordinateur). Avec OpenStreetMap, un fournisseur de fonds de carte sera peut-être nécessaire en production (limites d'usage des serveurs publics).
2. **Agrégateur de paiement** : Kkiapay, FedaPay ou autre. Le paiement manuel V1 sert en attendant.
3. **Chat temps réel** : Reverb, Pusher ou Supabase Realtime.
4. **Contestation d'un avis** par un pro avant modération.
5. **Cadre légal** du partage des données agrégées avec l'administration. **Complément (2026-09-23)** : la plateforme stocke désormais des pièces d'identité (CIP, v0.27 : photo, date de naissance, filiation). Avant la mise en production, définir au regard de la loi n° 2017-20 portant Code du numérique (protection des données personnelles, Bénin) : la durée de conservation de ces documents, l'information et le consentement du professionnel, et le sort des documents d'un dossier refusé ou d'un compte fermé.
6. **Auth téléphone/SMS** pour l'automobiliste.
7. **Périmètre de la mini-boutique** : catégories, limite, seuil de bascule vers un compte Market Space.
8. ~~**Pages de confirmation** des liens email~~ : construites en v0.30 (2026-09-24), voir §8.
9. **FCM** : `FCM_SERVER_KEY` non configurée, mode simulation. Migration future vers l'API HTTP v1.
10. **Fuseau horaire unique** `Africa/Porto-Novo`.
11. **Design system / identité visuelle** non choisis.
12. **Téléphone d'un automobiliste non béninois** (nouveau, commit `7068c44`) : `BeninPhoneNumber` refuse tout numéro hors `+229`. Il faut choisir en V2 entre assouplir ou assumer.
13. **Parcours d'inscription pro v0.26, suites** : test en navigateur du parcours boutique et de l'affichage sur téléphone (parcours garagiste testé le 2026-09-24), landing page (projet séparé), notification de l'admin à chaque soumission et à chaque nouvelle demande de réactivation (v0.28 : aujourd'hui l'admin doit penser à consulter le filtre « Réactivation demandée »), prénom/nom pour automobiliste/express/Google, `FRONTEND_URL` à renseigner dans `.env.production`.
14. **Suppression d'un compte professionnel** (nouveau, 2026-09-23) : supprimer un utilisateur pro supprime en cascade son profil, puis ses RDV, devis et conversations. Commandes, avis et réclamations, polymorphes, resteraient orphelins. Aucun endpoint ne supprime de compte aujourd'hui, mais le cahier des charges prévoit la suppression de comptes par l'admin. Avant de la construire, il faut garantir qu'aucun devis, facture ou commande ne disparaisse (CLAUDE.md §6, traçabilité) : désactivation ou suppression logique plutôt que suppression réelle.

**Autres pistes ouvertes**
- `scopePubliclyVisible()` ne filtre toujours pas sur la complétude du profil (vérifié dans `Garage.php`). Une fiche incomplète reste visible côté mobile.
- Vérification téléphone pour la finalisation d'un compte express.
- Index géospatial si le volume de professionnels croît.
- **Frontend, sujets mis de côté lors du backend v0.26** :
- **Infra (§4 ci-dessus)** : ramener le timeout Axios à 15 s, suivre le ticket SU-481692, fixer le mode `serve --no-reload` dans un script si on veut qu'il survive aux redémarrages.

## Bloquant avant la mise en production

À distinguer des points ouverts ordinaires (§6) : aucun lancement public tant qu'une case reste décochée.

- [ ] **Protection des données personnelles (prioritaire)**
  - *Raison* : la plateforme stocke des pièces d'identité (CIP : photo, date de naissance, filiation), ainsi que l'IFU, le NPI et le registre de commerce.
  - *À définir*, au regard de la loi n° 2017-20 portant Code du numérique en République du Bénin et des obligations auprès de l'autorité de protection des données personnelles (APDP) :
    - la durée de conservation de ces documents ;
    - l'information du professionnel et le recueil de son consentement au moment de l'envoi ;
    - le sort des documents d'un dossier refusé ou d'un compte fermé ;
    - l'éventuelle déclaration ou autorisation du traitement.
  - **À valider avec un juriste avant tout lancement public.**
  - *Voir* : §6, point 5 (cadre légal) ; CLAUDE.md §5 « Certificat d'Identification Personnelle (ajout v0.27) » et §7.
- [ ] **`FRONTEND_URL` renseignée dans `.env.production`**
  - *Raison* : les liens des emails d'inscription (compte créé, approbation, refus) et, depuis v0.30, ceux de la décision de devis et de l'activation d'un compte express sont construits à partir de cette valeur ; vide, ils pointent au mauvais endroit.
  - *Voir* : §6, point 13 ; CLAUDE.md §5 « Parcours d'inscription professionnelle en deux temps (ajout v0.26) ».
- [ ] **Limites d'envoi de fichiers PHP et du serveur web reproduites sur le serveur de production**
  - *Raison* : avec les valeurs PHP par défaut (2M / 8M), les justificatifs de 10 Mo (registre de commerce, CIP) sont refusés avant même la validation Laravel ; le serveur web peut avoir sa propre limite (ex. `client_max_body_size` pour Nginx).
  - *Voir* : §4 « Limites PHP d'envoi de fichiers ».
- [ ] **Suppression d'un compte professionnel : désactivation ou suppression logique**
  - *Raison* : une suppression réelle supprime en cascade RDV, devis et conversations, et laisse orphelins commandes, avis et réclamations. Aucun devis, facture ou commande ne doit jamais disparaître (traçabilité, CLAUDE.md §6).
  - *Voir* : §6, point 14 ; CLAUDE.md §7 « Suppression d'un compte professionnel ».
- [ ] **Timeout Axios ramené à 15 s** une fois la latence Supabase revenue à la normale
  - *Raison* : la valeur actuelle de 30 s est un palliatif temporaire, marqué comme tel dans `frontend-web/src/api/http.ts`.
  - *Voir* : §4 « Latence Supabase et timeout Axios ».
- [ ] **Toutes les migrations en attente appliquées sur la base de production**, jamais une seule à la fois.
  - *Raison* : depuis la v0.26, les migrations n'ont été appliquées que sur la base de développement. Le code actuel en dépend (connexion, inscription, dossier, réactivation) : déployer sans elles casse la production.
  - *Procédure* : `switch-env.sh prod`, `switch-env.sh status` pour confirmer, sauvegarde de la base, `php artisan migrate:status` pour lister les migrations en attente (et les noter ici), `php artisan migrate`, nouvelle vérification avec `migrate:status`, puis retour sur `dev` et redémarrage de `php artisan serve --no-reload`.
  - Liste actuelle des migrations en attente attendues : toutes celles du 2026-09-23 (v0.26 et v0.28, dont `create_reactivation_request_attachments_table`) et `create_password_reset_codes_table` (v0.29, 2026-09-24). À vérifier par `migrate:status`, sans lancer `migrate` en production.

## 7. Contrôle de cohérence du CLAUDE.md (v0.20 et suivantes)

Toutes les versions de v0.3 à v0.25 sont citées, sans trou de numérotation, et chacune a désormais sa section `###`. Trois écarts relevés le 2026-09-23 ont été corrigés le même jour dans CLAUDE.md :

1. **v0.22 a désormais sa section dédiée** (« Chat Market Space et conversation polymorphe »). La mention périmée de la contrainte unique `garage_id`+`user_id` dans « Module Chat (v0.8) » a été remplacée par un renvoi : la colonne `garage_id` n'existe plus depuis la migration `make_conversations_polymorphic`.
2. **La ligne de devis « Prestation libre » est documentée** dans une nouvelle section v0.25, et la liste des natures de lignes du « Module Devis/Facture (v0.8) » y renvoie.
3. **Le glossaire est à jour** : « Statistiques agrégées (admin) » parle maintenant de la répartition par département, séparée entre garages et Market Space (v0.19).

**v0.20, v0.21, v0.23, v0.24** : cohérents entre eux et avec le code contrôlé :
   - `RejectAppointmentRequest` : `reason` est `required` ;
   - `StoreServiceRequest` / `StoreProductRequest` : `image` est `required` ;
   - middleware `profile.complete` présent.

## 8. Derniers changements (depuis le 2026-09-20)

**Lot du 2026-09-24 : liens email et correctif de sécurité (v0.30)**
- **Faille corrigée** : la décision d'un devis par un client express se faisait sur un simple `GET`. Une messagerie ou un antivirus qui ouvre le lien pour l'analyser pouvait accepter le devis (et décrémenter le stock) ou le refuser sans action du client. Règle désormais : un `GET` sur un lien reçu par email n'a jamais d'effet ; seul un `POST` explicite décide.
- Backend `5f2dbeb` :
  - une seule URL signée `quotes/{quote}/versions/{version}/email-decision` : `GET` lecture seule (détail, `is_decidable`, expiration), `POST { decision: accept|reject }` ;
  - signatures relatives (`absolute: false`, `signed:relative`) pour la décision de devis et l'activation d'un compte express : le frontend joint l'API par `127.0.0.1` alors qu'`APP_URL` dit `localhost` ;
  - les emails pointent vers le frontend (`/devis/decision?link=…&choix=accepter|refuser`, `/compte/activer?link=…`) ;
  - réclamation : le `GET` renvoie le prénom et l'email masqué, plus l'adresse complète ;
  - `assertVersionIsDecidable` renvoie **409 `quote_version_not_decidable`** (version déjà décidée, périmée ou non envoyée) au lieu de 403, **y compris sur les endpoints mobiles** accepter/refuser (aucun client mobile n'existait encore). 403 reste réservé à l'accès refusé ;
  - tests : un `GET` ne change ni le statut, ni le stock, ni les notifications ; `POST` accept/reject ; `decision` invalide ; lien expiré, modifié, ou appelé par un autre hôte ; version déjà décidée ou périmée (409). OpenAPI mis à jour.
- Frontend `82b733f` : pages `/devis/decision` et `/compte/activer` (`views/public/`), client `api/emailLinks.ts` (instance Axios sans jeton ni redirection, paramètre `link` vérifié : même origine que l'API et route attendue).
- **Pas encore testé en navigateur.**
- Lien d'activation expiré : aucun écran web ne permet d'en demander un nouveau (`POST /auth/express-claim` n'est appelé par aucune interface, l'application mobile n'existant pas). La page dit seulement que le lien n'est plus valide.
- Lire les liens reçus en développement (`MAIL_MAILER=log`), depuis `backend/` :
  - décision de devis : `grep -o 'http[^"]*/devis/decision?[^"]*' storage/logs/laravel.log | tail -2 | sed 's/&amp;/\&/g'` (lien « accepter » puis « refuser ») ;
  - activation d'un compte express : `grep -o 'http[^"]*/compte/activer?[^"]*' storage/logs/laravel.log | tail -1`.

**Lot du 2026-09-24 : fiche admin d'examen d'un dossier d'inscription**
- Backend `e0d4be7` : `ProfessionalRegistrationResource` expose `representative` (prénom, nom, nom d'affichage, email, téléphone de la personne inscrite), **réservé à l'admin** comme IFU/NPI/profil/décisions (tests : présent sur la fiche admin, absent de `/auth/me`). Seule donnée manquante pour la fiche ; tout le reste était déjà renvoyé. Libellé du document de registre corrigé en « Registre de commerce (RCCM) » (`RegistrationDocumentType`).
- Frontend `3df53f1` : fiche `/admin/registrations/:id` en sections (en-tête, représentant, structure avec « Voir sur la carte », horaires, photos, informations légales avec « Consulter le CIP », justificatifs, historique des décisions, compte/réactivation, décision), une colonne sur téléphone, rien de modifiable. Date de soumission corrigée : `submitted_at` (« Jamais soumis » si vide) au lieu de `created_at`. Libellé « Numéro RCCM » au lieu de « N° IFU-RCCM ». Liste : filtre « Profil à compléter », colonnes soumission / création du compte.
- **Pas encore testé en navigateur.**

**Lot du 2026-09-24 (v0.29, après les tests de l'inscription garagiste)**
- Backend `885be9b` :
  - une `ApiException` (code faux, code expiré, demande déjà en attente…) n'est plus journalisée comme une erreur (`dontReport` dans `bootstrap/app.php`) ; la réponse JSON ne change pas ; test `Exceptions::fake()` ;
  - messages de validation en français : `lang/fr/{validation,auth,passwords,pagination}.php`, noms de champs traduits, messages dédiés pour un email ou un téléphone déjà utilisé ; `APP_LOCALE=fr` fixé dans `phpunit.xml`. Aucune dépendance ajoutée. Aucun message anglais dans les FormRequest (`grep`).
- Frontend `b2b4bf3` :
  - champ du code : exemple neutre `●●●●●●` au lieu de `123456` ;
  - `ConfirmDialog` / `confirmAction()` à la place de `window.confirm` sur 5 écrans : Mon profil (soumission du dossier), Services (suppression), Produits (suppression), détail d'une commande (marquer payée), détail d'un devis (marquer payé, abandonner) ;
  - « Mon profil » : « Nom du garage » / « Nom de la boutique » avec l'aide « Le nom sous lequel les automobilistes vous trouveront. », aussi dans la liste des éléments manquants (`missingFieldLabel`) ;
  - « Mon profil », position : aide pour relever les coordonnées avec Google Maps au téléphone (« atelier » / « boutique »), précision du relevé affichée (`coords.accuracy`), avertissement au-delà de 100 m.
- Test `f89b5d6` : parcours d'inscription Market Space de bout en bout (`MarketSpaceSignupJourneyTest`), qui remplace deux tests Market Space qu'il couvre entièrement.
- **Mot de passe oublié (v0.29)** : backend `85970a4` (table `password_reset_codes`, service, 2 routes, email, 14 tests ; règles du code mises en commun avec l'inscription dans `config/email_codes.php` et `App\Support\EmailCode`), frontend `b06fc59` (page `/mot-de-passe-oublie`, `CodeInput`, `useResendCountdown`). Migration appliquée sur la base de **développement** uniquement. Considéré comme validé par l'utilisateur, sans test en navigateur (2026-09-24).

**Lot du 2026-09-23 (après les tests de « Mon profil »)**
- Corrections frontend (`c0506e4`) :
  - champs verrouillés visibles (variantes Tailwind `disabled:`) sur « Mon profil » ;
  - badge « Profil à compléter » dans les cartes Structures du tableau de bord admin (`profile_incomplete`, typé dans `types/statistics.ts`) ;
  - plus aucune mention de CLAUDE.md, de § ou de version dans les textes affichés (6 écrans admin) ;
  - une seule rubrique active dans le menu, pages de détail comprises (`DashboardShell`) ;
  - « Mon profil » reporte l'état du dossier dans la session (`auth.updateRegistrationFromProfile`).
- **Certificat d'Identification Personnelle (v0.27)** : backend `166c773` (le faux CIP des seeders y est inclus, car `submit()` l'exige), frontend `9668a4e` (`LegalDocumentField`).
- Seeders : localisation cohérente à Cotonou (`6b3e3a0`).
- **Demande de réactivation (v0.28)** : backend `b02c872` (+ `690759a`, retrait d'un fichier généré par erreur), frontend `91a9cb8`. Migration `create_reactivation_requests_table` appliquée sur la base de **développement** uniquement ; à appliquer en production lors du prochain déploiement.
- **Pièces jointes de la demande de réactivation (v0.28, complément)** : backend `62a9c48`, frontend `80c84c6`.
  - Pourquoi : pendant les tests, l'admin a refusé une demande avec le motif « Merci de joindre la preuve du remboursement du client », alors que la demande n'acceptait que du texte.
  - 0 à 5 fichiers jpg/jpeg/png/pdf de 5 Mo au plus, disque privé des médias, table `reactivation_request_attachments` (jamais supprimées) ; téléchargement par le propriétaire et l'admin (404 sinon) ; si l'enregistrement échoue, les fichiers déjà écrits sont effacés.
  - Pas de mutualisation avec `DisputeAttachment` : colonnes différentes (position vs nom d'origine, type, taille) et clé étrangère différente ; le seul point commun serait `download()`, une ligne.
  - Frontend : `ReactivationRequestModal` (nouvelle fenêtre, `ReasonPromptModal` inchangé), nombre de pièces jointes dans le bandeau, `ReactivationAttachmentList` sur la fiche admin.
  - Migration `create_reactivation_request_attachments_table` appliquée sur la base de **développement** uniquement.
  - **Considérées comme validées par l'utilisateur, sans test en navigateur (2026-09-23).**
- **Écrans d'inscription professionnelle (v0.26, frontend)** : `f737da2`.
  - Routes publiques `/inscription` (choix), `/inscription/garagiste` et `/inscription/boutique` (même écran `SignupFormView`, prop `accountType`), `/inscription/verification/:id` (saisie du code), `/inscription/confirmation`. Lien depuis `/login`. Un utilisateur connecté est renvoyé vers son espace (`meta.guestOnly`).
  - Store `signup` en mémoire seulement (mot de passe), vidé après vérification et à la sortie du parcours (`meta.signupFlow`) ; l'id de vérification dans l'URL permet de saisir le code après un rechargement.
  - Aucun changement backend.
  - **Construits, pas encore testés** en navigateur.

**Règles métier**
- **Parcours d'inscription professionnelle en deux temps (v0.26, 2026-09-23)**, backend uniquement :
  - prénom/nom séparés, `name` recomposé (`926850d`) ;
  - inscription courte + vérification de l'email par code, table `pending_professional_registrations` (`e421d74`) ;
  - statut `profile_incomplete`, informations légales (RCCM + document, IFU 13 chiffres, NPI 10 chiffres), soumission, middlewares `registration.approved` / `registration.editable`, historique `registration_decisions`, emails approbation/refus, migration des données existantes (`3555d53`) ;
  - l'ancien endpoint multipart `register/professionnel` (justificatifs + photos) est supprimé.
- **Motif obligatoire pour le refus d'un RDV (v0.24, `b3b639a`)** : `reason` passe de `nullable` à `required`.
- **Image obligatoire pour un service ou un produit (v0.23, `958cbf6`)** : obligatoire à la création. En modification, l'image existante suffit. Aucune action « supprimer l'image ».
- **Chat Market Space ↔ automobiliste (v0.22, `dcec7dc`)** :
  - `Conversation` devient polymorphe (`sellable_type` / `sellable_id`), unique par couple (vendeur, automobiliste) ;
  - `POST /mobile/conversations` accepte `garage_id` **ou** `market_space_account_id` ;
  - la facture d'une commande Market Space payée est postée dans le chat.
- **Ligne de devis « Prestation libre » (`636fa95`, `3165653`)** : réparation découverte après inspection, sans fiche catalogue. Documentée dans CLAUDE.md en v0.25.
- **Point ouvert téléphone non béninois (`7068c44`)** : documentaire uniquement.

**Construction des dashboards (frontend + ajustements backend)**
- Garagiste :
  - RDV `5eb6307` ;
  - Services `85f0f8f` ;
  - Produits `46c2733` ;
  - Devis `88c25ee` ;
  - Chat `bfbaf32` ;
  - Commandes `f9c4ef6` et `d94198f` ;
  - Avis, Réclamations, Notifications `600a233` ;
  - correctifs `afcffbc` et `0a07841`.
- Market Space :
  - Produits et Commandes `f48dfab` ;
  - Avis, Réclamations, Notifications `874281c`.

**Mutualisation des écrans Garagiste / Market Space (2026-09-23, `07d536e`)**
- Produits, Commandes (+ détail), Messages, Avis, Réclamations (+ détail) et Notifications déplacés dans `views/shared/`, prop obligatoire `space` ; les copies `views/market-space/*`, `api/marketSpace*.ts` et `types/marketSpace*.ts` sont supprimées.
- API des espaces pro : `space` en premier paramètre obligatoire (`api/professionalProducts.ts`, `api/professionalReviews.ts`, `orders`, `disputes`, `notifications`, `conversations`). `fetchAllGarageProducts()` reste propre au Garagiste (devis).
- Chemins et noms de route inchangés ; backend inchangé. Convention documentée dans CLAUDE.md §4.
- Market Space toujours **non testé en navigateur** (voir « Niveau de vérification »).

**Correctifs et ajustements techniques**
- `0b70784` : rechargement des relations Eloquent après approve / reject / suspend / moderate / resolve (écrans admin).
- `b0aca16` : relations et pagination des devis, `per_page` sur services et produits.
- `c037052` : expéditeur chargé après envoi, `quote_id` exposé sur les messages système.
- `7095e8d` : écran Admin Produits résistant à un vendeur orphelin.
- `0cd8a5f` : mémoïsation de `missingProfileFields()`.
- `cc028fa` : curseur main sur les boutons actifs.
- `528ef98` : timeout Axios à 30 s, temporaire (§4).
- `d3cf4ed`, `10f3cee` : seeders avec profil complet.

**Rappel opérationnel** (CLAUDE.md §9) : après chaque commit sur `main`, push immédiat vers `origin/main` (autorisation permanente). Les opérations destructrices restent soumises à confirmation.
