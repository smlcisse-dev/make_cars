# Make Cars — Plateforme de digitalisation de la mécanique automobile

Contexte de référence : `cahier_des_charges_make_cars.docx` (v0.3, juillet 2026). Ce fichier résume le cahier des charges pour guider le développement et intègre les ajustements validés depuis (signalés par une date, ex. « ajout v0.4 »). En cas de divergence, ce fichier fait foi ; se référer au document original pour le contexte détaillé non repris ici.

## 1. Contexte et objectifs

Au Bénin / Afrique de l'Ouest, le secteur de la mécanique automobile est largement informel : garages non enregistrés, pièces détachées d'occasion ou de mauvaise qualité, aucun moyen pour un automobiliste d'évaluer la fiabilité d'un garage avant d'y recourir. Conséquences : risques pour la sécurité routière, absence de concurrence saine, prix abusifs.

Le projet digitalise ce secteur en :
- permettant à un automobiliste en panne de localiser en temps réel les garages proches ;
- permettant la prise de rendez-vous, le paiement en ligne et la génération automatique de devis/factures ;
- réduisant la circulation de pièces de mauvaise qualité via une validation admin systématique (comptes, services, produits) ;
- instaurant un système d'avis/notation pour créer un climat de confiance ;
- structurant la gestion interne des garages/boutiques (stock, inventaire) ;
- offrant à l'administrateur une supervision complète (comptes, validations, litiges) ;
- fournissant aux autorités béninoises des données agrégées et fiables sur le secteur (nombre de structures, répartition géographique, volumes d'activité), pour appuyer des politiques de régulation et de formalisation.

**Portée géographique** : cible initiale le Bénin, avec extension envisageable à d'autres pays d'Afrique de l'Ouest — l'architecture doit rester scalable en ce sens.

**Limite importante** : la plateforme rend un garage/boutique non enregistré invisible et non sollicitable via l'appli, mais ne peut pas empêcher son activité physique dans la rue. L'objectif de formalisation du secteur dépend d'un partenariat institutionnel (mairies, ministère du commerce/transport), pas uniquement de la plateforme.

## 2. Composants de la solution

Deux volets applicatifs partageant le **même backend** :
- **Application mobile (Flutter)** — exclusivement pour les automobilistes (clients grand public). Aucun accès aux dashboards de gestion.
- **Plateforme web (Laravel)** — regroupe trois espaces distincts :
  - **Dashboard Administrateur**
  - **Dashboard Garagiste**
  - **Market Space** (boutiques de pièces détachées)

Le backend Laravel expose une **API REST unique**, consommée à la fois par l'app Flutter et par les interfaces web.

## 3. Acteurs et utilisateurs

| Acteur | Accès | Rôle |
|---|---|---|
| **Automobiliste** | App mobile uniquement | Recherche de garage/boutique, prise de RDV, paiement, chat, avis. Aucun accès web. |
| **Compte Garagiste** | Dashboard Garagiste (web) | Profil garage, services, stock/inventaire (pièces d'atelier **et** mini-boutique), RDV, devis/factures, chat. Peut vendre directement un petit catalogue de produits courants via une mini-boutique liée à son profil (voir §5). |
| **Compte Market Space** | Dashboard Market Space (web) | Produits, stock, commandes, paiements, chat. Réservé aux boutiques de pièces détachées à part entière, avec leur propre inscription/KYC — distinct de la mini-boutique d'un garage. |
| **Administrateur** | Dashboard Admin (web), lecture sur tous les dashboards | Validation des inscriptions/services/produits, gestion des comptes, litiges, modération des avis, supervision globale. |

**Règle clé** : un même garage peut détenir **un compte Garagiste ET un compte Market Space distinct** (deux comptes séparés, chacun avec sa propre validation admin, rattachables à la même structure) — réservé au cas où le garage veut tenir une boutique à part entière. Indépendamment de cela, tout compte Garagiste peut vendre un petit catalogue de produits courants directement sur son propre profil, sans compte Market Space (voir §5). Un boutiquier sans activité de réparation ne peut ouvrir qu'un compte Market Space, jamais un compte Garagiste.

## 4. Architecture technique retenue

- **Backend** : Laravel, **API REST pure** (aucune vue Blade servie par le backend), documentée avec **OpenAPI/Swagger**. Consommée à la fois par le frontend web et l'app Flutter.
- **Structure backend** : couche **Service** dédiée entre les Controllers et les Models pour séparer la logique métier (pattern Controller → Service → Model/Repository) — pas de logique métier directement dans les controllers.
- **Authentification API** : **Laravel Sanctum** (tokens), utilisée par le frontend web et l'app mobile. Émission de token identique quelle que soit la méthode de connexion (email/mot de passe ou Google — voir §5, ajout v0.5) : Sanctum n'est pas concerné par *comment* l'utilisateur a prouvé son identité, seulement par l'émission du token une fois l'identité établie.
- **Frontend web** : SPA **découplée** en **Vue 3 + TypeScript**, un seul projet (`frontend-web/`) gérant les trois espaces (Admin / Garagiste / Market Space) via son propre routing/permissions internes. Communique uniquement via l'API REST, comme Flutter. Gère elle-même l'auth (stockage du token Sanctum) ; CORS déjà configuré côté Laravel (`backend/config/cors.php`, origine par défaut `http://localhost:5173`). Outillage retenu (socle posé le 2026-09-17) : **Vite** (build/dev server), **Vue Router** (routing interne des 3 espaces + garde de navigation par rôle), **Pinia** en style *setup store* (Composition API), **Axios** (client HTTP unique, intercepteur pour le token Sanctum et la déconnexion automatique sur 401), **Tailwind CSS** (choix explicite de l'utilisateur : contrôle total du design, pas de bibliothèque de composants imposée — voir aussi la note pédagogique en tête de fichier).
- **Application mobile** : Flutter (Android / iOS).
- **Base de données** : PostgreSQL hébergée sur **Supabase**. Deux projets Supabase distincts (développement et production) — voir « Environnements de base de données » ci-dessous.
- **Géolocalisation / cartographie** : prestataire tiers à sélectionner (Google Maps, Mapbox ou OpenStreetMap — non encore arbitré, cf. §7 points ouverts).
- **Stockage fichiers** (images, justificatifs KYC) : Supabase Storage ou équivalent.
- **Paiement** : agrégateur local à sélectionner (ex. Kkiapay, FedaPay) supportant Mobile Money (MTN Mobile Money, Moov Money, Celtiis Cash) et carte bancaire.
- **Notifications push** : Firebase Cloud Messaging (FCM) ou équivalent.
- **Chat temps réel** : solution à arbitrer (WebSockets / Laravel Reverb, Pusher, ou Supabase Realtime).

> Note pédagogique : l'utilisateur découvre Vue 3 et TypeScript sur ce projet — expliquer brièvement les concepts clés (Composition API, réactivité, typage) au fil du code produit côté frontend.

### Environnements de base de données : développement et production (ajout 2026-09-18)

Deux projets Supabase séparés, pour ne jamais faire porter des essais/migrations risqués à la base réelle :

- **Production** : projet Supabase historique du projet (`postgres.aowpgpeabffpqneflzas`), utilisé depuis le début du développement.
- **Développement** : second projet Supabase créé le 2026-09-18, dédié aux essais locaux (migrations, seeders, données de test) — même host/port/nom de base (pooler Supabase région `eu-central-1`), seul l'identifiant de projet (`DB_USERNAME`) et le mot de passe diffèrent.

**Mécanisme de bascule** : `backend/.env` n'est plus un fichier ordinaire mais un **lien symbolique** vers `backend/.env.development` ou `backend/.env.production` (les deux fichiers réels, jamais commités — `backend/.gitignore`). Un lien symbolique plutôt qu'une copie manuelle : `readlink backend/.env` (ou le script ci-dessous) dit toujours sans ambiguïté quel environnement est actif, alors qu'un `.env` obtenu par copie ne garde aucune trace de son origine.

- **Basculer** : `backend/bin/switch-env.sh dev` (développement) ou `backend/bin/switch-env.sh prod` (production).
- **Vérifier l'environnement actif** (à faire en cas de doute avant toute opération sensible — migration, seed, requête destructive) : `backend/bin/switch-env.sh status`, qui affiche le fichier ciblé par le lien ainsi que `DB_HOST`/`DB_USERNAME` (jamais le mot de passe).
- **Redémarrer `php artisan serve`** après une bascule : le `.env` n'est relu qu'au démarrage du processus PHP, pas à chaud.
- **Défaut volontaire sur développement** : après une installation/un clone, `.env` doit pointer vers `.env.development`, jamais `.env.production` par défaut — un oubli de bascule doit tomber sans risque sur la base de test, jamais sur la base réelle.
- **Mot de passe jamais dans ce dépôt ni généré par l'assistant** : chaque `.env.{development,production}` est créé avec `DB_PASSWORD` vide (développement) ou déjà renseigné manuellement par l'utilisateur (production, existant) ; toute mise à jour de mot de passe se fait à la main, directement dans le fichier concerné.
- **Migrations** : après bascule sur `dev` et mot de passe renseigné dans `backend/.env.development`, `php artisan migrate` (voire `migrate:fresh --seed` pour un jeu de données de test) applique le même schéma que la production, sans risque pour les données réelles.

## 5. Règles de gestion transversales

Ces règles s'appliquent à toute l'application et doivent être respectées par toute fonctionnalité développée :

1. Un garage qui ne vend aucun produit ne dispose que d'un compte Garagiste.
2. Un garage qui veut tenir une boutique de pièces détachées à part entière (catalogue large, identité commerciale propre) peut ouvrir en plus un compte Market Space distinct — chaque compte a sa propre procédure de validation. Ce n'est pas nécessaire pour un simple catalogue de produits courants lié au profil du garage (voir « Mini-boutique Garage » ci-dessous).
3. Un boutiquier sans activité de réparation ne peut ouvrir qu'un compte Market Space, jamais un compte Garagiste.
4. **Toute inscription** (Garagiste ou Market Space) nécessite des justificatifs (registre de commerce / IFU-RCCM, photo du local) et est soumise à validation admin. Aucune structure non formalisée n'est validée — exigence assumée pour appuyer la formalisation du secteur.
5. **Tout service ou produit** ajouté ou modifié est soumis à validation admin avant d'être visible côté application mobile.
6. **Tous les paiements** (services et pièces) transitent obligatoirement par la plateforme ; devis et factures y sont générés automatiquement.
7. Un automobiliste ne peut laisser un avis/note qu'**après** une prestation réalisée ou un achat confirmé.
8. L'administrateur a un accès total en lecture (et modération) sur l'ensemble des dashboards et données.
9. L'application mobile est strictement réservée aux automobilistes — aucun accès aux dashboards de gestion.
10. La prise de rendez-vous ne déclenche **aucun paiement**. Séquence garage : RDV → devis demandé sur place → validation devis par le client → démarrage prestation → paiement total en fin de prestation → facture auto-générée.
11. Séquence Market Space : commande → paiement immédiat sur la plateforme → facture auto-générée.
12. Moyens de paiement : MTN Mobile Money, Moov Money, Celtiis Cash, carte bancaire.
13. Un compte professionnel déjà validé (Garagiste ou Market Space) peut être **suspendu** par l'administrateur (fraude, plaintes répétées, pièces de mauvaise qualité signalées) — motif obligatoire, comme pour un rejet d'inscription. Un compte suspendu devient invisible côté application mobile (comme un compte non encore validé) mais conserve tout son historique (produits, services, avis) pour une éventuelle réactivation (voir « Suspension de compte professionnel » ci-dessous).

### Mini-boutique Garage vs Market Space (ajout v0.4, 2026-09-16)

Un compte Garagiste peut vendre un petit catalogue de produits courants (huiles, pneus, consommables) **directement lié à son profil**, sans ouvrir de compte Market Space séparé. Précisions à respecter dans toute implémentation :

- **Un seul vendeur par produit** : un produit appartient soit à un profil Garage, soit à un compte Market Space — jamais aux deux. La logique métier (catalogue, commande, paiement) doit être **mutualisée** entre les deux cas plutôt que dupliquée (ex. modèle `Product` avec relation polymorphe vers `Garage` ou `MarketSpaceAccount`, mêmes services de commande/paiement pour les deux).
- **Validation admin identique** : un produit de mini-boutique Garage suit la même règle que les produits Market Space (règle 5 ci-dessus) — soumis à validation admin avant d'être visible dans l'app mobile.
- **Visibilité mobile** : les produits de la mini-boutique d'un garage sont affichés sur le profil de ce garage dans l'app mobile (pas dans une liste Market Space distincte), avec commande et paiement en ligne comme pour le Market Space.
- **Un seul stock par garage** : un garage ne tient qu'**un seul** stock/inventaire de pièces et consommables (pas un stock « atelier » et un stock « mini-boutique » séparés).
- **Un seul mécanisme de décrément de stock** : la vente d'une pièce via la mini-boutique est la seule opération qui fait diminuer ce stock — que la vente soit isolée (le client achète juste une huile) ou intégrée à une prestation (la pièce nécessaire à la réparation est vendue au client dans le cadre de son devis).
- **Un service de réparation sans pièce (main d'œuvre seule)** a un prix fixe connu à l'avance et ne touche **jamais** le stock.
- **Un devis/facture de prestation peut donc combiner deux natures de lignes** : des frais de service à prix fixe (aucun impact stock) et des pièces vendues via la mini-boutique (qui décrémentent le stock).
- **Le Market Space garde son propre stock**, totalement distinct de celui d'un garage : une boutique Market Space a sa propre inscription, sa propre validation KYC et son propre inventaire, indépendamment de tout garage — y compris quand un garage détient les deux comptes (règle 2).

### Authentification Automobiliste : email/mot de passe + Google (ajout v0.5, 2026-09-16)

En complément de l'email/mot de passe existant, un automobiliste peut se connecter via **Google (OAuth2)**. Précisions à respecter dans toute implémentation :

- **Réservé au compte Automobiliste** : seul ce type de compte peut utiliser la connexion Google. Les comptes Garagiste, Market Space et Admin restent exclusivement en email/mot de passe — pas de Google pour l'instant (accès dashboard web plus sensible, KYC déjà nominatif).
- **Les deux méthodes coexistent** pour un automobiliste : un compte créé via Google doit pouvoir, à terme, définir un mot de passe (et inversement, un compte créé par email/mot de passe doit pouvoir lier son compte Google) — pas de méthode exclusive one-shot.
- **Rattachement par email** : si l'email renvoyé par Google correspond à un compte automobiliste existant, la connexion Google s'y rattache (pas de doublon de compte). Si l'email correspond à un compte **professionnel** (Garagiste/Market Space/Admin), la connexion Google est refusée — elle ne doit jamais créer ou détourner un compte professionnel.
- **Flux mobile natif, pas de redirection navigateur** : l'app Flutter effectue elle-même le Google Sign-In côté client (SDK natif) et transmet un jeton au backend pour vérification — cohérent avec l'architecture API REST pure (§4) : le backend ne sert aucune vue, donc pas de flux Socialite `redirect()/callback()` classique orienté navigateur.

### Suspension de compte professionnel (ajout v0.6, 2026-09-16)

Un compte Garagiste ou Market Space déjà validé peut être suspendu par l'administrateur, puis réactivé. Précisions à respecter dans toute implémentation :

- **Distinct du rejet d'inscription** : le rejet s'applique à un dossier KYC pas encore validé (statut `pending` → `rejected`, définitif sauf nouvelle inscription). La suspension s'applique à un compte déjà `approved` : le statut d'inscription reste `approved` pendant la suspension — c'est un état superposé, réversible, pas une remise en cause du dossier KYC.
- **Motif obligatoire** : comme pour un rejet d'inscription (règle 4/§5), la suspension exige un motif texte — traçabilité pour litiges et audit (§6).
- **Réactivation** : l'administrateur peut lever la suspension à tout moment, sans motif requis. Le compte redevient immédiatement visible côté mobile, sans reconstruire quoi que ce soit — c'est pourquoi rien n'est supprimé à la suspension.
- **Effet unique : la visibilité mobile.** Un compte suspendu disparaît de l'app mobile exactement comme un compte non encore validé (recherche, fiche garage/boutique, mini-boutique et produits Market Space, services de réparation). Rien d'autre n'est modifié par la suspension elle-même : ni les données du garage/de la boutique, ni ses produits/services, ni les avis déjà laissés — tout est conservé pour permettre une reprise normale à la réactivation.
- **Accès dashboard non traité par cette règle** : la suspension ne coupe pas explicitement l'accès du professionnel à son propre dashboard (connexion, consultation) — seule la visibilité publique côté mobile est concernée. Un éventuel blocage d'accès dashboard pendant la suspension serait une extension ultérieure, à valider avant implémentation.

### Catalogue des services de réparation (ajout v0.6, 2026-09-16)

Résout le point ouvert « typologie précise des services » (§7) : un service de réparation proposé par un garage suit les règles suivantes.

- **Catégorie fixe, pas de texte libre** : un garagiste choisit une catégorie dans une liste prédéfinie par la plateforme (Entretien courant, Freinage & suspension, Pneumatiques, Électricité & électronique, Climatisation & refroidissement, Carrosserie, Diagnostic & contrôle, Autre/Divers) — il ne peut pas en créer une nouvelle. Toute évolution de cette liste est un changement de code (déploiement), pas une action d'administration courante.
- **Prix fixe unique, pas de variation par véhicule** : un service a un seul prix. Un garagiste qui veut différencier par type de véhicule crée plusieurs services distincts (ex. « Vidange citadine » / « Vidange 4x4 ») plutôt qu'une grille tarifaire sur un même service.
- **Toujours rattaché à un Garage, jamais au Market Space** : contrairement au produit (§ mini-boutique), un service de réparation n'est jamais polymorphe — le Market Space ne fait pas de réparation, seulement de la vente de pièces.
- **Même mécanisme de validation admin que les produits** (règle 5) : `pending` à la création et à toute modification du contenu (nom, description, catégorie, prix, durée, image), visible côté mobile uniquement si `approved`.
- **Disponibilité distincte de la validation admin** : un interrupteur actif/inactif, géré librement par le garagiste, permet de rendre un service temporairement indisponible sans le supprimer ni redéclencher de validation admin. Un service invisible côté mobile si validé mais inactif, ou si le compte du garage est non validé/suspendu.

### Module Rendez-vous (ajout v0.7, 2026-09-16)

Premier maillon de la chaîne RDV → devis → validation du devis par le client → prestation → paiement → facture (règle 10). Ce module ne couvre que la partie RDV jusqu'à confirmation ; devis/validation/paiement/facture sont des modules séparés à venir.

- **Demande** : un automobiliste choisit un garage, propose une date/heure, et précise son besoin via un service du catalogue **et/ou** une description libre — au moins l'un des deux est obligatoire. Seul un garage validé et non suspendu peut être sollicité ; si un service précis est choisi, il doit appartenir à ce garage et être lui-même publié (`approved`, actif).
- **Pas de créneaux prédéfinis en V1** : la date/heure est une simple proposition/confirmation en texte libre, pas un système de disponibilités structuré.
- **Aucun paiement déclenché par la prise de RDV** (règle 10, rappel explicite) — le module Devis, à construire ensuite, est le premier point de la chaîne où un montant apparaît.
- **Statuts** : `pending` (en attente) → `confirmed` (confirmé par le garagiste), `rejected` (refusé), ou `rescheduled` (contre-proposition du garagiste) ; `rescheduled` → `confirmed` (le client accepte la nouvelle date) ou `cancelled` (le client annule plutôt que de « refuser » la contre-proposition — ça referme la négociation sans état supplémentaire). `cancelled` est aussi accessible depuis `pending`/`confirmed` (annulation client). `completed` est prévu dans le modèle pour anticiper la suite de la chaîne (prestation terminée) mais n'est pas encore atteignable dans ce module — sa transition sera déclenchée par le futur module Devis/Prestation.
- **Le garagiste agit uniquement depuis `pending`** : confirmer, refuser (motif optionnel, à la différence des rejets admin qui l'exigent), ou proposer une autre date. Pas de contre-proposition en chaîne dans cette V1 (un seul aller-retour).
- **Anticipation du futur devis** : le module Devis référence le RDV via une FK `appointment_id` optionnelle sur son propre modèle (`Appointment` n'a rien à porter en anticipation — la relation se construit dans l'autre sens). *Mise à jour v0.9 : le RDV confirmé n'est plus une condition obligatoire pour créer un devis — voir « Module Commande, RDV optionnel pour le devis, et compte express » ci-dessous.*
- **Suspension du compte garage** : un RDV déjà créé n'est pas annulé automatiquement si le garage est suspendu entre-temps (l'historique est conservé, cf. suspension de compte) — mais un automobiliste ne peut plus en demander de nouveau tant que le garage reste suspendu.

### Module Devis/Facture (ajout v0.8, 2026-09-16)

Deuxième maillon de la chaîne RDV → devis → validation → prestation → paiement → facture (règle 10). Un devis est créé par le garagiste pour un Garage + un Client — un seul devis par RDV quand ce RDV existe, la renégociation crée de nouvelles versions au sein de ce même devis, jamais un nouveau devis. *Mise à jour v0.9 : le RDV n'est plus une condition obligatoire pour créer un devis, voir plus bas.*

- **Lignes d'un devis** : frais de diagnostic (optionnel, prix libre saisi par le garagiste — pas de catalogue), lignes de service (catalogue Services du garage) et lignes de pièces (mini-boutique du garage). Le prix et le libellé d'une ligne service/produit ne sont **jamais** acceptés depuis le client : ils sont relus depuis le catalogue au moment de l'ajout de la ligne et figés (snapshot) — un devis déjà envoyé ne doit jamais changer de contenu si le catalogue évolue ensuite. Vendre plus de pièces que le stock disponible est refusé dès la constitution du devis.
- **Document PDF réel** (dompdf, rendu d'une vue Blade interne jamais servie en HTTP — cohérent avec l'API REST pure du §4) : l'en-tête (garage, client) est lu depuis les relations `Quote→Garage`/`Quote→User` en direct au moment de la génération (mise à jour v0.9 : directement sur le devis, plus besoin de passer par le RDV), **jamais dupliqué en base**. Les lignes, elles, sont un instantané figé (voir ci-dessus) — un document financier ne doit pas changer rétroactivement.
- **Statuts** : `draft` (brouillon, lignes modifiables) → `sent` (envoyé, PDF généré, notifié via le chat) → `accepted` (validé par le client, décrémente le stock des lignes de pièces) ou `rejected` (refusé) → si refusé, le garagiste peut créer une nouvelle version (`negotiating` une fois cette version envoyée) → `accepted` ou `rejected` à nouveau. Depuis `accepted` : `in_progress` (prestation démarrée) → `invoiced` (payé, facture générée). Depuis `rejected` : `abandoned` (négociation infructueuse, clôture sans prestation ni facture) au lieu de renégocier.
- **Validation = action digitale explicite, jamais le chat** : le client dispose d'un endpoint « Accepter » et d'un endpoint « Refuser », rattachés précisément à la version du devis concernée (pas juste « le dernier devis reçu ») — toute décision est tracée (`decided_by`, `decided_at`). Le chat ne sert qu'à transmettre le PDF et notifier, jamais à interpréter une validation.
- **Paiement manuel V1** : en l'absence d'agrégateur de paiement en ligne (§7), le garagiste marque lui-même la prestation comme payée (couvre aussi le paiement en espèces sur place). Ça transforme automatiquement le devis accepté en facture : même contenu (lignes copiées), nouveau PDF avec la mention « Facture », renvoyé via le chat. Ce mécanisme sera étendu par un vrai module de paiement en ligne plus tard, sans changer cette logique de transition.
- **Clôture du RDV, deux issues distinctes** (utile pour les statistiques agrégées, §1) : prestation facturée (`Appointment.status = completed`, `Quote.status = invoiced`) vs négociation infructueuse (`Appointment.status = completed`, `Quote.status = abandoned`). La distinction n'est jamais dupliquée sur `Appointment` : elle se lit via `Appointment::wasCompletedWithService()`, dérivée du statut du devis lié.

### Module Chat (ajout v0.8, 2026-09-16)

Une conversation regroupe **tous** les échanges entre un garage et un automobiliste donnés, indépendamment de tout RDV précis — un automobiliste peut contacter un garage à tout moment, y compris sans RDV (panne d'urgence). Un seul canal par paire (contrainte unique `garage_id`+`user_id`), jamais de messagerie de groupe, jamais de contact entre automobilistes ou entre garages. **Portée initiale : la paire Garage ↔ Automobiliste ; étendue au Market Space ↔ Automobiliste (ajout v0.22, 2026-09-21)** — `Conversation` est polymorphe (`sellable_type`/`sellable_id`, comme `Product`/`Order`), avec une contrainte unique sur (vendeur, automobiliste) : un automobiliste a une conversation distincte avec chaque garage et chaque boutique. Mêmes endpoints/écrans dans le dashboard Market Space, et `POST /mobile/conversations` accepte `garage_id` **ou** `market_space_account_id` (exactement l'un des deux).

- **Messages texte et/ou image** (photo véhicule/tableau de bord/voyants pour diagnostic à distance) — au moins l'un des deux requis par message.
- **Message système automatique** à chaque génération de devis/facture (`sent()`/`markPaid()` du module Devis) ou de facture de commande (`markPaid()` du module Commande, ajout v0.9) : posté sans intervention humaine (`sender_id` null), avec le PDF en pièce jointe référencée (pas de copie du fichier — le téléchargement passe par l'endpoint dédié, gated par l'appartenance au devis/à la commande).
- **Stockage des images** : disque privé dédié (`private_media_disk`, distinct du disque KYC) — jamais d'URL publique, toujours un téléchargement authentifié qui vérifie l'appartenance à la conversation, même mécanisme que pour les justificatifs KYC.
- **Pas de conversation initiée côté garagiste dans cette V1** : le garagiste voit et répond aux conversations déjà ouvertes par des automobilistes ; il n'y a pas d'action « démarrer une conversation avec tel client » côté dashboard Garagiste (pas de liste de clients à cibler dans le périmètre actuel).

### Module Commande, RDV optionnel pour le devis, et compte express (ajout v0.9, 2026-09-17)

**Rappel de mission** : l'objectif du projet est de vulgariser et d'assainir le secteur de la mécanique au Bénin et en Afrique de l'Ouest en rendant chaque prestation traçable et évaluable (avis clients), pour créer une concurrence saine et pousser les garages à bien travailler (§1). C'est pourquoi même un client « walk-in » sans app doit être digitalisé — c'est la donnée qui fait la valeur de la plateforme. Cette section introduit trois évolutions liées entre elles pour couvrir ce cas.

**Module Commande (achat isolé de pièces/produits)** — troisième maillon indépendant, parallèle à la chaîne RDV → devis (règle 11) :

- Un automobiliste peut acheter directement une pièce/produit sans devis, depuis la mini-boutique d'un Garage ou depuis un Market Space, dès lors qu'**aucune prestation de service n'est associée** — le prix est déjà fixé et connu au catalogue. Dès qu'une prestation de réparation/diagnostic est nécessaire, c'est toujours un devis (voir plus bas), jamais une commande.
- Modèle `Order`/`OrderLine`, même principe de mutualisation que `Product` (§5, ajout v0.4) : relation polymorphe `sellable` vers `Garage` ou `MarketSpaceAccount`, mêmes services de commande/paiement pour les deux. Toutes les lignes d'une commande doivent provenir du même vendeur (pas de panier mixte Garage + Market Space).
- **Séquence stricte** (règle 11) : commande → paiement immédiat → facture auto-générée. Pas de négociation, pas de statut « en attente de validation client » — contrairement au devis, le prix est fixé au catalogue au moment de l'achat.
- **Paiement manuel V1** (même mécanisme que le devis, §7) : le vendeur (garagiste ou compte Market Space) marque lui-même la commande comme payée. Ça décrémente le stock (même mécanisme unique de décrément que pour les devis, §5 ajout v0.4) et génère la facture PDF (dompdf, même mécanisme que le devis : en-tête vendeur/client lu en direct, jamais dupliqué).
- **Chat** : la facture d'une commande payée est postée en message système dans la conversation avec l'automobiliste, pour un garage comme pour un Market Space (ajout v0.22 — le Market Space n'avait pas de chat avant).

**RDV optionnel pour le devis** — correction d'une règle trop stricte posée en v0.7/v0.8 :

- La vraie règle : dès qu'il y a une prestation de service (réparation, diagnostic) à réaliser, un devis est toujours nécessaire, **avec ou sans RDV préalable** — que le client ait pris RDV via l'app, ou qu'il se présente directement au garage sans RDV (panne, urgence). Le RDV reste utile comme moyen de planification quand c'est possible, mais n'est plus une condition technique obligatoire pour créer un devis.
- **Structure retenue** : `Quote` référence désormais directement `garage_id` et `user_id` (colonnes obligatoires) ; `appointment_id` devient une FK **nullable**, lien de traçabilité optionnel plutôt qu'une condition (contrainte unique conservée : un RDV donné n'a jamais plus d'un devis). Quand un devis naît d'un RDV, celui-ci doit rester `confirmed` et appartenir au même garage/client (cohérence conservée) ; sans RDV, le garagiste doit fournir un `client_id` valide (compte automobiliste existant ou compte « express », voir plus bas).
- **Aucun affaiblissement de la validation client** : tout devis reste soumis à une validation explicite et digitale du client (accepter/refuser) avant démarrage de la prestation — cette règle s'applique identiquement avec ou sans RDV, et même pour un client « compte express » (via le mécanisme email ci-dessous).
- Un achat isolé de pièce/produit **sans aucune prestation** ne passe jamais par un devis : toujours via le module Commande ci-dessus.

**Création de compte « express » par le garagiste** (client présent physiquement, sans app ni compte) :

- Le garagiste renseigne nom, email et téléphone pour créer un compte automobiliste minimal (`is_express = true`). Colonne `email` de `users` rendue **nullable en base** (ouvre la voie à de futurs flux sans email, §7), mais **obligatoire dans la validation métier de ce flux précis** ; l'inscription classique automobiliste n'est pas concernée par cet assouplissement.
- **Rattachement plutôt que doublon** : si l'email ou le téléphone correspond à un compte automobiliste déjà existant, le garagiste est rattaché à ce compte. Si l'email/téléphone correspond à un compte **professionnel** (Garagiste/Market Space/Admin), la création est refusée — même logique de protection que pour la connexion Google (§5, ajout v0.5) : ce mécanisme ne doit jamais créer ou détourner un compte professionnel.
- **Validation par email pour ce client sans app** : quand un devis est envoyé à un client « compte express », en plus du message système dans le chat, un email est envoyé (Laravel Mail, driver SMTP — Mailtrap en dev, tout fournisseur SMTP en prod par simple changement de `.env`, aucune dépendance Composer supplémentaire). Cet email contient **le PDF du devis en pièce jointe** et **deux boutons visuels** (mise en forme HTML/CSS inline, pas de simples liens texte) « Accepter le devis » / « Refuser le devis », chacun pointant vers son lien signé (`URL::temporarySignedRoute`, 7 jours, action unique par version). Cette décision reste une action digitale explicite et tracée (`decided_by`, `decided_at`), exactement comme depuis l'app — seul le canal diffère.
- **Réponse JSON brute pour l'instant — compromis temporaire assumé, pas la cible finale** : faute de frontend Vue existant, ces routes publiques (`/api/quotes/{quote}/versions/{version}/email-decision/accept|reject`, protégées par signature+expiration, pas de Sanctum) renvoient une réponse JSON brute plutôt qu'une page de confirmation — cohérent avec l'API REST pure (§4), mais le client verra du JSON brut dans son navigateur en cliquant. **Point ouvert (§7)** : une fois le frontend Vue construit, ces liens email devront rediriger vers une vraie page Vue (confirmation visuelle claire pour le client), qui appellera ces mêmes endpoints API en arrière-plan — aucun changement côté backend requis, seule l'URL cible des boutons de l'email changera.
- Le garagiste peut aussi télécharger le PDF du devis/de la facture directement depuis son dashboard pour le remettre en main propre, en complément de l'email.
- **Point ouvert (§7)** : le mécanisme permettant à un client de « réclamer » plus tard son compte express (téléchargement de l'app, définition d'un mot de passe, finalisation de l'inscription) n'est pas construit — seul le champ `is_express` existe pour l'anticiper.

### Module Avis/Notation (ajout v0.10, 2026-09-17)

Instaure le climat de confiance visé par le projet (§1) : un avis n'est possible qu'après une prestation ou un achat réellement conclu, jamais avant, pour que la note reflète une expérience vécue plutôt qu'une opinion a priori.

- **Éligibilité stricte** (règle 7) : un automobiliste ne peut laisser un avis que sur un devis **facturé** (`Quote.status = invoiced`) ou une commande **payée** (`Order.status = paid`) dont il est le client — jamais sur un devis/une commande en cours, refusé, abandonné ou appartenant à quelqu'un d'autre.
- **Un seul avis par transaction terminée** : contrainte unique en base sur la transaction (le devis ou la commande) — un même devis facturé ou une même commande payée ne peut recevoir qu'un avis. Un même client peut en revanche laisser plusieurs avis distincts pour plusieurs transactions différentes avec le même garage/boutique, chacune reflétant une expérience différente.
- **Cible de l'avis** : dérivée automatiquement de la transaction, jamais transmise par le client — le garage pour un devis (`Quote.garage`), le vendeur (Garage ou Market Space) pour une commande (`Order.sellable`, relation polymorphe déjà mutualisée entre les deux — voir « Mini-boutique Garage vs Market Space »).
- **Composition** : une note obligatoire (1 à 5 étoiles) et un commentaire texte optionnel.
- **Aucune validation admin préalable** : contrairement aux comptes/services/produits (règle 5), un avis est visible publiquement dès sa création — seule une modération a posteriori peut le retirer de la vue publique.
- **Note moyenne exposée côté recherche** : chaque Garage/Market Space expose sa note moyenne et son nombre d'avis (calculés sur les seuls avis visibles) dans les listings et fiches consultés par l'app mobile — utile au client pour choisir un garage/boutique en fonction de sa réputation, comme visé par le projet (§1).
- **Modération admin = masquage logique tracé, jamais une suppression SQL** : l'administrateur peut masquer un avis jugé abusif ou diffamatoire, avec un motif texte obligatoire — même logique que le rejet d'inscription ou la suspension de compte (§5, ajouts existants). Un avis masqué disparaît des listes publiques (mobile) mais reste conservé en base avec la trace de la modération (motif, auteur, date), précisément pour permettre d'auditer cette modération elle-même et éviter un abus de ce pouvoir. Un garage/boutique ne peut jamais masquer ses propres avis négatifs — cette action n'existe que côté Admin.
- **Lecture seule côté professionnel** : le garage/la boutique concerné consulte l'intégralité de ses avis reçus (y compris ceux déjà masqués, par transparence sur son propre historique) mais ne dispose d'aucun endpoint pour les modifier ou les supprimer.

### Module Réclamations/Litiges (ajout v0.11, 2026-09-17)

Deuxième mécanisme de confiance après les avis (§5, ajout v0.10) : un canal formel pour qu'un automobiliste conteste une prestation/un achat précis et obtienne une décision motivée de l'administrateur, sans devoir passer par le chat (qui n'est ni tracé pour l'arbitrage, ni scopé Admin↔Automobiliste).

- **Éligibilité identique au module Avis** : une réclamation est toujours rattachée à une transaction terminée précise — un devis **facturé** ou une commande **payée** dont l'automobiliste est le client — jamais à une transaction en cours ou appartenant à quelqu'un d'autre. Contrairement aux avis, plusieurs réclamations peuvent viser la même transaction (pas de contrainte d'unicité : un litige peut nécessiter plusieurs échanges/relances).
- **Cible dérivée automatiquement** de la transaction, jamais transmise par le client — même mécanisme que le module Avis (le garage pour un devis, le vendeur Garage/Market Space pour une commande).
- **Composition** : un motif/description obligatoire, avec possibilité de joindre jusqu'à 5 photos en preuve — réutilise le disque privé dédié aux médias déjà en place pour les images du chat (`private_media_disk`, téléchargement toujours authentifié, jamais d'URL publique — CLAUDE.md §5, ajout v0.8).
- **Cycle de vie** : `submitted` (déposée) → `under_review` (en cours d'instruction, atteint dès qu'une réponse est demandée par l'admin ou envoyée par le professionnel) → `resolved_founded` ou `resolved_rejected` (décision motivée de l'admin) → `closed` (clôture explicite du dossier par l'admin, un pas distinct de la décision elle-même — pas de clôture automatique).
- **Espace d'échange dédié entre l'admin et le professionnel** (distinct du chat Garage↔Automobiliste, dont la portée reste limitée à cette paire — ajout v0.8) : l'admin peut demander une réponse/défense (optionnel — il peut aussi trancher directement si les preuves jointes suffisent), le professionnel y répond ; les deux peuvent s'y exprimer à plusieurs reprises avant la décision.
- **Instruction avec accès à tout l'historique lié** : la fiche admin d'une réclamation embarque la transaction concernée, la conversation de chat Garage↔Automobiliste associée (quand elle existe) et les avis déjà laissés sur le professionnel visé — tout ce qui permet de juger du bien-fondé sans naviguer entre plusieurs écrans.
- **Décision finale motivée et tracée, comme les autres actions de modération** (rejet d'inscription, suspension, masquage d'avis) : `rejected` (classée sans suite, motif obligatoire) ou `resolved_founded` (motif obligatoire **et** action choisie librement par l'admin selon la gravité qu'il évalue — **aucune sanction automatique**). Deux actions possibles pour une réclamation fondée :
  - **`suspension`** : réutilise tel quel le mécanisme de suspension de compte déjà construit (`ProfessionalRegistrationService::suspend`, §5 ajout v0.6) — mêmes garanties (réversible, motif tracé, historique conservé).
  - **`warning`** : avertissement formel sans suspension — le dossier de réclamation lui-même (motif, décision, action) **est** la trace de cet avertissement ; aucune structure séparée n'est nécessaire.
- **Notification du client** : l'automobiliste suit le statut de ses réclamations depuis l'app (liste + détail) et reçoit un email à la décision finale (fondée ou rejetée) — email silencieusement ignoré si son compte n'a pas d'email (cas rare d'un compte express non encore réclamé, §5 ajout v0.9). Depuis l'ajout v0.12, une notification push accompagne systématiquement cette décision (dépôt de la réclamation compris, côté professionnel) ; en l'absence de configuration FCM elle reste simulée, l'email demeure donc le canal réellement livré en attendant.
- **Lecture seule côté professionnel, hors réponse** : le garage/la boutique concerné consulte les réclamations le visant et peut répondre dans l'espace d'échange dédié, mais ne dispose d'aucun endpoint de décision — cette action reste exclusivement Admin, comme pour la modération des avis.

### Module Notifications push (ajout v0.12, 2026-09-17)

FCM n'est pas encore configuré (§7, point ouvert) : ce module suit la même logique que le paiement manuel V1 (§5, ajout v0.8/v0.9) — la structure interne est complète et fonctionnelle, l'envoi réel est simulé tant que la configuration manque, sans erreur bloquante.

- **Modèle de notification** (`PushNotification`, table `push_notifications`) : destinataire, type d'événement (catalogue fermé, changement de code pour en ajouter un — même principe que `ServiceCategory`), titre, contenu, données de deep-link, statut, horodatages. Nommé "Push*" et pas "Notification" tout court pour ne jamais entrer en collision avec le système de notifications intégré de Laravel (trait `Notifiable` de `User`, déjà présent mais inutilisé côté notifications base de données) — les deux restent indépendants.
- **Statut à trois valeurs** : `created` (créée) → `sent` (envoyée) ou `failed` (échouée). Tant que FCM n'est pas configuré, une notification reste `created` indéfiniment — c'est aussi bien le statut initial que le statut "en attente d'envoi réel" : aucune tentative d'envoi n'est faite sans clé FCM, donc jamais d'échec artificiel. Dès qu'une clé est renseignée (`FCM_SERVER_KEY`), l'envoi réel est tenté pour toute nouvelle notification et bascule vers `sent`/`failed` selon le résultat — aucun code à changer ailleurs que la configuration.
- **Jetons d'appareil** (`DeviceToken`, table `device_tokens`) : un utilisateur peut avoir plusieurs appareils : un jeton donné n'appartient jamais qu'à un seul compte à la fois (unique sur `token` seul) — une connexion avec un autre compte sur le même appareil réassigne le jeton au lieu d'en dupliquer un. Mis à jour à chaque connexion via l'endpoint dédié.
- **Service centralisé** (`PushNotificationService`) : chaque service métier déjà en place (RDV, devis, chat, inscription pro, réclamation, commande, produit) appelle une méthode `notifyXxx()` dédiée plutôt que de construire lui-même le contenu — même principe de centralisation que `ChatService` pour les messages système. Événements couverts : RDV (nouvelle demande, confirmé, refusé, reprogrammé), devis (envoyé, accepté, refusé, facturé), nouveau message de chat (humain uniquement — un message système de génération de devis/facture est déjà couvert par sa propre notification dédiée, pas de doublon sur le même événement), validation/rejet d'inscription, suspension/réactivation de compte, réclamation (déposée, décidée), changement de statut de commande, stock bas, nouveau produit publié.
- **Nouveau produit publié = anti-spam volontaire** : ne notifie jamais tous les automobilistes de la plateforme — uniquement ceux ayant déjà au moins une transaction terminée (devis facturé ou commande payée) avec ce garage/cette boutique précis. Déclenché à la validation admin du produit (moment où il devient réellement visible), pas à sa création. Piste d'amélioration future : cibler aussi les clients à proximité géographique, maintenant que le module Recherche géolocalisée (ajout v0.13, ci-dessous) fournit le calcul de distance nécessaire.
- **Alerte de stock bas** : seuil optionnel par produit (`low_stock_threshold`), pas de valeur par défaut imposée — sans seuil configuré, jamais d'alerte automatique. Voyage avec l'endpoint de correction de stock (`PUT .../products/{product}/stock`), pas avec la mise à jour de contenu : ce n'est pas une donnée qui remet en cause la validation admin déjà accordée, même principe que `stock_quantity`. Ne se déclenche qu'à la vente (décrément de stock, mécanisme unique — §5 ajout v0.4), jamais sur une correction manuelle. Une seule alerte jusqu'à ce que le stock remonte strictement au-dessus du seuil (`low_stock_alert_sent_at` réarmé à ce moment-là) puis redescende à nouveau — pas de spam au vendeur à chaque vente supplémentaire sous le seuil.
- **Endpoints Mobile/Garage/Market Space** (mêmes trois routes dans chaque espace) : liste de ses propres notifications, marquage comme lue (idempotent, appartenance vérifiée), enregistrement/mise à jour du jeton FCM de l'appareil courant.

### Module Recherche géolocalisée (ajout v0.13, 2026-09-17)

Clôt le point ouvert §7 sur le choix d'un prestataire de cartographie, en le recadrant : **ce module de recherche par proximité ne nécessite et n'utilise aucun service de cartographie externe** (Google Maps, Mapbox, OpenStreetMap...) — uniquement un calcul de distance sur les coordonnées déjà stockées (`Garage.latitude/longitude`, `MarketSpaceAccount.latitude/longitude`). Un tel service ne redevient un point ouvert que pour l'**affichage visuel sur une carte**, un sujet strictement frontend (Vue/Flutter) à traiter plus tard, sans aucune dépendance à ce module backend ni changement à y apporter le moment venu. Candidat gratuit envisagé pour cet affichage futur : OpenStreetMap via `flutter_map` (mobile) / Leaflet (web), sans clé API.

- **Calcul de distance en PHP, formule de Haversine** (`GeoSearchService::distanceKm()`), pas en SQL trigonométrique : portable entre SQLite (tests) et PostgreSQL (production) sans extension particulière, et largement suffisant à l'échelle actuelle. Endpoint `GET /mobile/search/nearby`, **public** comme les listes garages/market-space-accounts existantes — un automobiliste en panne n'a pas forcément de session ouverte.
- **Entrée** : latitude/longitude, `radius_km` optionnel (par défaut `GEO_DEFAULT_SEARCH_RADIUS_KM`, 15 km — plafonné à `GEO_MAX_SEARCH_RADIUS_KM`, 100 km, même si une valeur plus grande est demandée) et `type` optionnel (`garage`/`market_space`/`both`, par défaut `both`). *Mise à jour v0.14 : latitude/longitude ne sont plus obligatoires — voir « Extension recherche » ci-dessous.*
- **Résultat unifié** (`NearbySearchResult`, garage et Market Space dans la même liste triée) : type, id, nom, adresse (le champ `address` existant tel quel — le modèle de données n'a pas de notion structurée d'adresse "courte" distincte, pas d'invention d'un nouveau format), photo principale (première image du profil, position la plus basse), distance en km (nullable depuis v0.14 — null si aucune position n'est transmise), note moyenne + nombre d'avis (avis visibles uniquement, même logique que les listes existantes — §5, ajout v0.10), et statut "ouvert maintenant".
- **Statut "ouvert maintenant" à trois états** (`HasOpeningHours::isOpenNow()`, trait mutualisé entre `Garage` et `MarketSpaceAccount` — même mutualisation de principe que `Product` entre les deux) : `true`/`false` à partir des horaires déjà enregistrés (§5, ajout v0.7) comparés à l'heure actuelle, ou **`null`** si le professionnel n'a pas encore configuré d'horaire pour le jour courant (distinct de "fermé" : absence d'information, pas fermeture constatée).
- **Fuseau horaire métier dédié** (`config('geo.business_timezone')`, `Africa/Porto-Novo` par défaut — les horaires sont saisis en heure locale sans fuseau) plutôt que le fuseau serveur (`UTC` par défaut, §4) : sans ça, "ouvert maintenant" serait décalé d'une heure pour un serveur en UTC. Valeur unique en V1 (Bénin) ; une extension à d'autres pays d'Afrique de l'Ouest à fuseau différent (§1) nécessitera de stocker le fuseau par garage/boutique plutôt qu'une constante globale — nouveau point ouvert.
- **Filtre de visibilité mutualisé** (`scopePubliclyVisible()` sur `Garage`/`MarketSpaceAccount`, même filtre qu'`isPubliclyVisible()` mais utilisable dans une requête de liste) : seuls les comptes approuvés et non suspendus sont inclus, comme pour les listes garages/market-space-accounts existantes — réutilisé par ces deux listes pour éliminer la duplication qui existait avant ce module.
- **Numéro de téléphone déjà exposé** dans la fiche détaillée garage/Market Space (`phone` sur `GarageResource`/`MarketSpaceAccountResource`) — vérifié à l'occasion de ce module, aucun ajout nécessaire : permet déjà à l'automobiliste d'appeler directement en cas de panne urgente.
- **Performance à l'échelle actuelle** (§6, exigence < 2-3s) : le calcul se fait en mémoire sur l'ensemble des professionnels approuvés (pas de filtrage géographique au niveau SQL), ce qui reste largement suffisant pour le nombre de garages/boutiques d'un pays en V1. À revoir (index géospatial type PostGIS/earthdistance côté PostgreSQL) si le volume de professionnels grandit significativement.

### Extension recherche : nom, service, tri (ajout v0.14, 2026-09-17)

Complète le module Recherche géolocalisée (§5, ajout v0.13) sur la même route `GET /mobile/search/nearby`, sans nouvel endpoint : recherche par nom, filtre par service proposé, et choix du tri — combinables avec le filtre géographique existant, mais utilisables aussi indépendamment de lui.

- **Position désormais optionnelle** : latitude/longitude ne sont plus obligatoires — un automobiliste peut chercher par nom et/ou par service sans position transmise. Seuls le rayon (`radius_km`) et le tri par distance (`sort=distance`) restent impossibles sans position et l'exigent en validation (422 sinon) : ils n'ont pas de sens sans elle.
- **Recherche par nom** (`name`, partielle) : insensible à la casse **et aux accents**, sur `Garage.name`/`MarketSpaceAccount.name`. Comparaison faite en PHP plutôt qu'en SQL `LOWER()`, dont le comportement sur les caractères accentués n'est pas fiable sous SQLite (tests) — même choix de portabilité que le calcul de distance (§5, ajout v0.13), à l'échelle actuelle du volume de professionnels (§6). *Mise à jour v0.15 : la comparaison passe par une translittération ASCII (ext-intl/ICU, `GeoSearchService::normalizeForSearch()`) plutôt que `mb_stripos()` seul, pour un vrai repli sur les accents (ex. « ETOILE » retrouve « Étoile ») — `iconv(..., 'ASCII//TRANSLIT', ...)` a été écarté car son support de la translittération dépend de l'implémentation d'iconv du système (ex. musl/Alpine), donc moins portable qu'ICU. Même comparaison désormais réutilisée par le filtre `product_name` ci-dessous.*
- **Filtre par service proposé** (`service_category` ou `service_id`) : restreint aux garages ayant un service approuvé et actif correspondant — jamais aux Market Space, qui ne font pas de réparation (§5, ajout v0.6). Sa présence exclut donc toujours les Market Space du résultat, même avec `type=both` ou `type=market_space` (résultat alors simplement vide dans ce dernier cas).
- **Tri au choix du client** (`sort`) : `distance` (nécessite une position) ou `rating` (note moyenne décroissante — avis visibles uniquement, §5 ajout v0.10 — professionnels non notés relégués en dernier, égalité départagée par nom pour un ordre déterministe). Par défaut `distance` si une position est fournie, sinon `rating`.
- **Tous les critères se combinent** : nom, service, rayon et tri fonctionnent ensemble sur la même requête, chaque filtre s'appliquant indépendamment des autres.
- **Pas d'écran "urgence" dédié côté backend** : le raccourci "panne/urgence" prévu côté app mobile est un choix d'UI frontend, pas un nouvel endpoint — le frontend appelle cette même route avec `sort=distance`, puis n'affiche que les résultats dont `is_open_now` vaut `true` (champ déjà exposé par résultat, §5 ajout v0.13). Aucun paramètre serveur supplémentaire n'est nécessaire pour ce raccourci ; à garder en tête pour ne pas dupliquer cette logique côté backend plus tard.

### Extension recherche : nom de produit (ajout v0.15, 2026-09-17)

Complète encore la même route `GET /mobile/search/nearby` (§5, ajouts v0.13 et v0.14), sans nouvel endpoint : recherche par nom de produit, combinable avec tous les critères déjà en place (position, nom du vendeur, service, tri).

- **Recherche par nom de produit** (`product_name`, partielle) : même comparaison insensible casse/accents que `name` (`GeoSearchService::matchesName()`, réutilisée — voir « Extension recherche : nom, service, tri » ci-dessus), appliquée cette fois aux noms de produits (`Product.name`) plutôt qu'aux noms de vendeurs.
- **Concerne les deux types de vendeurs, contrairement au filtre service** : la mini-boutique Garage et le Market Space partagent la même logique catalogue (`Product` polymorphe — §5, ajout v0.4), donc `product_name` retourne aussi bien des Garages que des Market Space ayant au moins un produit approuvé correspondant. Il n'exclut jamais l'un ou l'autre, à la différence de `service_category`/`service_id` (ajout v0.14), qui ne peuvent jamais être satisfaits par un Market Space.
- **Seuls les produits approuvés comptent** (règle 5) : un produit `pending` ou `rejected` ne fait jamais correspondre son vendeur, même si le nom correspond — cohérent avec le reste de la plateforme (rien de non validé n'est visible côté mobile).
- **Résultat enrichi** : chaque résultat de vendeur porte désormais `matched_products` (tableau d'objets `id`/`name`/`price`) — les produits approuvés de ce vendeur qui correspondent à `product_name`, pour que le client voie directement ce qu'il cherche sans ouvrir chaque fiche. Toujours vide (`[]`) hors de ce filtre, jamais `null`, pour une forme de réponse stable.
- **Combinable avec tous les autres critères** : `product_name` + `name` + position/rayon + tri fonctionnent ensemble sur la même requête, chaque filtre s'appliquant indépendamment.

### Extension recherche : tranche de prix sur les produits (ajout v0.16, 2026-09-17)

Complète encore la même route `GET /mobile/search/nearby` (§5, ajouts v0.13 à v0.15), sans nouvel endpoint : `min_price`/`max_price` restreignent aux vendeurs ayant au moins un produit approuvé dont le prix tombe dans la tranche demandée.

- **`min_price` et `max_price` indépendants et combinables** : chacun peut être fourni seul (tranche ouverte d'un côté) ou ensemble (tranche fermée). `max_price` doit être supérieur ou égal à `min_price` quand les deux sont fournis (422 sinon).
- **Filtré en SQL, contrairement au nom** : le prix est une comparaison numérique exacte (`Product.price >= min_price` / `<= max_price`), sans les problèmes de portabilité qui justifient de faire la comparaison de nom en PHP (§5, ajout v0.14) — `GeoSearchService::productsQuery()` applique ces bornes au chargement de la relation `products`, en plus du statut `approved`.
- **Concerne les deux types de vendeurs, comme le nom de produit** : même mutualisation Garage/Market Space que `product_name` (§5, ajout v0.15) — ce filtre n'exclut jamais l'un ou l'autre, à la différence du filtre service.
- **Combinable avec `product_name`** : quand les deux sont fournis, un produit doit satisfaire le nom **et** la tranche de prix pour compter comme correspondance — `matched_products` ne reprend alors que les produits qui remplissent les deux conditions à la fois.
- **Seuls les produits approuvés comptent**, comme pour `product_name` (règle 5) — un produit `pending`/`rejected` dans la tranche ne fait jamais correspondre son vendeur.

### Réclamation de compte express (ajout v0.17, 2026-09-17)

Ferme le point ouvert correspondant (§7) : un client « compte express » (§5, ajout v0.9) peut désormais récupérer son compte lui-même, sans intervention du garagiste.

- **Demande** (`POST /auth/express-claim`, publique) : l'automobiliste renseigne son email. S'il correspond à un compte `is_express = true`, un email est envoyé avec un lien signé (`URL::temporarySignedRoute`, 7 jours) — même mécanisme que la validation de devis par email (§5, ajout v0.9). Ré-appelable à volonté tant que le compte reste « express » (pas de compte trouvé/déjà réclamé → erreur claire, 422).
- **Une seule URL signée, deux usages** (`GET`/`POST /express-clients/{user}/claim`) : la vérification Laravel d'un lien signé ne porte que sur l'URL (chemin + query), jamais sur la méthode HTTP ni le nom de route. `GET` (le lien cliqué depuis l'email) confirme la validité du lien et renvoie une réponse JSON brute — page de confirmation laissée au futur frontend Vue, même point ouvert que la décision de devis par email (§5, ajout v0.9), sans qu'aucun second lien ne soit nécessaire. `POST`, à la même URL, définit effectivement le mot de passe (`password`/`password_confirmation`) : c'est ce que le futur formulaire Vue appellera en arrière-plan, sans changement côté backend.
- **Bascule hors « express »** : une fois le mot de passe défini, `is_express` repasse à `false` — le compte redevient un automobiliste standard, utilisable en email/mot de passe ou Google OAuth si l'email correspond (§5, ajout v0.5), sans distinction avec un compte créé classiquement.
- **Lien déjà utilisé ou compte déjà réclamé** : `409` avec message clair (« Ce compte a déjà été réclamé. Connectez-vous avec votre email et votre mot de passe. »), que ce soit sur `GET` ou `POST` — même famille de garde-fou que `QuoteService::assertVersionIsDecidable` pour une décision de devis déjà tranchée.
- **Point ouvert restant** (§7, inchangé) : la finalisation de l'inscription (nom/téléphone déjà présents, mot de passe désormais définissable) ne prévoit pas encore de flux additionnel (ex. vérification téléphone) — hors périmètre de cette fermeture, qui couvre strictement « définir un mot de passe ».

### Localisation structurée et statistiques agrégées admin (ajout v0.18, 2026-09-17)

Prépare les données agrégées pour les autorités béninoises visées par le projet (§1).

- **Localisation structurée : voir « Hiérarchie administrative » ci-dessous (ajout v0.19)**, qui remplace les champs `city`/`region` posés en v0.18 (supprimés, ainsi que les enums `City`/`Region`).
- **Endpoint `GET /admin/statistics`** (`AdminStatisticsService`), période optionnelle (`start_date`/`end_date`, `end_date` ≥ `start_date` sinon 422) filtrant uniquement le volume d'activité — tout le reste (structures, géographie, avis, réclamations) reste un instantané global, sans notion de période. Calcul en SQL/mémoire simple sur l'ensemble des données, même principe de performance-acceptable-en-V1 que `GeoSearchService` (§5, ajout v0.13) : pas d'agrégation matérialisée à ce stade.
  - **Structures par type et statut** : lu depuis `professional_registrations` (jamais depuis `Garage`/`MarketSpaceAccount`, qui n'existent qu'une fois le dossier approuvé — §5, ajout v0.6), statut dérivé `approved`/`pending`/`suspended`/`rejected` par type de compte (`garagiste`/`market_space`).
  - **Répartition géographique** par département, **séparément** pour les garages et pour les Market Space (`geography.garagiste` / `geography.market_space` — mise à jour v0.19) ; une entrée « Non renseigné » (toujours en dernier) regroupe les profils sans localisation.
  - **Volume d'activité sur la période** : nombre de RDV et de devis émis (statut au-delà de `draft`) créés dans la période, nombre de commandes créées dans la période, nombre de factures générées (devis `invoiced` + commandes `paid`, comptés sur leur date de paiement `paid_at`) et montant total facturé (somme des lignes des versions `invoice` des devis facturés + des lignes des commandes payées, dans la période). Sans bornes fournies, totaux depuis le début.
  - **Avis et réclamations** : nombre d'avis visibles et note moyenne globale de la plateforme (même filtre `visible()` que côté recherche — §5, ajout v0.10) ; nombre total de réclamations et répartition par statut (§5, ajout v0.11).

### Hiérarchie administrative béninoise (ajout v0.19, 2026-09-20)

Remplace les champs `city`/`region` (v0.18) sur les profils Garage et Market Space.

- **Quatre niveaux** : Département (12) → Commune (77) → Arrondissement (546), listes fermées dépendantes du niveau parent, puis **Quartier en texte libre** (`neighborhood`, trop variable pour une liste). Colonnes `department_id`, `commune_id`, `arrondissement_id` (FK nullables) + `neighborhood` sur `garages` et `market_space_accounts`, mutualisées par le trait `HasAdministrativeLocation`. *Mise à jour v0.20 : ces champs sont désormais obligatoires pour considérer le profil complet — voir « Profil complet obligatoire » ci-dessous.*
- **Données de référence en base** (tables `departments`, `communes`, `arrondissements`, slug unique dans son parent), pas en enums : chargées par `LocationSeeder` (idempotent, upserts groupés) depuis `database/data/benin_locations.php`, appelé par la migration pour que la production les reçoive sans `db:seed`. L'extension à d'autres pays (§1) passera par une table `countries` + FK, sans changement de code métier.
- **Source des données** : paquet npm `decoupage-territorial-benin` (MIT, dérivé de « leplutonien », pas un fichier INSAE direct), vérifié commune par commune contre la liste Wikipédia/INSAE (mêmes effectifs : 77 communes, 546 arrondissements ; la mention « 545 » de Wikipédia est périmée). Noms en casse titre **sans accents** (choix validé) — corrigeable par simple mise à jour du fichier de données. À Natitingou, `Peporiyakou` (source) = `Natitingou IV` (Wikipédia) : à confirmer.
- **Validation** (`ValidatesAdministrativeLocation`) : chaque niveau exige son parent et doit lui appartenir (422 sinon). La localisation forme un bloc : dès qu'un niveau est envoyé, les niveaux absents sont remis à null (changer de département ne conserve jamais une ancienne commune).
- **API** : `GET /locations/departments`, `/locations/departments/{id}/communes`, `/locations/communes/{id}/arrondissements` (publiques, pour les selects en cascade web et Flutter). `GarageResource`/`MarketSpaceAccountResource` exposent ids, noms (chargés en détail/profil uniquement) et quartier.
- **Migration des profils existants** : région → département (même slug), ville → commune (même slug, cohérente avec le département), le reste devient null. Aucun profil concerné en développement comme en production au moment du changement.
- **Frontend** : composant réutilisable `LocationSelect.vue` (`shared/components`), intégré à l'écran de profil (voir « Profil complet obligatoire » ci-dessous).

### Profil complet obligatoire après approbation (ajout v0.20, 2026-09-20)

**Remplace explicitement** la mention « jamais imposés à la création du profil » (ajouts v0.18/v0.19) pour tous les champs listés ci-dessous, ainsi que le caractère facultatif de la position, des horaires, des photos et du téléphone (§5, ajout v0.7 pour les horaires). Dès qu'un compte Garagiste ou Market Space est `approved`, le professionnel doit compléter son profil **intégralement** avant d'accéder à quoi que ce soit d'autre dans son espace.

- **Éléments obligatoires** (`HasProfileCompleteness`, trait partagé `Garage`/`MarketSpaceAccount`) : nom et adresse (pré-remplis depuis le KYC, doivent rester non vides), **téléphone** (pré-rempli à l'approbation depuis le téléphone saisi à l'inscription quand il existe — `users.phone`, facultatif à l'inscription, donc souvent à saisir), **latitude et longitude**, **Département, Commune, Arrondissement et Quartier** (le quartier est obligatoire malgré son statut de texte libre), **les 7 jours d'horaires** enregistrés (jour fermé accepté, mais le jour doit exister), et **au moins une photo**. La description reste facultative.
- **Source de vérité côté backend** : `missingProfileFields()` (clés des éléments manquants : noms de colonnes, plus `opening_hours` et `images`), `isProfileComplete()` et `profileStatus()` (`{ is_complete, missing_fields }`). Exposé dans `UserResource.profile_status` (login, `/auth/me`, `null` pour un rôle sans profil ou un compte pas encore approuvé) et dans `meta.profile_status` de `GET/PUT /garage|market-space/profile`.
- **Blocage côté API** : middleware `profile.complete` (`EnsureProfileIsComplete`) sur **toutes** les routes de l'espace pro **sauf** les routes de profil (`GET/PUT profile`, `PUT profile/opening-hours`, `POST/DELETE profile/images`). Réponse `403` avec `code: profile_incomplete` et la liste `missing_fields`. La déconnexion (`/auth/logout`) n'est pas concernée (hors de ces groupes). Le blocage est serveur : la redirection frontend n'est qu'un confort, jamais la protection.
- **Profil ne pouvant plus redevenir incomplet** : `PUT profile` exige désormais téléphone, latitude, longitude, les quatre champs de localisation (cohérence Département → Commune → Arrondissement conservée) ; la **dernière photo ne peut pas être supprimée** (422 sur `image`) ; les horaires exigent déjà les 7 jours. Un profil complet reste donc complet.
- **Profils existants** : un profil déjà approuvé mais incomplet est bloqué de la même façon dès le déploiement (aucun profil en production au moment du changement).
- **Visibilité mobile inchangée** : la complétude n'est **pas** (pour l'instant) une condition de visibilité publique — seule l'approbation et l'absence de suspension comptent (§5, ajouts v0.6 et v0.13). Piste ouverte : ajouter `is_profile_complete` à `scopePubliclyVisible()` pour ne jamais afficher de fiche vide.
- **Frontend** (`ProfessionalProfileView.vue`, une seule page pour les deux espaces, prop `space`) : sections Informations et localisation (avec `LocationSelect` et bouton « Utiliser ma position », géolocalisation du navigateur, pas de carte — §7), Horaires (7 jours, semaine type pré-remplie), Photos ; un bouton d'enregistrement par section (un endpoint backend chacune) et un bandeau listant ce qui manque. Routes `/garage/profile` et `/market-space/profile`. Le store `auth` porte `profile_status` (`mustCompleteProfile`), le garde de navigation redirige vers la page de profil et la barre latérale ne propose que « Mon profil » tant que le profil est incomplet ; un 403 `profile_incomplete` reçu par Axios recharge sur la page de profil (filet de sécurité). À la première complétion, retour automatique vers l'espace ; ensuite la page sert d'écran « Mon profil ». La page de test `/garage/location-test` est supprimée (remplacée par cet écran).
- **Tests** : les factories `Garage`/`MarketSpaceAccount` ont un état `complete()` (localisation, quartier, 7 jours, une photo) à utiliser dans tout test appelant une route de l'espace pro autre que le profil.

### Format du numéro de téléphone (ajout v0.21, 2026-09-20)

Tout champ `phone` saisi dans l'application doit être un numéro béninois valide, en numérotation à 10 chiffres (en vigueur depuis 2024) : **indicatif `+229` suivi de 10 chiffres commençant par `01`** (ex. `+229 01 23 45 67 89`).

- **Règle unique** : `App\Rules\BeninPhoneNumber` (pas de bibliothèque de numéros de téléphone dans le projet). Espaces, tirets, points et parenthèses de la saisie sont tolérés ; `00229` est accepté à la place de `+229` ; un numéro sans indicatif, à 8 chiffres (ancien format), ne commençant pas par 01, trop court/long ou d'un autre pays est refusé (422).
- **Forme canonique stockée** : `+229` immédiatement suivi des 10 chiffres (`+2290123456789`). Le trait `NormalizesBeninPhone` remplace la saisie par cette forme avant validation, si bien que l'unicité `users.phone` et la recherche d'un compte existant (compte express) ne dépendent jamais des espaces tapés.
- **Champs concernés** (les cinq requêtes qui acceptent un téléphone) : inscription automobiliste et inscription professionnelle (facultatif, mais valide s'il est fourni), création de compte client express, profil Garagiste et profil Market Space (obligatoire, §5 ajout v0.20).
- **Frontend** : `utils/beninPhone.ts` reproduit la règle (validation avant envoi, message clair sous le champ, envoi de la forme canonique) ; le backend reste l'autorité. Seul l'écran de profil professionnel a aujourd'hui un champ téléphone côté web (les inscriptions et l'app mobile n'existent pas encore côté interface) : les futurs écrans devront réutiliser cet utilitaire.
- **Données antérieures** : les téléphones déjà en base dans un autre format (données de test ou saisies avant cette règle) ne sont pas migrés ; ils seront refusés au prochain enregistrement du profil concerné tant qu'ils n'ont pas été corrigés.

### Image obligatoire pour un Service et un Produit (ajout v0.23, 2026-09-22)

Jusqu'ici, l'image était facultative à la création d'un service ou d'un produit. Nouvelle règle métier : **une image devient obligatoire à la création**, pour les deux domaines (Services garagiste, Produits garagiste **et** Market Space — ces derniers partagent les mêmes classes de validation, `StoreProductRequest`/`UpdateProductRequest`).

- **À la modification, l'image existante suffit** : pas besoin d'en re-uploader une à chaque édition — même logique que la photo de profil professionnel (§5, ajout v0.20), qui ne peut jamais être totalement retirée une fois le profil complet. L'image reste obligatoire à la modification uniquement si l'enregistrement n'en a encore aucune (cas des enregistrements créés avant cette règle) : c'est ce qui les fait converger vers la nouvelle règle dès qu'on y touche, sans les invalider tant qu'on n'y touche pas.
- **Aucune action « supprimer l'image » (seule)**, ni côté backend ni côté frontend : le seul moyen de changer l'image est d'en envoyer une nouvelle via le même formulaire de création/modification, qui la remplace. Il n'existe jamais de chemin qui laisse `image_path` (colonne réelle derrière le champ exposé `image_url`) redevenir `null` une fois qu'une image a été fournie une première fois.

### Matrice des droits d'accès (résumé)

| Fonctionnalité | Admin | Compte Garagiste | Compte Market Space | Automobiliste (mobile) |
|---|---|---|---|---|
| Dashboard Admin | Oui | Non | Non | Non |
| Dashboard Garagiste | Oui (lecture) | Oui | Non | Non |
| Dashboard Market Space | Oui (lecture) | Non | Oui | Non |
| Créer/modifier service garage | Oui | Oui (soumis validation) | Non | Non |
| Créer/modifier produit (mini-boutique ou Market Space) | Oui | Oui (soumis validation) | Oui (soumis validation) | Non |
| Valider service/produit/compte | Oui | Non | Non | Non |
| Suspendre/réactiver un compte déjà validé | Oui | Non | Non | Non |
| Gestion stock/inventaire (unique par garage / par boutique) | Oui (lecture) | Oui | Oui | Non |
| Génération devis/factures | Oui (lecture) | Oui (avec ou sans RDV) | Non concerné | Consultation |
| Achat isolé de pièce/produit (sans devis) | Oui (lecture) | Émission (factures, mini-boutique) | Émission (factures) | Commande + paiement |
| Créer un compte client « express » (sans app) | Non | Oui | Non | Non concerné |
| Paiement en ligne | — | Réception | Réception | Émission |
| Prise de rendez-vous | Oui (lecture) | Gestion agenda | Non concerné | Prise de RDV |
| Chat avec l'automobiliste | Non | Oui | Oui | Oui |
| Recherche (proximité, nom, service proposé, nom de produit, tranche de prix, tri — garage et/ou Market Space) | — | — | — | Oui |
| Laisser un avis/notation (transaction terminée) | Non | Non | Non | Oui |
| Consulter ses avis reçus | Oui (lecture) | Oui (lecture seule) | Oui (lecture seule) | Non concerné |
| Masquer un avis abusif (motif obligatoire) | Oui | Non | Non | Non |
| Déposer une réclamation (transaction terminée) | Non | Non | Non | Oui |
| Consulter les réclamations la/le concernant, y répondre | Oui (lecture) | Oui (lecture + réponse) | Oui (lecture + réponse) | Suivi de ses réclamations |
| Décider d'une réclamation (rejeter/fondée + action, motif obligatoire) | Oui | Non | Non | Non |
| Consulter ses notifications push, marquer comme lue | Non | Oui | Oui | Oui |
| Enregistrer/mettre à jour le jeton FCM de l'appareil courant | Non | Oui | Oui | Oui |
| Définir le seuil d'alerte de stock bas sur ses produits | Non | Oui | Oui | Non concerné |
| Notifications push (statut commande) | — | — | — | Oui |
| Consulter les statistiques agrégées (structures, géographie, activité, avis, réclamations) | Oui | Non | Non | Non |
| Réclamer un compte express (définir un mot de passe) | Non concerné | Non concerné | Non concerné | Oui (lien email) |

## 6. Exigences non fonctionnelles

- **Performance** : affichage des garages proches en < 2-3 secondes après géolocalisation.
- **Sécurité** : cloisonnement strict des accès selon la matrice ci-dessus ; sécurisation des transactions ; protection des données personnelles.
- **Fiabilité du contenu** : aucun compte, service ou produit visible publiquement sans validation admin préalable.
- **Disponibilité** : plateforme continue, sauvegardes régulières de la base de données.
- **Scalabilité** : architecture capable de supporter une extension progressive à d'autres villes/pays d'Afrique de l'Ouest.
- **Ergonomie mobile** : utilisable en conditions de connexion internet limitée.
- **Traçabilité** : historique des devis, factures, rendez-vous et avis conservé pour audit et gestion des litiges.

## 7. Points ouverts / non encore arbitrés

À garder en tête — ne pas figer de choix définitif dans le code sur ces points sans validation métier :

- Choix du prestataire de cartographie pour l'**affichage visuel sur une carte** côté frontend (Vue/Flutter) uniquement — le calcul de distance/tri par proximité côté backend ne nécessite et n'utilise aucun de ces services (§5, ajout v0.13, module Recherche géolocalisée déjà livré). Candidat gratuit envisagé : OpenStreetMap via `flutter_map` (mobile) / Leaflet (web), sans clé API — alternative à Google Maps/Mapbox si le budget/la dépendance à un compte développeur pose problème.
- Choix de l'agrégateur de paiement (Kkiapay, FedaPay, ou autre).
- Choix de la solution de chat temps réel (Reverb, Pusher, Supabase Realtime).
- Modalités de contestation d'un avis par un professionnel avant modération/suppression admin.
- Cadre légal précis de partage des données agrégées avec l'administration béninoise (nature des données, fréquence, base légale RGPD/loi locale).
- Authentification côté automobiliste par téléphone/SMS (email + Google désormais tranchés, voir §5 ajout v0.5).
- Périmètre exact du catalogue « mini-boutique » d'un garage (catégories de produits autorisées, limite de nombre éventuelle) et seuil au-delà duquel un garage devrait plutôt ouvrir un compte Market Space à part entière.
- Page de confirmation conviviale pour le lien de décision par email d'un devis, et pour le lien de réclamation d'un compte express (actuellement réponse JSON brute sur les deux, faute de frontend Vue existant) — à intégrer au futur SPA sans changement backend, voir §5 ajouts v0.9 et v0.17.
- Configuration FCM (`FCM_SERVER_KEY`) en attente : le module Notifications push (§5, ajout v0.12) fonctionne en mode simulation tant qu'elle n'est pas renseignée — voir aussi une future migration vers l'API HTTP v1 de FCM (OAuth, clé de compte de service) à la place de l'API legacy utilisée pour l'instant.
- Fuseau horaire métier unique (`Africa/Porto-Novo`) codé en configuration globale pour le calcul "ouvert maintenant" (§5, ajout v0.13) — correct tant que la plateforme reste au Bénin, mais à revoir (fuseau par garage/boutique) avant une extension à un pays d'Afrique de l'Ouest à fuseau différent (§1).
- **Design system/identité visuelle frontend non encore choisi** (palette, typographie, etc.). En attendant, l'écran Statistiques admin (§5, ajout v0.18) est construit en cartes de chiffres clés + tableaux, sans bibliothèque de graphiques — choix assumé, pas définitif. Une fois le design final défini, cet écran est le candidat naturel à enrichir avec de vrais graphiques (répartition géographique, volume d'activité dans le temps, etc.) ; les autres écrans admin (listes, fiches détail) resteront probablement des tableaux même après, car ce sont des interfaces d'action plutôt que de visualisation. La donnée (`GET /admin/statistics`) ne change pas entre les deux approches, seule la couche de présentation sera à refaire.

## 8. Glossaire

- **Market Space** : espace web dédié à la vente de pièces détachées et consommables automobiles par des boutiques à part entière (compte et KYC distincts d'un garage).
- **Mini-boutique (Garage)** : petit catalogue de produits courants (huiles, pneus, consommables) vendu directement depuis le profil d'un compte Garagiste, sans compte Market Space ni KYC séparés. Partage le même stock que l'atelier et la même logique métier produits/commande/paiement que le Market Space (voir §5).
- **Compte Garagiste** : accès dashboard de gestion d'un garage.
- **Compte Market Space** : accès dashboard de gestion d'une boutique de pièces détachées.
- **Validation admin** : approbation obligatoire par l'administrateur avant visibilité publique d'un compte/service/produit.
- **KYC** : vérification d'identité/légitimité d'un professionnel via justificatifs, préalable à la validation du compte.
- **Automobiliste** : utilisateur final de l'app mobile.
- **Commande** : achat isolé d'une ou plusieurs pièces/produits, sans prestation associée, payé immédiatement (§5, ajout v0.9) — distinct d'un devis, qui implique toujours une prestation de service.
- **Compte express** : compte automobiliste minimal créé par un garagiste pour un client walk-in sans app (§5, ajout v0.9). Réclamable par son propriétaire réel via un lien signé reçu par email, qui lui permet de définir un mot de passe (`is_express` repasse alors à `false` — §5, ajout v0.17).
- **Avis** : note (1 à 5) et commentaire optionnel laissés par un automobiliste sur un Garage ou un Market Space, uniquement après un devis facturé ou une commande payée (§5, ajout v0.10) — un seul avis par transaction terminée.
- **Masquage (modération)** : retrait logique et tracé d'un avis abusif/diffamatoire de la vue publique par l'administrateur, motif obligatoire (§5, ajout v0.10) — jamais une suppression, pour garder la preuve de la modération elle-même.
- **Réclamation (litige)** : contestation formelle d'un automobiliste sur une transaction terminée (devis facturé ou commande payée), instruite et tranchée par l'administrateur avec motif obligatoire (§5, ajout v0.11) — distincte d'un avis (qui note l'expérience sans nécessiter d'instruction) et du chat (qui n'est ni tracé pour l'arbitrage, ni ouvert à l'admin).
- **Notification push** : événement notifiable enregistré pour un destinataire (RDV, devis, chat, compte pro, réclamation, commande, stock bas, nouveau produit) et diffusé via FCM (§5, ajout v0.12) — simulée (enregistrée, jamais réellement envoyée) tant que FCM n'est pas configuré (§7).
- **Recherche géolocalisée** : recherche de garages/Market Space triée par distance à vol d'oiseau (formule de Haversine) à partir de la position de l'automobiliste, sans aucun fournisseur de cartographie externe côté backend (§5, ajout v0.13) — distincte de l'affichage visuel sur une carte, un sujet frontend séparé et non encore construit (§7). Étendue par recherche par nom, filtre par service proposé et choix du tri (§5, ajout v0.14), puis par recherche par nom de produit (§5, ajout v0.15) et par tranche de prix sur les produits (§5, ajout v0.16) — tous combinables ou utilisables indépendamment de la position.
- **Statistiques agrégées (admin)** : données de supervision globale exposées à l'administrateur (`GET /admin/statistics`) — structures par type/statut, répartition géographique (ville/région), volume d'activité sur une période optionnelle, avis et réclamations — pour appuyer les politiques de régulation/formalisation du secteur auprès des autorités béninoises (§1, ajout v0.18).

## 9. Consignes opérationnelles pour l'assistant (Claude Code)

- **Push automatique après chaque commit** (ajout 2026-09-16) : dès qu'un commit est créé sur ce dépôt (par l'utilisateur ou par l'assistant à sa demande) sur la branche `main`, l'assistant le pousse **immédiatement et automatiquement** vers `origin/main`, sans redemander confirmation à chaque fois — autorisation permanente donnée par l'utilisateur, qui prévaut sur la prudence par défaut de l'assistant concernant les actions affectant l'état partagé (push). Cette règle ne s'applique qu'au push d'un commit déjà créé sur `main` vers `origin/main` ; elle ne couvre pas les opérations destructrices (`push --force`, suppression de branche distante, réécriture d'historique, etc.), qui restent soumises à confirmation explicite au cas par cas.
