# Make Cars : document de recette avant mise en ligne

Liste unique de tout ce qui n'a **jamais été testé dans un navigateur** (source : PASSATION.md, « Niveau de vérification des écrans »), plus quelques contrôles de non-régression. À dérouler en une ou deux séances, dans l'ordre : chaque partie prépare les données de la suivante.

**Comment remplir** : pour chaque étape, faire l'action, comparer avec le **résultat attendu**, puis cocher la case (remplacer `☐` par `☑`). Si le résultat diffère, ne pas cocher et écrire ce qui s'est passé dans la colonne « Remarque » (voir « Signaler un problème » en fin de document).

Tous les libellés entre guillemets (« … ») sont ceux affichés à l'écran : si un bouton porte un autre nom, c'est déjà une remarque à noter.

---

## 1. Préparation (une seule fois, avant la séance)

### 1.1 Lancer les deux serveurs

Ouvrir **trois terminaux**.

**Terminal 1 : vérifier la base, remettre les comptes de test, lancer l'API**
```bash
cd ~/make_cars/backend
bin/switch-env.sh status
```
Résultat attendu : `Environnement actif : .env.development`. **Si ce n'est pas le cas, s'arrêter** : `bin/switch-env.sh dev`, puis recommencer. Ne jamais dérouler la recette sur la production.

```bash
php artisan db:seed --class=ProfessionalRegistrationTestSeeder
php artisan db:seed --class=CatalogTestSeeder
php artisan serve --no-reload
```
Les deux premières commandes remettent les comptes de test dans l'état du tableau 1.3 (elles affichent pour chaque compte : « créé », « réinitialisé » ou « conservé »). La dernière lance l'API sur `http://127.0.0.1:8000` : **laisser ce terminal ouvert** pendant toute la séance.

**Terminal 2 : lancer le site**
```bash
cd ~/make_cars/frontend-web
npm run dev
```
Le site est à l'adresse **http://localhost:5173**. Laisser ce terminal ouvert.

**Terminal 3 : lire les emails et taper les commandes de la recette.** Toujours se placer d'abord dans le dossier du backend :
```bash
cd ~/make_cars/backend
```

Les commandes de la recette utilisent l'outil `jq`. Vérifier qu'il est installé :
```bash
jq --version
```
Si la commande répond `jq-1.…`, c'est bon. Si elle répond « commande introuvable » (`command not found`), l'installer (le mot de passe de la session Linux est demandé) :
```bash
sudo dnf install jq
```

**Message « Trop de tentatives. Réessayez dans … secondes. »** Il peut apparaître pendant la recette, car la plateforme limite le nombre d'essais : par exemple 10 par minute depuis un même ordinateur pour les inscriptions et les codes reçus par email. **Ce n'est pas un défaut** : attendre la fin du délai affiché, puis continuer là où on s'était arrêté. (Seul le test SE-02 le provoque volontairement.)

### 1.2 Où lire les emails

En développement, **aucun email n'est réellement envoyé** : chacun est écrit dans le fichier `storage/logs/laravel.log`. Depuis le terminal 3 (dossier `backend`) :

Le dernier code à 6 chiffres (inscription ou mot de passe oublié) :
```bash
grep -A1 "letter-spacing:8px" storage/logs/laravel.log | tail -1 | tr -d ' \r'
```

Le lien « Compléter mon profil » d'une boutique :
```bash
grep -o 'http[^"]*/market-space/profile' storage/logs/laravel.log | tail -1
```

Les deux liens de décision d'un devis (« accepter » puis « refuser ») :
```bash
grep -o 'http[^"]*/devis/decision?[^"]*' storage/logs/laravel.log | tail -2 | sed 's/&amp;/\&/g'
```

Le lien d'activation d'un compte express :
```bash
grep -o 'http[^"]*/compte/activer?[^"]*' storage/logs/laravel.log | tail -1
```

Copier le lien affiché et le coller dans la barre d'adresse du navigateur.

**Astuce pour les adresses email** : une adresse Gmail accepte un « + » suivi de n'importe quel mot. `horuskoeus6+boutique1@gmail.com`, `horuskoeus6+client1@gmail.com`… sont des adresses **différentes** pour Make Cars, mais arrivent toutes dans la même boîte Gmail. Utiliser un mot nouveau à chaque inscription (sinon : « email déjà utilisé »). Aujourd'hui, les emails restent dans le fichier ci-dessus ; l'astuce servira telle quelle le jour où un vrai service d'envoi sera branché.

### 1.3 Comptes de test

Mot de passe de tous ces comptes : **`password`**

| Compte | Email |
|---|---|
| Administrateur | `admin@makecars.test` |
| Garagiste approuvé, profil complet | `garage.excellence.approved@makecars.test` |
| Market Space approuvé, profil complet | `marche.pieces.approved@makecars.test` |
| Garagiste « profil à compléter » | `garage.nouveau.incomplete@makecars.test` |
| Garagiste en attente d'examen | `garage.etoile.pending@makecars.test` |
| Market Space en attente d'examen | `pieces.express.pending@makecars.test` |
| Market Space refusé (avec motif) | `auto.pieces.rejected@makecars.test` |

Des comptes seront aussi créés pendant la recette (boutique, client) : noter ici leur email et leur mot de passe.

| Créé à l'étape | Email | Mot de passe |
|---|---|---|
| MS-01 (boutique) | | |
| DV-02 (client express) | | |

### 1.4 Deux sessions en même temps

Le site garde une seule connexion par fenêtre de navigateur. Pour être **administrateur et professionnel en même temps** :
- fenêtre normale : le professionnel ;
- **fenêtre privée** (Firefox : `Ctrl+Maj+P`) : l'administrateur.

### 1.5 Mode « téléphone » du navigateur

Firefox : `Ctrl+Maj+M` (ou menu ☰ > Plus d'outils > Vue adaptative). Choisir un modèle de téléphone en haut de la page (par exemple « iPhone SE », 375 px de large). `Ctrl+Maj+M` à nouveau pour revenir à l'affichage normal.

---

## 2. Parcours à tester

### 2.1 Inscription d'une boutique (Market Space), du formulaire à la soumission

Fenêtre normale, **déconnecté**.

| ☐ | N° | Action | Résultat attendu | Remarque |
|---|---|---|---|---|
| ☐ | MS-01 | Ouvrir http://localhost:5173/login, cliquer « Créer un compte professionnel », puis « Je vends des pièces ou du matériel automobile ». | Page « Créer mon compte boutique ». | |
| ☐ | MS-02 | Remplir : prénom, nom, email `horuskoeus6+boutique1@gmail.com` (changer le mot après « + » si déjà utilisé), téléphone `+229 01 97 11 22 33` (si « téléphone déjà utilisé », changer les derniers chiffres, ex. `+229 01 97 11 22 34`), mot de passe de 8 caractères et confirmation. Cliquer « Créer mon compte ». Noter l'email et le mot de passe dans le tableau 1.3. | Page « Vérifiez votre email », qui cite l'adresse saisie et « valable 15 minutes ». | |
| ☐ | MS-03 | Saisir un code faux (`000000`), « Vérifier ». | Message d'erreur avec le nombre d'essais restants ; on reste sur la page. | |
| ☐ | MS-04 | Lire le vrai code (commande du 1.2), le saisir, « Vérifier ». | Page « Votre compte a été créé ». | |
| ☐ | MS-05 | Lire le lien « Compléter mon profil » (1.2) et l'ouvrir. Se connecter avec le compte créé. | Arrivée sur « Mon profil » de l'**Espace Market Space**. Le menu ne propose que « Mon profil ». Bandeau : « Complétez votre profil et vos informations légales, puis soumettez votre dossier pour validation. », avec la liste de ce qui manque. | |
| ☐ | MS-06 | Section « Informations et localisation » : champ « Nom de la boutique » = **`Boutique Recette`** (ce nom exact sert plus loin), téléphone, adresse, Département / Commune / Arrondissement (chaque liste se remplit après le choix précédent), Quartier. Coordonnées : `6.3703` et `2.3912`. « Enregistrer ». | « Informations enregistrées. » La liste des éléments manquants raccourcit. | |
| ☐ | MS-07 | Cliquer « Utiliser ma position » (accepter la demande du navigateur). | Latitude et longitude remplies, avec « Position relevée (précision : environ … ) » ou, sur un ordinateur, un avertissement « Position approximative… Relevez plutôt vos coordonnées avec votre téléphone, sur place. ». **Remettre ensuite `6.3703` / `2.3912` et « Enregistrer ».** | |
| ☐ | MS-08 | Section « Horaires d'ouverture » : cocher « Fermé » pour le dimanche, « Enregistrer les horaires ». | « Horaires enregistrés. » | |
| ☐ | MS-09 | Section « Photos » : ajouter une photo JPG ou PNG. | « Photo(s) ajoutée(s). » et la photo apparaît. | |
| ☐ | MS-10 | Section « Informations légales » : Numéro RCCM (texte libre), IFU `1234567890123` (13 chiffres), NPI `1234567890` (10 chiffres). « Enregistrer les informations légales ». | « Informations légales enregistrées. » | |
| ☐ | MS-11 | Même section : « Document du registre de commerce » > « Choisir un fichier » (PDF, JPG ou PNG). Puis « Voir le document ». | « Document envoyé » ; le document s'ouvre. | |
| ☐ | MS-12 | « Certificat d'Identification Personnelle (CIP) » > « Choisir un fichier ». Puis « Voir le document ». | Même comportement que MS-11. | |
| ☐ | MS-13 | Section « Soumettre mon dossier » : cliquer « Soumettre pour validation », puis **« Annuler »** dans la fenêtre qui s'ouvre. | Fenêtre titrée « Soumettre votre dossier » (pas une fenêtre grise du navigateur). Après « Annuler » : rien n'a changé, le dossier n'est pas soumis. | |
| ☐ | MS-14 | Recommencer et cliquer « Soumettre ». | « Votre dossier a été soumis pour validation. » Bandeau : « Votre dossier a été soumis le … et est en cours d'examen. », bouton « Actualiser ». Tous les champs sont grisés, plus aucun bouton « Enregistrer ». | |

### 2.2 Administration : fiche d'examen du dossier, approbation

Fenêtre privée, connecté en **administrateur**.

| ☐ | N° | Action | Résultat attendu | Remarque |
|---|---|---|---|---|
| ☐ | AD-01 | Menu « Inscriptions ». Essayer chaque filtre, dont « Profil à compléter ». | Chaque filtre change la liste. Colonnes « Date de soumission » et « Compte créé le ». « Boutique Recette » figure sous « En attente ». | |
| ☐ | AD-02 | Ouvrir « Boutique Recette ». | En-tête : nom, type de compte, badge d'état, date de soumission, date de création. Blocs dans l'ordre : Représentant (prénom, nom, email, téléphone saisis en MS-02), Structure (profil public), Horaires (dimanche « Fermé »), Photos du profil, Informations légales, Justificatifs, Historique des décisions, Décision. Rien n'est modifiable. | |
| ☐ | AD-03 | Bloc Structure : cliquer « Voir sur la carte ↗ ». | Un nouvel onglet OpenStreetMap s'ouvre sur Cotonou. | |
| ☐ | AD-04 | Cliquer une photo. | Elle s'agrandit. | |
| ☐ | AD-05 | Informations légales : RCCM, IFU et NPI de MS-10 ; cliquer « Consulter le CIP ». | Le CIP envoyé en MS-12 s'ouvre. | |
| ☐ | AD-06 | Justificatifs : « Consulter » sur chaque ligne. | Deux lignes, « Registre de commerce (RCCM) » et « Certificat d'Identification Personnelle (CIP) » ; chacune ouvre le bon fichier. | |
| ☐ | AD-07 | Ouvrir le dossier `garage.nouveau.incomplete` (filtre « Profil à compléter »). | « Date de soumission : Jamais soumis » ; les valeurs absentes affichent « Non fourni ». | |
| ☐ | AD-08 | Revenir à « Boutique Recette », cliquer « Approuver ». | Badge « Approuvé » ; l'historique des décisions montre l'approbation, sa date et l'auteur. | |
| ☐ | AD-09 | Fenêtre normale (boutique) : cliquer « Actualiser » sur « Mon profil ». | Le bandeau disparaît ; le menu complet apparaît (Tableau de bord, Produits, Commandes, Messages, Avis, Réclamations, Notifications, Mon profil). Les informations légales ne sont plus modifiables. | |

### 2.3 Espace Market Space : profil et produits

Fenêtre normale, connecté avec **Boutique Recette**.

| ☐ | N° | Action | Résultat attendu | Remarque |
|---|---|---|---|---|
| ☐ | MS-15 | « Mon profil » : changer la description, « Enregistrer ». Recharger la page (`F5`). | « Informations enregistrées. » ; la nouvelle description est toujours là après rechargement. | |
| ☐ | MS-16 | « Mon profil » > Photos : essayer de supprimer la seule photo. | Refus avec un message (la dernière photo ne peut pas être supprimée). | |
| ☐ | MS-17 | « Produits » > « Ajouter un produit » sans image, « Ajouter ». | Message d'erreur sous « Image » : l'image est obligatoire. | |
| ☐ | MS-18 | Remplir : nom `Plaquettes recette`, prix `18000`, stock initial `10`, image. « Ajouter ». | Le produit apparaît dans la liste, statut « En attente ». | |
| ☐ | MS-19 | Cliquer la ligne du produit, changer le prix, « Enregistrer ». | Message rappelant que la modification remet le produit en validation ; statut « En attente ». | |
| ☐ | MS-20 | « Ajuster le stock » : quantité `10`, seuil d'alerte `9`, « Enregistrer ». | Le stock reste 10, pas encore en orange (10 > 9). Statut inchangé. | |
| ☐ | MS-21 | Ajouter un second produit quelconque, puis « Supprimer » > « Annuler ». Recommencer > « Supprimer ». | Fenêtre « Supprimer le produit ». Après « Annuler » : le produit est toujours là. Après « Supprimer » : « Produit supprimé. » et il disparaît. | |

### 2.4 Validation du produit et arrivée d'un client (à la place de l'application mobile)

L'application mobile n'existe pas encore : les commandes ci-dessous jouent le rôle d'un automobiliste. À taper dans le **terminal 3**, dossier `backend`, **dans le même terminal du début à la fin de 2.4** (les valeurs sont gardées entre deux commandes).

| ☐ | N° | Action | Résultat attendu | Remarque |
|---|---|---|---|---|
| ☐ | AD-09b | Admin : menu « Produits », ouvrir `Plaquettes recette`, « Approuver ». | Statut « Approuvé ». Côté boutique (« Produits », `F5`) : « Approuvé ». | |
| ☐ | CL-01 | Créer un client et lui ouvrir une session (copier les deux blocs, l'un après l'autre) : voir **bloc A** ci-dessous. | Affiche `Jeton : 1|…` (une longue suite de caractères, jamais `null`), puis `Boutique n° …, produit n° …` avec deux nombres. Si `null` apparaît : voir la note sous les blocs. | |
| ☐ | CL-02 | Envoyer un message et passer une commande : **bloc B**. | Affiche `Message envoyé.` puis `Commande n° …`. | |

**Bloc A**
```bash
API=http://127.0.0.1:8000/api
TOKEN=$(curl -s -X POST $API/auth/register/automobiliste -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"name":"Client Recette","email":"client.recette@makecars.test","password":"password123","password_confirmation":"password123"}' | jq -r '.data.token')
echo "Jeton : $TOKEN"
BOUTIQUE=$(curl -s $API/mobile/market-space-accounts -H 'Accept: application/json' | jq '.data[] | select(.name=="Boutique Recette")')
MS_ID=$(echo "$BOUTIQUE" | jq '.id'); PRODUIT_ID=$(echo "$BOUTIQUE" | jq '.products[0].id')
echo "Boutique n° $MS_ID, produit n° $PRODUIT_ID"
```

**Bloc B**
```bash
CONV_ID=$(curl -s -X POST $API/mobile/conversations -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json' -H 'Content-Type: application/json' -d "{\"market_space_account_id\":$MS_ID}" | jq '.data.id')
curl -s -X POST $API/mobile/conversations/$CONV_ID/messages -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json' -H 'Content-Type: application/json' -d '{"body":"Bonjour, avez-vous des plaquettes pour une Corolla 2010 ?"}' | jq -r '.message'
ORDER_ID=$(curl -s -X POST $API/mobile/orders -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json' -H 'Content-Type: application/json' -d "{\"lines\":[{\"product_id\":$PRODUIT_ID,\"quantity\":2}]}" | jq '.data.id')
echo "Commande n° $ORDER_ID"
```

*Note* : si `Jeton : null` (le client existe déjà, recette rejouée), remplacer dans le bloc A la ligne `TOKEN=…` (2 lignes) par la connexion : `TOKEN=$(curl -s -X POST $API/auth/login -H 'Accept: application/json' -H 'Content-Type: application/json' -d '{"email":"client.recette@makecars.test","password":"password123"}' | jq -r '.data.token')`. Si `Boutique n° ` est vide : le nom de la boutique n'est pas exactement `Boutique Recette`, ou le produit n'est pas approuvé.

### 2.5 Espace Market Space : tableau de bord, messages, commandes

Fenêtre normale, **Boutique Recette**.

| ☐ | N° | Action | Résultat attendu | Remarque |
|---|---|---|---|---|
| ☐ | TB-01 | « Tableau de bord ». | « Bienvenue, Boutique Recette ». Section « À traiter » : carte « Commandes à encaisser » = 1. Section « Activité » : « Facturé en (mois en cours) » = 0, « Note moyenne » : « Aucun avis pour le moment. », « En attente de validation par l'administrateur » : « 0 produit ». **Aucune** carte de rendez-vous, devis ou services (propres au garage). | |
| ☐ | TB-02 | Cliquer la carte « Commandes à encaisser ». | Page « Commandes » ; la commande de « Client Recette » est « En attente de paiement ». | |
| ☐ | MS-22 | « Messages ». | Conversation « Client Recette » à gauche ; en la choisissant, le message de CL-02 s'affiche. Aucun lien « Voir le devis ». | |
| ☐ | MS-23 | Répondre par un texte, « Envoyer ». Puis envoyer une photo seule (choisir un fichier, « Envoyer »), et cliquer « Voir la photo ». | Les deux messages apparaissent à droite ; la photo s'ouvre. | |
| ☐ | MS-24 | « Commandes », ouvrir la commande. | « Commande n° … », client, lignes (« Pièce », `Plaquettes recette`, quantité 2), total, bouton « Marquer comme payé ». | |
| ☐ | MS-25 | « Marquer comme payé » > « Annuler ». | Fenêtre « Marquer la commande comme payée » ; après « Annuler », statut toujours « En attente de paiement ». | |
| ☐ | MS-26 | « Marquer comme payé » > « Marquer comme payée ». | Statut « Payée », date de paiement, bouton « Télécharger la facture » (le PDF s'ouvre, avec la mention facture). | |
| ☐ | MS-27 | « Produits ». | Stock de `Plaquettes recette` : **8** (10 − 2, une seule fois), en orange avec « (seuil 9 — à réapprovisionner) ». | |
| ☐ | MS-28 | « Messages », conversation « Client Recette ». | Nouveau « Message système » avec la facture de la commande. | |
| ☐ | TB-03 | « Tableau de bord », « Actualiser ». | Plus de carte « Commandes à encaisser ». Carte « Produits en stock bas » = 1, listant `Plaquettes recette` « 8 (seuil 9) » ; un clic mène à « Produits ». « Facturé en … » = 36 000 FCFA (2 × 18 000). | |
| ☐ | MS-29 | « Notifications ». | Au moins une notification (stock bas) ; un clic sur une notification non lue enlève sa pastille bleue. | |

### 2.6 Avis et réclamation du client, instruction par l'administrateur

**Terminal 3.** Choisir d'abord une photo sur l'ordinateur et indiquer son chemin (par exemple `~/Images/photo.jpg`) :

**Bloc C**
```bash
API=http://127.0.0.1:8000/api
PHOTO=~/Images/photo.jpg
TOKEN=$(curl -s -X POST $API/auth/login -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"email":"client.recette@makecars.test","password":"password123"}' | jq -r '.data.token')
ORDER_ID=$(curl -s $API/mobile/orders -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json' | jq '.data[0].id')
echo "Commande n° $ORDER_ID"
curl -s -X POST $API/mobile/orders/$ORDER_ID/review -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json' -H 'Content-Type: application/json' -d '{"rating":4,"comment":"Pièces conformes, bon accueil."}' | jq -r '.message'
curl -s -X POST $API/mobile/orders/$ORDER_ID/dispute -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json' -F 'reason=Une des deux plaquettes est fissurée.' -F "photos[]=@$PHOTO" | jq -r '.message'
```

| ☐ | N° | Action | Résultat attendu | Remarque |
|---|---|---|---|---|
| ☐ | CL-03 | Exécuter le bloc C. | `Commande n° …` (même numéro qu'en CL-02), `Avis publié.`, `Réclamation déposée.` | |
| ☐ | MS-30 | Boutique : « Avis ». | « Avis reçus » : 4/5, ★★★★☆, « Client Recette », le commentaire. Aucun bouton pour modifier ou supprimer. | |
| ☐ | MS-31 | Boutique : « Réclamations ». Essayer les filtres. | Réclamation de « Client Recette », statut « Déposée » ; filtres « Toutes », « Déposées », « En instruction », « Fondées », « Rejetées », « Clôturées ». | |
| ☐ | MS-32 | Ouvrir la réclamation ; cliquer « Photo 1 ». | Motif, « Transaction concernée : commande n° … », la photo s'ouvre. « Espace d'échange avec l'administrateur » : « Aucun message pour l'instant. » | |
| ☐ | AD-10 | Admin : « Litiges », ouvrir la réclamation, cliquer « Demander une réponse » (écrire un message). | Le message apparaît dans l'espace d'échange ; statut « En instruction ». | |
| ☐ | MS-33 | Boutique : `F5` sur la réclamation, écrire une réponse, « Répondre ». | La réponse apparaît sous le message de l'admin. | |
| ☐ | AD-11 | Admin : `F5`, lire la réponse ; « Rejeter » avec un motif. Rouvrir la réclamation depuis la liste, « Clôturer le dossier ». | Après le rejet : retour à la liste avec « Réclamation rejetée. », statut « Rejetée ». Après la clôture : « Réclamation clôturée. », statut « Clôturée ». | |
| ☐ | MS-34 | Boutique : `F5` sur la réclamation. | Bloc « Décision de l'administrateur » avec le motif et la date ; plus de champ de réponse. | |
| ☐ | TB-04 | Boutique : « Tableau de bord », « Actualiser ». | « Note moyenne » : 4,0 / 5 (1 avis) ; un clic mène à « Avis ». Pas de carte « Réclamations ouvertes ». | |

### 2.7 Garagiste : devis à un client sans application, liens email, tableau de bord

Fenêtre normale : se déconnecter, puis se connecter en **`garage.excellence.approved@makecars.test`**.

| ☐ | N° | Action | Résultat attendu | Remarque |
|---|---|---|---|---|
| ☐ | DV-01 | « Tableau de bord ». Noter les nombres affichés. | Cartes « À traiter » non nulles seulement (ou « Rien à traiter pour le moment. ») ; section « Activité » avec 4 cartes dont « Devis en attente de réponse du client ». Chaque carte « À traiter » mène à sa page (RDV → « Rendez-vous », devis → « Devis », etc.). | |
| ☐ | DV-02 | « Devis » > « Nouveau devis » > « Sans RDV (client présent) ». Nom du client, email **nouveau** `horuskoeus6+client1@gmail.com`, téléphone `+229 01 97 44 55 66` (changer les derniers chiffres si refusé). Ajouter une ligne « Prestation libre » (libellé, prix `25000`) et une ligne « Frais de diagnostic » (`5000`). « Créer le devis ». Noter l'email dans le tableau 1.3. | Fiche du devis, statut « Brouillon ». | |
| ☐ | DV-03 | « Envoyer au client ». | Statut « Envoyé ». Sur « Tableau de bord » (« Actualiser ») : « Devis en attente de réponse du client » a augmenté de 1. | |
| ☐ | DV-04 | Lire les deux liens de décision (1.2). Ouvrir le **premier** (celui qui contient `choix=accepter`). **Ne cliquer sur rien.** | Page « Devis de Garage Excellence Akpakpa », « Pour … », les deux lignes, « Total » 30 000 FCFA, texte « Vous êtes sur le point d'accepter ce devis… », bouton « Confirmer l'acceptation du devis » et lien « Je préfère refuser ». | |
| ☐ | DV-05 | Revenir au dashboard garagiste, recharger la fiche du devis. | **Toujours « Envoyé »** : ouvrir le lien n'a rien décidé. | |
| ☐ | DV-06 | Sur la page du lien, cliquer « Je préfère refuser », puis « Je préfère accepter ». | Le texte et le bouton basculent entre « Confirmer le refus » et « Confirmer l'acceptation du devis ». Toujours rien d'envoyé (vérifier comme en DV-05). | |
| ☐ | DV-07 | Cliquer « Confirmer l'acceptation du devis ». | Page « Merci » : « Merci, votre décision a été transmise au garage. » et « Vous avez accepté ce devis. » | |
| ☐ | DV-08 | Ouvrir le **second** lien (`choix=refuser`). | Pas de bouton de décision ; encadré « Ce devis a été accepté le … ». | |
| ☐ | DV-09 | Dans la barre d'adresse, repérer `signature` dans le lien et changer une des lettres ou un des chiffres qui suivent, puis valider. | « Lien non valide » : « Ce lien a expiré ou n'est plus valide. Contactez le garage pour recevoir un nouveau devis. » | |
| ☐ | DV-10 | Garagiste : fiche du devis (`F5`), puis « Tableau de bord » > « Actualiser ». | Devis « Accepté ». Carte « Devis acceptés, prestation à démarrer » (+1). | |
| ☐ | DV-11 | Fiche du devis : « Démarrer la prestation ». | « Prestation en cours ». Tableau de bord : carte « Prestations en cours, à facturer » (+1). | |
| ☐ | DV-12 | « Marquer comme payé » > « Annuler », puis « Marquer comme payé » > « Marquer comme payé ». | Fenêtre « Marquer le devis comme payé » ; après « Annuler » rien ne change. Après confirmation : « Facturé », une version « Facture » dans l'historique, avec « Télécharger le PDF ». « Facturé en … » du tableau de bord a augmenté de 30 000 FCFA. | |
| ☐ | DV-13 | Admin : « Services », ouvrir « Vidange complète (test validation admin) », « Rejeter » avec motif. **Si ce service n'est pas « En attente »** (le seeder le conserve tel quel quand il a déjà servi dans un devis) : Garagiste, « Services », créer un nouveau service (nom, catégorie, prix, image), puis rejeter celui-là côté Admin. Garagiste : « Tableau de bord » > « Actualiser ». | Carte « Services refusés par l'administrateur, à corriger » = 1 ; un clic mène à « Services », où le motif est affiché. | |

Carte « Rendez-vous en attente de votre réponse » (facultatif, seul moyen d'en créer un sans l'application) : dans le terminal 3, après avoir exécuté les 3 premières lignes du bloc C (API, PHOTO, TOKEN) :
```bash
GARAGE_ID=$(curl -s $API/mobile/garages -H 'Accept: application/json' | jq '.data[] | select(.name=="Garage Excellence Akpakpa") | .id')
curl -s -X POST $API/mobile/appointments -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json' -H 'Content-Type: application/json' -d "{\"garage_id\":$GARAGE_ID,\"description\":\"Bruit au freinage\",\"requested_at\":\"2026-12-15 10:00:00\"}" | jq -r '.message'
```

| ☐ | N° | Action | Résultat attendu | Remarque |
|---|---|---|---|---|
| ☐ | DV-14 | (Facultatif) Exécuter les deux lignes ci-dessus, puis « Tableau de bord » > « Actualiser », cliquer la carte. | Carte « Rendez-vous en attente de votre réponse » (+1) ; le clic mène à « Rendez-vous », où figure la demande de « Client Recette ». | |

### 2.8 Activation d'un compte express et nouveau lien

Le client de DV-02 est un « compte express ». Aucun lien d'activation ne lui a encore été envoyé : on part donc d'un lien invalide, comme un client dont le lien a expiré.

| ☐ | N° | Action | Résultat attendu | Remarque |
|---|---|---|---|---|
| ☐ | EX-01 | Ouvrir http://localhost:5173/compte/activer (sans rien après). | « Lien non valide », champ « Votre adresse email », bouton « Recevoir un nouveau lien ». | |
| ☐ | EX-02 | Saisir une adresse **inconnue** (`personne@exemple.com`), « Recevoir un nouveau lien ». | « Si un compte à activer existe avec cette adresse, un nouveau lien vient d'y être envoyé. Pensez à regarder dans les courriers indésirables. » | |
| ☐ | EX-03 | Recharger, saisir l'email du client de DV-02, « Recevoir un nouveau lien ». | **Exactement le même message** qu'en EX-02 (on ne peut pas deviner si un compte existe). | |
| ☐ | EX-04 | Lire le lien d'activation (1.2) et l'ouvrir. | « Activez votre compte » : « Bonjour {prénom}, choisissez un mot de passe pour votre compte (…). » L'email entre parenthèses est masqué (première lettre puis `***`). | |
| ☐ | EX-05 | Mot de passe de 5 caractères, « Activer mon compte ». | Message sous le champ (8 caractères minimum). | |
| ☐ | EX-06 | Mot de passe de 8 caractères et confirmation identique, « Activer mon compte ». | « Votre compte est prêt » : invitation à se connecter depuis l'application mobile. **Aucun** lien vers la page de connexion du site. | |
| ☐ | EX-07 | Rouvrir le même lien. | « Compte déjà activé ». | |

### 2.9 Administration : supervision (6 rubriques)

Fenêtre privée, **administrateur**. Rubriques du groupe « Supervision » du menu.

| ☐ | N° | Action | Résultat attendu | Remarque |
|---|---|---|---|---|
| ☐ | SV-01 | « Garages ». Ouvrir « Garage Excellence Akpakpa ». | Liste : nom, adresse, téléphone, badge « Visible » ou « Suspendu ». Fiche : profil, horaires, photos (mêmes blocs que la fiche d'examen), bouton « Voir ses rendez-vous ». | |
| ☐ | SV-02 | Cliquer « Voir ses rendez-vous ». | « Rendez-vous » filtrés sur ce garage, avec un lien « Voir tous les garages » pour retirer le filtre. | |
| ☐ | SV-03 | « Boutiques ». Ouvrir « Boutique Recette ». | Elle figure dans la liste (« Visible ») ; fiche identique à SV-01, sans bouton « Voir ses rendez-vous ». Les dossiers non approuvés (ex. « en attente ») n'y figurent pas. | |
| ☐ | SV-04 | « Rendez-vous » : essayer les filtres de statut ; ouvrir un rendez-vous. | Fiche : garage, client, besoin, dates, motif du refus s'il y en a un. | |
| ☐ | SV-05 | « Devis » : ouvrir le devis de DV-02. | Garage, client, versions (plus récente en premier : la facture), lignes, totaux, « Accepté par le client » avec le nom et la date. Aucun bouton d'action. | |
| ☐ | SV-06 | « Commandes » : filtre « Payée » ; ouvrir la commande de CL-02. | Vendeur « Boutique Recette » (lien vers sa fiche), client, lignes, total, date de paiement. | |
| ☐ | SV-07 | « Conversations » : ouvrir celle de « Boutique Recette » et « Client Recette ». | Mention « Conversation privée entre le client et le professionnel, consultée à des fins de supervision. » ; les messages en lecture seule ; la photo de MS-23 signalée « Photo jointe (non consultable depuis l'administration) ». Aucun champ pour écrire. | |
| ☐ | SV-08 | Mode téléphone (1.5) sur « Commandes ». | Le tableau défile horizontalement, la page elle-même ne déborde pas. | |

### 2.10 Suspension et demande de réactivation avec pièces jointes

*Validé par l'utilisateur sans test en navigateur (2026-09-23) : à confirmer.*

| ☐ | N° | Action | Résultat attendu | Remarque |
|---|---|---|---|---|
| ☐ | RA-01 | Admin : « Inscriptions », ouvrir « Boutique Recette », « Suspendre » avec un motif. | Badge « Suspendu », motif et date de suspension dans l'en-tête. | |
| ☐ | RA-02 | Boutique (se reconnecter avec Boutique Recette) : recharger. | Bandeau « Votre compte est suspendu : il n'est plus visible par les automobilistes. » avec le motif et « Demander la réactivation ». L'espace reste utilisable. | |
| ☐ | RA-03 | « Demander la réactivation » > « Ajouter des fichiers » : choisir un fichier de plus de 5 Mo. | Fichier refusé avec un message ; il n'est pas ajouté. | |
| ☐ | RA-04 | Ajouter 2 fichiers (1 photo, 1 PDF, moins de 5 Mo chacun), en retirer un avec « Retirer », le rajouter. Cliquer « Envoyer la demande » **sans message**. | « Le champ « Message » est obligatoire. » | |
| ☐ | RA-05 | Écrire un message, « Envoyer la demande ». | Bandeau : « Demande de réactivation envoyée le … avec 2 pièces jointes, en cours d'examen. » ; plus de bouton de demande. | |
| ☐ | RA-06 | Admin : `F5` sur le dossier. Ouvrir chaque pièce jointe (« Consulter »). | Bloc « Demande de réactivation » avec le message ; les deux fichiers s'ouvrent. Dans « Inscriptions », le filtre « Réactivation demandée » affiche la boutique. | |
| ☐ | RA-07 | « Refuser la demande » avec un motif. | La demande passe dans « Demandes de réactivation précédentes », avec ses pièces jointes toujours consultables. Le compte reste « Suspendu ». | |
| ☐ | RA-08 | Boutique : recharger. | « Votre demande de réactivation a été refusée. » avec le motif, et de nouveau « Demander la réactivation ». | |
| ☐ | RA-09 | Nouvelle demande (sans pièce jointe), puis admin : « Réactiver ». | Badge « Suspendu » disparu ; côté boutique, bandeau disparu après rechargement. | |

### 2.11 Mot de passe oublié

*Validé par l'utilisateur sans test en navigateur (2026-09-24) : à confirmer.* Fenêtre normale, **déconnecté**.

| ☐ | N° | Action | Résultat attendu | Remarque |
|---|---|---|---|---|
| ☐ | MP-01 | `/login` > « Mot de passe oublié ? ». Saisir `inconnu@exemple.com`, « Recevoir un code ». | Passage à l'étape du code ; message générique (« Si un compte existe avec cette adresse, un code vient d'être envoyé »). Aucun email dans le journal pour cette adresse. | |
| ☐ | MP-02 | « Modifier l'email », saisir l'email de Boutique Recette, « Recevoir un code ». | Même message. Un code arrive dans le journal (1.2). | |
| ☐ | MP-03 | Code faux + nouveau mot de passe, « Changer mon mot de passe ». | Erreur avec le nombre d'essais restants. | |
| ☐ | MP-04 | « Je n'ai pas reçu le code » tout de suite. | Bouton inactif, « (nouvel envoi possible dans … s) » avec décompte. | |
| ☐ | MP-05 | Vrai code + nouveau mot de passe (8 caractères) + confirmation, « Changer mon mot de passe ». | Retour sur la page de connexion avec « Votre mot de passe a été modifié. Connectez-vous avec votre nouveau mot de passe. » | |
| ☐ | MP-06 | Se connecter avec l'**ancien** mot de passe, puis avec le **nouveau**. Mettre à jour le tableau 1.3. | Ancien refusé (« Ces identifiants ne correspondent à aucun compte. ») ; nouveau accepté. | |
| ☐ | MP-07 | Si une autre fenêtre était connectée avec ce compte avant MP-05 : y cliquer n'importe quelle rubrique. | Retour à la page de connexion (toutes les sessions ont été fermées). | |

### 2.12 Sécurité visible : blocage de la connexion

Fenêtre normale, déconnecté.

| ☐ | N° | Action | Résultat attendu | Remarque |
|---|---|---|---|---|
| ☐ | SE-01 | `/login` : email `marche.pieces.approved@makecars.test`, mauvais mot de passe, « Se connecter ». Recommencer jusqu'à 5 échecs. | Les 5 fois : « Ces identifiants ne correspondent à aucun compte. » | |
| ☐ | SE-02 | 6e essai (mauvais mot de passe). | « Trop de tentatives. Réessayez dans … secondes. » avec un décompte qui diminue chaque seconde ; bouton « Se connecter » grisé. | |
| ☐ | SE-03 | Attendre la fin du décompte. | Le message disparaît, le bouton redevient cliquable. | |
| ☐ | SE-04 | Se connecter avec le **bon** mot de passe (`password`). Se déconnecter. Refaire 2 échecs. | Connexion réussie. Les 2 nouveaux échecs affichent le message ordinaire, sans blocage (le compteur a été remis à zéro par la connexion réussie). | |

### 2.13 Pages publiques sur téléphone

Mode téléphone (1.5), modèle de 375 px de large. Pour chaque page : **rien ne déborde à droite** (pas de défilement horizontal), textes lisibles sans zoomer, boutons assez grands pour le doigt, clavier adapté à l'email ou au téléphone si le navigateur le simule.

| ☐ | N° | Action | Résultat attendu | Remarque |
|---|---|---|---|---|
| ☐ | TE-01 | `/login` | Conforme. | |
| ☐ | TE-02 | `/inscription`, puis `/inscription/boutique` et `/inscription/garagiste` | Conforme. | |
| ☐ | TE-03 | Page du code (faire une inscription jusqu'à la page « Vérifiez votre email ») : les 6 chiffres | Champ du code lisible, saisie facile. | |
| ☐ | TE-04 | `/inscription/confirmation` (après une vérification réussie) | Conforme. | |
| ☐ | TE-05 | `/mot-de-passe-oublie`, les deux étapes | Conforme. | |
| ☐ | TE-06 | Lien de décision de devis (un nouveau devis envoyé, comme en DV-02/DV-03) | Lignes du devis en liste lisible, total visible, boutons pleine largeur. | |
| ☐ | TE-07 | `/compte/activer` sans lien, puis avec un lien valide | Conforme. | |

---

## 3. Contrôles rapides de non-régression

Éléments déjà testés, mais modifiés depuis.

| ☐ | N° | Action | Résultat attendu | Remarque |
|---|---|---|---|---|
| ☐ | NR-01 | Inscription garagiste : `/inscription` > « Je suis garagiste », nouvel email `horuskoeus6+garage1@gmail.com`, code, confirmation, connexion. | Même déroulé que MS-01 à MS-05, mais titre du garage et arrivée sur « Mon profil » de l'espace Garagiste, champ « Nom du garage ». | |
| ☐ | NR-02 | Inscription avec un email déjà utilisé (celui de NR-01). | Refus avec un message en français sous le champ email. | |
| ☐ | NR-03 | Soumission d'un dossier : c'est MS-13 et MS-14 (fenêtre de confirmation, « Annuler » sans effet). | Déjà coché plus haut : reporter le résultat. | |
| ☐ | NR-04 | Garagiste `garage.excellence.approved` : « Services », « Supprimer » sur un service > « Annuler », puis > « Supprimer ». Même chose sur « Produits ». | Fenêtre de confirmation du site (jamais la fenêtre grise du navigateur) ; « Annuler » sans effet ; « Supprimer » retire la ligne. | |
| ☐ | NR-05 | Paiement d'une commande : c'est MS-25 à MS-27. Vérifier en plus : cliquer très vite deux fois sur « Marquer comme payée » dans la fenêtre. | Stock diminué une seule fois (10 → 8), une seule facture. | |
| ☐ | NR-06 | Paiement d'un devis : c'est DV-12. Vérifier en plus : une seule version « Facture » dans l'historique. | Une seule facture. | |

---

## 4. Signaler un problème

Pour chaque étape non conforme :
1. **Capture d'écran** de la page (Firefox : `Ctrl+Maj+S`, ou l'outil de capture du système), en montrant la barre d'adresse si possible.
2. **Numéro de l'étape** (ex. `DV-05`) et, dans la colonne « Remarque », ce qui s'est passé en une phrase.
3. Si un message rouge ou une page d'erreur apparaît : le recopier tel quel.
4. Si l'erreur semble venir du serveur : les dernières lignes du terminal 1, ou `tail -50 storage/logs/laravel.log` depuis `backend/`.

Continuer la recette après un problème, sauf si l'étape bloque les suivantes (la noter alors comme bloquante).

---

## 5. Bilan

| | |
|---|---|
| **Date de la recette** | |
| **Personne(s)** | |
| **Étapes conformes / total** | … / … |
| **Étapes non conformes** (numéros) | |
| **Dont bloquantes pour la mise en ligne** | |
| **Résultat global** | ☐ Conforme ☐ Conforme avec réserves ☐ Non conforme |
