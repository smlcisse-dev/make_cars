# Make Cars : document de passation (état au 2026-09-23)

Remplace la version du 2026-09-20 (commit `473b70c`). Chaque point de la section « Vérifié » a été contrôlé le 2026-09-23 par une commande ou une lecture de code. Les points qui n'ont pas pu l'être sont signalés comme tels.

**Vérifié dans le code / par commande**
- **Git** : `main` est synchronisée avec `origin/main` (0 commit d'avance, 0 de retard après `git fetch`). Seul élément non suivi : `.claude/`. **69 commits** au total, dont 27 depuis la passation précédente.
- **Dernier commit** : `528ef98` chore(http): relève temporairement le timeout Axios à 30 s.
- **Tests backend** (`php artisan test`, SQLite en mémoire via `phpunit.xml`, donc sans toucher Supabase) : **523 tests, 523 réussis, 1589 assertions** après le backend v0.26 (475 tests et 1232 assertions juste avant).
- **Environnement actif** (`backend/bin/switch-env.sh status`) : `.env.development`, `DB_USERNAME=postgres.ppfflfwzqmckciqikhzn` (projet Supabase de développement, pooler `eu-central-1`).
- **Routes** (`php artisan route:list`) : **183 routes** au total (173 avant le parcours d'inscription v0.26 : +4 par espace pro, +2 en auth).

| Espace | Routes | Détail |
|---|---|---|
| `api/garage/*` | 53 | profil 9 (dont dossier v0.26 : `profile/legal`, `profile/legal/document` ×2, `profile/submit`), services 5, produits 5, RDV 7, devis 10, commandes 4, chat 4, réclamations 4, notifications 2, avis 1, client express 1, jeton FCM 1 |
| `api/market-space/*` | 30 | profil 9 (mêmes routes de dossier v0.26), produits 5, commandes 4, chat 4, réclamations 4, notifications 2, avis 1, jeton FCM 1 |
| `api/admin/*` | 39 | inscriptions 7, litiges 7, services 4, produits 4, avis 3, statistiques 1, supervision en lecture 12 (garages, boutiques, RDV, devis, commandes, conversations : 2 chacun) |
| `api/mobile/*` | 39 | automobiliste (voir §3) |
| `api/auth/*` | 9 | login, login Google, logout, me, inscription automobiliste, inscription pro en 3 routes (`register/professionnel`, `{uuid}/verify`, `{uuid}/resend`), `express-claim` |
| Publiques hors auth | 8 | `locations/*` 3, décision de devis par email 2, réclamation de compte express 2, `health` 1 |
| Hors `api/` | 5 | `sanctum/csrf-cookie`, `storage/{path}` ×2, `up`, `_boost/browser-logs` |

**Niveau de vérification des écrans**

Aucun test automatisé côté frontend : `package.json` n'a pas de script de test. On distingue deux niveaux.

- **Testé manuellement en navigateur, écran par écran** : le **dashboard Garagiste au complet**, c'est-à-dire Services, Produits, Rendez-vous, Devis/Factures, Chat, Commandes, Avis, Réclamations et Notifications (plus Mon profil).
  - *Source : déclaration de l'utilisateur. Rien dans le dépôt ne permet de le constater.*
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
- `/login` : connexion email/mot de passe.
- `/403` et page 404.
- Composants partagés (`src/components`) : `AppButton`, `AppTable`, `AppPagination`, `BaseModal`, `ReasonPromptModal`, `StatusBadge`, `LocationSelect`.
- Layouts : `AdminLayout`, `GarageLayout`, `MarketSpaceLayout`, tous construits sur `DashboardShell`.

**Espace Admin (`/admin`)** : construit, relu, jamais testé en navigateur

| Menu | Routes | Périmètre |
|---|---|---|
| Tableau de bord | `/admin` | Statistiques agrégées (`GET /admin/statistics`), en cartes et tableaux, sans graphiques |
| Inscriptions | `/admin/registrations`, `/:id` | Fiche KYC. Approbation, rejet, suspension et réactivation (motif obligatoire) |
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
| Mon profil | `/garage/profile` | `shared/ProfessionalProfileView.vue` (`space="garage"`) |

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
| Mon profil | `/market-space/profile` | `shared/ProfessionalProfileView.vue` (`space="market-space"`) |

Tant que le profil est incomplet, les deux layouts ne proposent que « Mon profil ».

## 2. Backend construit et testé, sans écran frontend

- **Supervision admin en lecture** (12 routes, aucun écran, absentes du menu) : `admin/garages`, `admin/market-space-accounts`, `admin/appointments`, `admin/quotes`, `admin/orders`, `admin/conversations`, chacune en liste et en détail.
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
  - liens signés de décision de devis et de réclamation de compte express (réponse JSON brute) ;
  - `locations/*` (utilisé par `LocationSelect`).
- **Jetons FCM des espaces pro** : `PUT garage|market-space/device-tokens` existent, mais le frontend web ne les appelle pas.
- **Transversal** :
  - notifications push avec envoi simulé tant que `FCM_SERVER_KEY` n'est pas renseignée ;
  - emails (devis au client express, décision de réclamation) ;
  - décrément de stock unique.

## 3. Ce qui n'existe nulle part

- **Application mobile Flutter** : le dépôt ne contient que `backend/` et `frontend-web/`.
- **Écrans d'inscription** (automobiliste et professionnel) côté web : seul `/login` existe. Pour le parcours pro v0.26 : formulaire court, saisie du code, informations légales + document sur « Mon profil », bouton « Soumettre pour validation », page de suivi du dossier.
- **Tableaux de bord d'accueil** Garagiste et Market Space : un message de bienvenue seulement, sans chiffres clés ni raccourcis.
- **Écrans de supervision admin** : garages, boutiques, RDV, devis, commandes, conversations.
- **Paiement en ligne réel** : seul le paiement manuel V1 existe.
- **Chat temps réel** : ni WebSocket ni Reverb. Il faut recharger pour voir les nouveaux messages.
- **Envoi push FCM réel.**
- **Affichage carte** (frontend).
- **Pages Vue de confirmation** pour les liens email (décision de devis, réclamation de compte express).
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
- Après un `switch-env`, relancer `php artisan serve --no-reload`.
- Le mot de passe Supabase se renseigne à la main dans `.env.development`, jamais par l'assistant.

## 6. Points ouverts (§7 du CLAUDE.md)

1. **Cartographie (affichage carte)** : frontend uniquement. Candidat : OpenStreetMap via Leaflet (web) ou `flutter_map` (mobile).
2. **Agrégateur de paiement** : Kkiapay, FedaPay ou autre. Le paiement manuel V1 sert en attendant.
3. **Chat temps réel** : Reverb, Pusher ou Supabase Realtime.
4. **Contestation d'un avis** par un pro avant modération.
5. **Cadre légal** du partage des données agrégées avec l'administration.
6. **Auth téléphone/SMS** pour l'automobiliste.
7. **Périmètre de la mini-boutique** : catégories, limite, seuil de bascule vers un compte Market Space.
8. **Pages de confirmation** des liens email (devis, compte express).
9. **FCM** : `FCM_SERVER_KEY` non configurée, mode simulation. Migration future vers l'API HTTP v1.
10. **Fuseau horaire unique** `Africa/Porto-Novo`.
11. **Design system / identité visuelle** non choisis.
12. **Téléphone d'un automobiliste non béninois** (nouveau, commit `7068c44`) : `BeninPhoneNumber` refuse tout numéro hors `+229`. Il faut choisir en V2 entre assouplir ou assumer.
13. **Parcours d'inscription pro v0.26, suites** : écrans frontend du parcours, landing page (projet séparé), notification de l'admin à chaque soumission, prénom/nom pour automobiliste/express/Google, `FRONTEND_URL` à renseigner dans `.env.production`.

**Autres pistes ouvertes**
- `scopePubliclyVisible()` ne filtre toujours pas sur la complétude du profil (vérifié dans `Garage.php`). Une fiche incomplète reste visible côté mobile.
- Vérification téléphone pour la finalisation d'un compte express.
- Index géospatial si le volume de professionnels croît.
- **Frontend, sujets mis de côté lors du backend v0.26** :
  - rafraîchissement de la session : `fetchCurrentUser()` n'est jamais appelée, donc un changement de statut du dossier n'est vu qu'après reconnexion ;
  - bandeau « compte suspendu » avec motif ;
  - limites d'envoi de fichiers de PHP (`upload_max_filesize`, `post_max_size`) à vérifier au regard des 10 Mo du registre de commerce ;
  - le commentaire de `frontend-web/src/types/user.ts` sur `profile_status` est périmé : il est désormais renseigné pour tout professionnel qui a un profil, quel que soit le statut du dossier ;
  - le nouveau 403 `registration_not_approved` n'est pas géré par l'intercepteur Axios (seul `profile_incomplete` l'est).
- **Infra (§4 ci-dessus)** : ramener le timeout Axios à 15 s, suivre le ticket SU-481692, fixer le mode `serve --no-reload` dans un script si on veut qu'il survive aux redémarrages.

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
