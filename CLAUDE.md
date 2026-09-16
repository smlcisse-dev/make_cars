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
- **Authentification API** : **Laravel Sanctum** (tokens), utilisée par le frontend web et l'app mobile.
- **Frontend web** : SPA **découplée** en **Vue 3 + TypeScript**, un seul projet gérant les trois espaces (Admin / Garagiste / Market Space) via son propre routing/permissions internes. Communique uniquement via l'API REST, comme Flutter. Gère elle-même l'auth (stockage du token Sanctum) ; CORS à configurer côté Laravel.
- **Application mobile** : Flutter (Android / iOS).
- **Base de données** : PostgreSQL hébergée sur **Supabase**.
- **Géolocalisation / cartographie** : prestataire tiers à sélectionner (Google Maps, Mapbox ou OpenStreetMap — non encore arbitré, cf. §7 points ouverts).
- **Stockage fichiers** (images, justificatifs KYC) : Supabase Storage ou équivalent.
- **Paiement** : agrégateur local à sélectionner (ex. Kkiapay, FedaPay) supportant Mobile Money (MTN Mobile Money, Moov Money, Celtiis Cash) et carte bancaire.
- **Notifications push** : Firebase Cloud Messaging (FCM) ou équivalent.
- **Chat temps réel** : solution à arbitrer (WebSockets / Laravel Reverb, Pusher, ou Supabase Realtime).

> Note pédagogique : l'utilisateur découvre Vue 3 et TypeScript sur ce projet — expliquer brièvement les concepts clés (Composition API, réactivité, typage) au fil du code produit côté frontend.

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

### Matrice des droits d'accès (résumé)

| Fonctionnalité | Admin | Compte Garagiste | Compte Market Space | Automobiliste (mobile) |
|---|---|---|---|---|
| Dashboard Admin | Oui | Non | Non | Non |
| Dashboard Garagiste | Oui (lecture) | Oui | Non | Non |
| Dashboard Market Space | Oui (lecture) | Non | Oui | Non |
| Créer/modifier service garage | Oui | Oui (soumis validation) | Non | Non |
| Créer/modifier produit (mini-boutique ou Market Space) | Oui | Oui (soumis validation) | Oui (soumis validation) | Non |
| Valider service/produit/compte | Oui | Non | Non | Non |
| Gestion stock/inventaire (unique par garage / par boutique) | Oui (lecture) | Oui | Oui | Non |
| Génération devis/factures | Oui (lecture) | Oui | Émission (factures) | Consultation |
| Paiement en ligne | — | Réception | Réception | Émission |
| Prise de rendez-vous | Oui (lecture) | Gestion agenda | Non concerné | Prise de RDV |
| Chat avec l'automobiliste | Non | Oui | Oui | Oui |
| Recherche garage par géoloc. | — | — | — | Oui |
| Laisser un avis/notation | Non | Non | Non | Oui |
| Notifications push (statut commande) | — | — | — | Oui |

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

- Typologie précise des services proposables par les garages.
- Choix du prestataire de cartographie (Google Maps / Mapbox / OpenStreetMap).
- Choix de l'agrégateur de paiement (Kkiapay, FedaPay, ou autre).
- Choix de la solution de chat temps réel (Reverb, Pusher, Supabase Realtime).
- Modalités de contestation d'un avis par un professionnel avant modération/suppression admin.
- Cadre légal précis de partage des données agrégées avec l'administration béninoise (nature des données, fréquence, base légale RGPD/loi locale).
- Méthodes d'authentification définitives côté automobiliste (email, téléphone, réseaux sociaux — à confirmer).
- Périmètre exact du catalogue « mini-boutique » d'un garage (catégories de produits autorisées, limite de nombre éventuelle) et seuil au-delà duquel un garage devrait plutôt ouvrir un compte Market Space à part entière.

## 8. Glossaire

- **Market Space** : espace web dédié à la vente de pièces détachées et consommables automobiles par des boutiques à part entière (compte et KYC distincts d'un garage).
- **Mini-boutique (Garage)** : petit catalogue de produits courants (huiles, pneus, consommables) vendu directement depuis le profil d'un compte Garagiste, sans compte Market Space ni KYC séparés. Partage le même stock que l'atelier et la même logique métier produits/commande/paiement que le Market Space (voir §5).
- **Compte Garagiste** : accès dashboard de gestion d'un garage.
- **Compte Market Space** : accès dashboard de gestion d'une boutique de pièces détachées.
- **Validation admin** : approbation obligatoire par l'administrateur avant visibilité publique d'un compte/service/produit.
- **KYC** : vérification d'identité/légitimité d'un professionnel via justificatifs, préalable à la validation du compte.
- **Automobiliste** : utilisateur final de l'app mobile.
