# Make Cars : document de passation (état au 2026-09-20)

**Vérifié dans le code :**
- `main` est synchronisée avec `origin/main`, sans commit en retard ni en avance. Seul élément non suivi : `.claude/`.
- Dernier commit : `d35038b` (profil pro obligatoire + validation téléphone). 41 commits au total.
- Tests backend : **425 tests, 425 réussis** (1004 assertions).
- `backend/.env` pointe vers `.env.development` (base de développement).
- Le backend expose 164 routes.

**Non vérifié :** le « testé en navigateur ». Le frontend n'a aucun test automatisé (`package.json` sans script de test). Les sections ci-dessous disent « le code existe et est câblé au routeur » ; la confirmation manuelle reste à faire.

---

## 1. Frontend web : écrans existants

Routes dans `frontend-web/src/router/index.ts`, avec garde de navigation par rôle.

**Commun**
- `/login` : connexion email/mot de passe.
- `/403` et page 404.
- Composants partagés : `AppButton`, `AppTable`, `AppPagination`, `BaseModal`, `ReasonPromptModal` (motif obligatoire), `StatusBadge`, `LocationSelect`.

**Espace Admin (`/admin`, 6 sections)**

| Écran | Routes | Périmètre |
|---|---|---|
| Tableau de bord | `/admin` | Statistiques agrégées (`GET /admin/statistics`) : cartes de chiffres et tableaux, sans graphiques. Répartition géographique par département, séparée garages / Market Space. |
| Inscriptions | `/admin/registrations`, `/:id` | Liste et fiche KYC. Approbation, rejet, suspension/réactivation (motif obligatoire). |
| Services | `/admin/services`, `/:id` | Validation des services de garage. |
| Produits | `/admin/products`, `/:id` | Validation des produits (mini-boutique et Market Space). |
| Avis | `/admin/avis`, `/:id` | Modération : masquage avec motif. |
| Litiges | `/admin/litiges`, `/:id` | Liste, instruction, décision (fondée + action suspension/avertissement, ou rejet) et clôture. |

**Espaces Garagiste (`/garage`) et Market Space (`/market-space`)**
- Une seule page réelle par espace : **Mon profil** (`ProfessionalProfileView.vue`, prop `space`) :
  - informations et localisation (`LocationSelect` en cascade, bouton « Utiliser ma position », quartier) ;
  - horaires sur 7 jours ;
  - photos ;
  - bandeau des éléments manquants.
- Le « Tableau de bord » de chaque espace est un **placeholder** (message de bienvenue).
- Tant que le profil est incomplet, seul « Mon profil » est proposé dans la barre latérale.

## 2. Backend construit et testé, sans écran frontend

**Espace Garagiste**
- Services : CRUD, soumis à validation.
- Produits : CRUD, stock, seuil bas.
- RDV : 7 routes (confirmer, refuser, reprogrammer).
- Devis/factures : 10 routes (versions, PDF, paiement manuel, statuts `draft` → `invoiced` ou `abandoned`).
- Commandes : 4 routes, paiement manuel.
- Clients express : `garage/clients`.
- Chat : 4 routes.
- Avis reçus, réclamations reçues (avec réponse), notifications, jeton FCM.

**Espace Market Space**
- Produits : 5 routes, stock.
- Commandes : 4 routes.
- Réclamations : 4 routes.
- Avis, notifications, jeton FCM.

**Côté automobiliste (`/mobile/*`)** : API complète, sans app Flutter.
- Listes et fiches garages et Market Space.
- Recherche `nearby` : position, rayon, nom, service, produit, prix, tri.
- RDV, devis (accepter/refuser), commandes, chat, avis, réclamations, notifications, jeton d'appareil.

**Auth et public**
- Inscription automobiliste et professionnelle (`auth/register`).
- Login email/mot de passe et Google (couvert par les tests).
- `auth/express-claim`.
- Liens signés email pour la décision d'un devis (`quotes/{quote}/...`, réponse JSON brute).
- Lien signé de réclamation d'un compte express (`express-clients/{user}/claim`, réponse JSON brute).
- `locations/*` : départements, communes, arrondissements.

**Supervision admin en lecture, sans écran**
- `admin/garages`, `admin/market-space-accounts`, `admin/appointments`, `admin/quotes`, `admin/orders`, `admin/conversations`.

**Transversal**
- Notifications push : modèle complet, envoi simulé sans `FCM_SERVER_KEY`.
- Mini-boutique polymorphe, décrément de stock unique.
- Emails (devis, décision de réclamation).

## 3. Ce qui n'existe nulle part

- **Application mobile Flutter** : aucun code dans le dépôt (seuls `backend/` et `frontend-web/`).
- **Écrans d'inscription** (automobiliste et professionnel) côté web : seul `/login` existe.
- **Dashboards Garagiste et Market Space fonctionnels** : services, produits, stock, RDV, devis, commandes, chat, avis, réclamations, notifications.
- **Écrans de supervision admin** : garages, boutiques, RDV, devis, commandes, conversations.
- **Paiement en ligne réel** : agrégateur non choisi, seul le paiement manuel V1 existe.
- **Chat temps réel** : pas de WebSocket ni Reverb.
- **Envoi push FCM réel**.
- **Affichage carte** (frontend).
- **Pages Vue de confirmation** pour les liens email (décision de devis, réclamation de compte express).
- **Authentification par téléphone/SMS**.
- **Extension multi-pays** : table `countries`, fuseau par structure.
- **Contestation d'un avis par le professionnel**.
- **Frontend** : aucun test automatisé, pas de design system.

## 4. Identifiants de test (base de développement)

Mot de passe de tous les comptes seedés : **`password`**. Vérifier l'environnement actif : `backend/bin/switch-env.sh status`.

| Compte | Email | Origine |
|---|---|---|
| Admin | `admin@makecars.test` | `DatabaseSeeder` |
| Garagiste en attente | `garage.etoile.pending@makecars.test` | `ProfessionalRegistrationTestSeeder` |
| Market Space en attente | `pieces.express.pending@makecars.test` | idem |
| **Garagiste approuvé** | `garage.excellence.approved@makecars.test` | idem |
| Market Space rejeté | `auto.pieces.rejected@makecars.test` | idem |
| **Market Space approuvé** | `marche.pieces.approved@makecars.test` | `CatalogTestSeeder` |

- Je n'ai pas vérifié ce qui est réellement présent dans la base de dev ; relancer les seeders si besoin.
- Les deux comptes approuvés ont un **profil incomplet** (règle v0.20) : ils atterrissent sur « Mon profil » tant que téléphone, position, localisation, 7 jours d'horaires et une photo ne sont pas saisis.
- Un profil complet se crée en test avec l'état de factory `complete()`.
- Après un `switch-env`, redémarrer `php artisan serve`.
- Le mot de passe Supabase de dev se renseigne à la main dans `.env.development`, jamais par l'assistant.

## 5. Points ouverts (§7 du CLAUDE.md)

1. **Cartographie (affichage carte)** : frontend uniquement, la recherche par distance backend n'en a pas besoin. Candidat : OpenStreetMap via Leaflet (web) ou `flutter_map` (mobile).
2. **Agrégateur de paiement** : Kkiapay, FedaPay ou autre. Le paiement manuel V1 le remplace en attendant.
3. **Chat temps réel** : Reverb, Pusher ou Supabase Realtime.
4. **Contestation d'un avis** par un pro avant modération : procédure non définie.
5. **Cadre légal du partage de données agrégées** avec l'administration : nature, fréquence, base légale.
6. **Auth téléphone/SMS** pour l'automobiliste : email et Google sont tranchés, pas le SMS.
7. **Périmètre de la mini-boutique** : catégories autorisées, limite, seuil à partir duquel il faut un compte Market Space.
8. **Pages de confirmation** des liens email (devis, compte express) : à intégrer au SPA, sans changement backend.
9. **FCM** : `FCM_SERVER_KEY` non configurée (mode simulation). Migration future vers l'API HTTP v1.
10. **Fuseau horaire unique** `Africa/Porto-Novo` : à passer par structure avant tout pays à fuseau différent.
11. **Design system / identité visuelle** non choisis : écran Statistiques en cartes et tableaux, à enrichir de graphiques plus tard.

**Autres pistes ouvertes dans les ajouts récents**
- Ajouter `is_profile_complete` à `scopePubliclyVisible()` : aujourd'hui, une fiche vide peut être visible côté mobile.
- Vérification téléphone pour la finalisation d'un compte express.
- Index géospatial si le volume de professionnels croît.
- À Natitingou, `Peporiyakou` (source) = `Natitingou IV` (Wikipédia) : à confirmer.

## 6. Derniers changements de règles métier

- **Profil complet obligatoire (v0.20, commit `d35038b`)**
  - Dès l'approbation, le pro doit tout compléter : nom, adresse, téléphone, latitude et longitude, département, commune, arrondissement, quartier, 7 jours d'horaires, au moins une photo.
  - Le middleware `profile.complete` renvoie 403 `profile_incomplete` sur toutes les routes de l'espace pro, sauf celles du profil.
  - La dernière photo ne peut pas être supprimée.
  - Frontend : garde de navigation et barre latérale restreints ; l'intercepteur Axios recharge sur la page de profil en cas de 403.
  - La complétude n'est pas encore une condition de visibilité publique.
- **Téléphone béninois (v0.21)**
  - Format `+229` suivi de 10 chiffres commençant par `01`. `00229` accepté, l'ancien format à 8 chiffres refusé.
  - Forme stockée : `+2290123456789`.
  - Règle : `BeninPhoneNumber` + trait `NormalizesBeninPhone`. Frontend : `utils/beninPhone.ts`.
  - Les téléphones antérieurs dans un autre format ne sont pas migrés.
- **Hiérarchie administrative béninoise (v0.19, commit `253b1e8`)**
  - Département (12), commune (77), arrondissement (546), puis quartier en texte libre.
  - Données en base (`LocationSeeder`, appelé par la migration).
  - Remplace `city`/`region` (v0.18).
  - Les statistiques séparent la géographie garage et Market Space.
- **Antérieurement** : statistiques admin (v0.18), réclamation de compte express (v0.17), filtre de prix (v0.16), recherche par nom de produit (v0.15), recherche par nom/service/tri (v0.14), recherche géolocalisée (v0.13).

**Rappel opérationnel** (CLAUDE.md §9) : après chaque commit sur `main`, push immédiat vers `origin/main` (autorisation permanente). Les opérations destructrices restent soumises à confirmation.
