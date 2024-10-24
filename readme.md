# OCR Développeur d'application PHP Symfony - Projet n° 12 : Mettez en place une API avec Symfony

Le but de cette mission est de réaliser intégralement un back-end proposant une API autour de contenus liés à des conseils de jardinage.<br>

**Mission :** 

EcoGarden & co est une entreprise spécialisée dans le domaine du jardinage et de l’agriculture écologique. Elle vise à encourager les pratiques durables et respectueuses de l’environnement pour aider les amateurs de jardinage à cultiver leurs propres plantes, légumes et herbes aromatiques.<br>

Elle veut alors rendre les informations sur son site web disponibles pour leurs sites partenaires et des associations pour qu’un public plus large puisse commencer à cultiver ses propres plantes. Pour ce faire, elle a décidé de mettre à disposition une API qui sera en accès libre.  

### Spécifications techniques : API EcoGarden & co :

**Routes accessibles sans authentification :** 

- POST api/user : permet de créer un nouveau compte utilisateur.
- POST api/auth : permet de s’authentifier avec un token JWT.

**Routes accessibles aux utilisateurs authentifiés et aux admins :** 

- GET api/conseil/{mois} : permet de récupérer un tableau avec tous les
conseils du mois spécifié. Le mois est au format numérique (1=janvier,
2=février, etc…).
- GET api/conseil/ : permet de récupérer un tableau avec tous les conseils du
mois en cours.
- GET api/meteo/{ville} : permet de retourner la météo d’une ville donnée. La
météo est récupérée sur l'API publique "OpenWeather".
- GET api/meteo : Dans le cas où la ville n’est pas renseignée, c’est la ville du
compte utilisateur qui est utilisée. 

**Routes accessibles seulement aux administrateurs :** 

- POST api/conseil : permet d’ajouter un conseil.
- PUT api/conseil/{id} : permet de mettre à jour le conseil qui correspond à
l’id.
- DELETE api/conseil/{id} : permet de supprimer un conseil.
- PUT api/user/{id} : permet de mettre à jour un compte.
- DELETE api/user/{id} : permet de supprimer un compte.

### Documentation de l'API EcoGarden & co :

- /api/doc

### Données de test : 

- **Utilisateur :** 

E-mail : user@ecogardenapi.com<br>
Mot de passe : password

- **Administrateur :**

E-mail : user@ecogardenapi.com<br>
Mot de passe : password

- **Mois de l'année :**

Les mois sont pré-enregistrés dans la base de données dans la table `Month` :
- `id = 1` : `month_number = 1` (janvier)
- `id = 2` : `month_number = 2` (février)
- `id = 3` : `month_number = 3` (mars)
- etc.

- **Conseils :**

8 conseils associés aux mois de l'année sont pré-enregistrés.

## Prérequis

- Un serveur local (MAMP, WAMP, LAMP, etc.)
- PHP
- MySQL
- Composer 
- Symfony CLI

## Instructions d'installation

## 1. Cloner le projet

Clonez le dépôt du projet avec la commande suivante :

git clone <URL_DU_DEPOT>
cd <NOM_DU_DOSSIER>

## 2. Installer les dépendances

Installez les dépendances du projet en utilisant Composer avec la commande suivante :
```bash
composer install
```

## 3. Configurer l’environnement

Créez un fichier `.env.local` à la racine du projet avec les configurations suivantes :

```bash
DATABASE_URL="mysql://<utilisateur>:<mot_de_passe>@127.0.0.1:3306/eco_garden?charset=utf8"
OPENWEATHER_API_KEY="<votre_token_OpenWeather>"
```

Remplacez "utilisateur" et "mot_de_passe" par vos informations d'accès MySQL, et "votre_token_openweather" par votre clé API OpenWeather.

## 4. Générer des clés pour l'authentification

Créez un dossier config/jwt :

```bash
mkdir -p config/jwt
```

Générez les clés publiques et privées nécessaires pour JWT :

```bash
openssl genpkey -out config/jwt/private.pem -algorithm rsa -pkeyopt rsa_keygen_bits:4096
```

```bash
openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout
```

Dans le fichier .env.local, ajoutez les lignes suivantes :

```bash
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=<votre_mot_de_passe_jwt>
```

## 5. Créer la base de données

Créez la base de données avec la commande suivante :
```bash
symfony console doctrine:database:create --if-not-exists
```

## 6. Créer la structure de la base de données

Appliquez les migrations pour créer la structure de la base de données :
```bash
symfony console doctrine:migrations:migrate  
```

## 7. Générer les données de test

Chargez les données de test avec la commande suivante :
```bash
symfony console doctrine:fixtures:load  
```

