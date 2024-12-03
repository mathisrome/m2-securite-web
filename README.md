# m2-securite-web

Membre du groupe :
- SOMVEILLE Quentin
- ROME Mathis

Dans ce projet vous trouverez les dossiers suivants :
- vulnerable (qui contient l'application avec les failles de sécurité)
- secure (qui contient l'application avec toutes les failles de sécurité corrigées)

Chaque dossier contient une application Symfony, permettant la gestion d'articles.

## Lancement du projet

Nous avons fourni un `docker-compose`. 
Il lancera l'application vulnérable et secure sur les liens ci-dessous :
- [localhost:80](http://localhost:80) application vulnérable
- [localhost:81](http://localhost:81) application sécurisée

### Commande pour lancer le projet
```bash
docker compose up -d
```

## Rapport

Dans ce projet vous trouverez les failles suivantes :

- [XSS](#xss)
- [File Upload](#file-upload)
- [SQLI](#sqli)
- [Brute Force](#brute-force) 
- [Command Injection](#command-injection)

Pour reproduire les failles vous devez être connecté !

Vous pouvez créer un compte sur chaque application via les liens suivants :
- [localhost:80/register](http://localhost:80/register)
- [localhost:81/register](http://localhost:81/register)

### Brute Force
### Command Injection
### XSS
L'application est vulnérable à l'injection XSS dans la création/édition d'un article.

Pour créer un article dirigez-vous sur le lien suivant : [localhost:80/articles/create](http://localhost:80/articles/create)

Dans le champ `description` vous pouvez insérer n'importe quel balise HTML.

Voici un exemple en ajoutant du code javascript :
![img.png](images/xss/xss_vulnerable_1.png)

Une fois qu'on se déplace sur la visualisation de l'article nous avons l'alerte suivante qui apparaît :
![img.png](images/xss/xss_vulnerable_2.png)

Voici le code associé à la faille XSS :

```php
$form->add(
    'description',
    TextareaType::class,
    [
        "label" => "Description",
    ]
)
```

Ce code correspond au champ `description` et celui-ci ne possède pas l'option qui permet
de `sanitize` le contenu mis dans le champ.

Voici le bout de code qui corrige le problème :

```php
$form->add(
    'description',
    TextareaType::class,
    [
        "label" => "Description",
        "sanitize_html" => true
    ]
)
```

L'option `sanitize_html` permet de vérifier le contenu du champ et supprime le contenu non souhaité, tel que les balises `<script></script>` en HTML.

Vous trouverez ci-dessous l'exemple du correctif.

1. Création d'un article sur le lien suivant : [localhost:81/articles/create](http://localhost:81/articles/create) ![img.png](images/xss/xss_secure_1.png)
2. Édition de l'article pour voir le contenu du champ ![img.png](images/xss/xss_secure_2.png)
3. Rendu de l'article ![img.png](images/xss/xss_secure_3.png)

### File Upload
L'application est vulnérable à l'upload de fichier malveilllant via la création/édition d'un article.

Vous trouverez un fichier d'exemple pour faire vos tests : [file_upload.php](images/file_upload/file_upload_example.php). 
Ce fichier contient un script `php` retournant les informations sur la version de `php` installée ainsi que le contenu du fichier `.env`.

Voici un exemple avec le fichier donné :

1. Création/édition d'un article sur le lien suivant [localhost:80/articles/create](http://localhost:80/articles/create) et ajouté le fichier dans le champ `image de couverture` ![img.png](images/file_upload/file_upload_vulnerable_1.png)
2. Une fois de retour sur la liste ouvrez la visualisation de l'article via le bouton détail ![img.png](images/file_upload/file_upload_vulnerable_2.png)
3. Cliquer sur l'image pour copier le lien de celle-ci ![img.png](images/file_upload/file_upload_vulnerable_3.png)
4. Ouvrez une nouvelle fenêtre et copier le lien, le fichier sera executé et vous pourrez voir les informations attendues ![img.png](images/file_upload/file_upload_vulnerable_4.png) ![img.png](images/file_upload/file_upload_vulnerable_5.png)

Vous trouverez le code associé à la vulnérabilité ci-dessous : 

```php
if ($form->isSubmitted() && $form->isValid()) {
    $article = $form->getData();
    $article->setUser($this->getUser());

    /** @var UploadedFile $imageFile */
    $imageFile = $form->get('image')->getData();

    // this condition is needed because the 'brochure' field is not required
    // so the PDF file must be processed only when a file is uploaded
    if ($imageFile) {
        $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
        // this is needed to safely include the file name as part of the URL
        $safeFilename = $slugger->slug($originalFilename);
        $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->getClientOriginalExtension();

        // Move the file to the directory where brochures are stored
        try {
            $imageFile->move($imagesDirectory, $newFilename);
        } catch (FileException $e) {
            // ... handle exception if something happens during file upload
        }

        // updates the 'brochureFilename' property to store the PDF file name
        // instead of its contents
        $article->setImageFilename($newFilename);
        $article->setOriginalImageFilename($originalFilename);
    }

    $em->persist($article);
    $em->flush();

    return $this->redirectToRoute('articles_index');
}
```

Le code ci-dessus enregistre le fichier, si il a été uploadé, et déplace le fichier dans le dossier `uploads/images`.

De plus voici notre configuration NGINX actuelle : 

```text
server {
    listen       80;
    server_name  _;
    root   /var/www/app/public;
    error_log /var/log/nginx/project_error.log;
    access_log /var/log/nginx/project_access.log;
    index  index.php;

    location / {
          try_files $uri /index.php$is_args$args;
    }

    location ~ \.php$ {
      fastcgi_pass vulnerable-symfony-php:9000;
      fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
      include fastcgi_params;
    }
}
```

Maintenant passons à la version sécurisé. Nous allons faire exactement les mêmes étapes

1. Création/édition d'un article sur le lien suivant [localhost:80/articles/create](http://localhost:80/articles/create) et ajouté le fichier dans le champ `image de couverture` ![img.png](images/file_upload/file_upload_secure_1.png)
2. Comme vous pouvez le voir, à l'enregistrement, nous avons une erreur spécifiant que le fichier ajouté n'est pas valide ![img.png](images/file_upload/file_upload_secure_2.png) ajouté donc une vrai image ![img.png](images/file_upload/file_upload_secure_3.png)
3. Une fois de retour sur la liste ouvrez la visualisation de l'article via le bouton détail ![img.png](images/file_upload/file_upload_secure_4.png)
4. Et voilà l'image fut ! ![img.png](images/file_upload/file_upload_secure_5.png)

Voici le code corrigé : 

```php
if ($form->isSubmitted() && $form->isValid()) {
    $article = $form->getData();
    $article->setUser($this->getUser());

    /** @var UploadedFile $imageFile */
    $imageFile = $form->get('image')->getData();

    // this condition is needed because the 'brochure' field is not required
    // so the PDF file must be processed only when a file is uploaded
    if ($imageFile) {
        $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
        // this is needed to safely include the file name as part of the URL
        $safeFilename = $slugger->slug($originalFilename);
        $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

        // Move the file to the directory where brochures are stored
        try {
            $imageFile->move($imagesDirectory, $newFilename);
        } catch (FileException $e) {
            // ... handle exception if something happens during file upload
        }

        // updates the 'brochureFilename' property to store the PDF file name
        // instead of its contents
        $article->setImageFilename($newFilename);
        $article->setOriginalImageFilename($originalFilename);
    }

    $em->persist($article);
    $em->flush();

    return $this->redirectToRoute("articles_index");
}

return $this->render('article/form.html.twig', [
    'article' => $article,
    "form" => $form,
]);
```

Comme vous pouvez le voir ci-dessus, cette fois nous adaptons le nom du fichier et determinons de nous même l'extension du fichier au lieu de récupérer celle de l'utilisateur.
De plus dans le formulaire nous avons ajouté une contrainte qui n'existait pas précédemment :
```php
$form->add('image', FileType::class, [
    'label' => 'Image de couverture',

    // unmapped means that this field is not associated to any entity property
    'mapped' => false,

    // make it optional so you don't have to re-upload the PDF file
    // every time you edit the Product details
    'required' => false,
    'constraints' => [
        new Image(
            maxSize: '2M',
        ),
    ]
])
```

Pour augmenter la sécurité nous avons modifier notre configuration NGINX, pour obliger le serveur à exécuter uniquement le fichier `index.php` dans le dossier `public`.
Voici le fichier :

```text
server {
    listen       80;
    server_name  _;
    root   /var/www/app/public;
    error_log /var/log/nginx/project_error.log;
    access_log /var/log/nginx/project_access.log;
    client_max_body_size 500M; # allows file uploads up to 500 megabytes


    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass secure-symfony-php:9000;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;

        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        internal;
    }

    location ~ \.php$ {
        return 404;
    }
}
```

### SQLI
L'application est vulnérable à l'injection SQL dans sa fonctionnalité de "Recherche" des articles: 
![img.png](images/sqli/sqli_vulnerable_1.png)

Quand l'utilisateur saisis une recherche similaire à:
```shell
aaa%' UNION ALL (select id, email, roles, password from user where 1=1);-- 
```

On obtient le résultat suivant:
![img.png](images/sqli/sqli_vulnerable_2.png)

On constate que l'utilisateur à la possiblité de récupérer les enregistrements de la table utilisateur et, en plus, les mots de passes ne sont pas chiffrés !

Voici le code associé à la vulnérabilité:
```php
/* App\vulnerable\src\Repository\ArticleRepository.php */

public function searchArticles(String $name): array
{
    $sql = "SELECT id, title, description, original_image_filename as originalImageFilename FROM article WHERE title LIKE '%" . $name . "%' ";
    $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
    $result = $stmt->executeQuery();
    return $result->fetchAllAssociative();
}
```

La préparation de la requête n'utilise pas le binding de paramètre mais concatène le paramètre directement dans la chaîne de caractères.

Pour y remédier, nous allons utiliser le QueryBuilder de Symfony tel quel: 
```php
/* App\vulnerable\src\Repository\ArticleRepository.php */

public function searchArticles(string $title): array
{
    return $this->createQueryBuilder('a')
        ->where('a.title LIKE :title')
        ->setParameter('title', '%' . $title . '%')
        ->getQuery()
        ->getResult();
}
```

On retourne sur l'application et on essaie de nouveau l'injection SQL:
![img.png](images/sqli/sqli_secure_1.png)

Pour vérifier, nous pouvons constater qu'il y a bien des utilisateurs en base données:
![img.png](images/sqli/sqli_secure_2.png)
### Command Injection