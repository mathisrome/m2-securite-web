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

Une fois qu'on se sur la visualisation de l'article nous avons l'alerte suivante qui apparaît :
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

L'option `sanitize_html` permet de vérifier le contenu du champ et supprime le contenu non souhaité, tel que les balises `<scrip></script>` en HTML.

Vous trouverez ci-dessous l'exemple du correctif.

1. Création d'un article ![img.png](images/xss/xss_secure_1.png)
2. Édition de l'article pour voir le contenu du champ ![img.png](images/xss/xss_secure_2.png)
3. Rendu de l'article ![img.png](images/xss/xss_secure_3.png)

### File Upload
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
### Access Management
La vulnérabilité se situe sur la liste, la vue et l'édition d'un article. Un utilisateur peut manipuler l'url pour accéder à l'article d'un autre utilisateur.

L'utilisateur `test@test.com` possède les articles suviants:
![img.png](images/access_management/access_management_1.png)

Je connecte maintenant avec un autre utilisateur, l'utilisateur `user1@mail.com` qui possède les articles suivants:
![img.png](images/access_management/access_management_2.png)

En cliquant sur le bouton "Edit", il arrive sur le formulaire d'édition de son article:
![img.png](images/access_management/access_management_3.png)

En modifiant l'url de la page pour changer l'identifiant de l'article de 4 à 3, on arrive sur la page d'édition d'un article qui l'utilisateur ne possède normalement pas:
![img.png](images/access_management/access_management_4.png)

L'utilisateur peut maintenant modifier l'article à sa guise et s'en approprier la possession en enregistrant les modifications:
![img.png](images/access_management/access_management_5.png)

L'article apparaît maintenant dans sa liste. Je me connecte avec le premier utilisateur et on peut constater que l'article n'apparaît plus dans sa liste:
![img.png](images/access_management/access_management_6.png)

Pour résoudre le problème, nous allons utiliser le système de droit de Symfony en créant un "Voter":
```php
<?php
namespace App\Security\Voters;

use App\Entity\Article;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class ArticleVoter extends Voter
{
    // On déifnit les constantes que l'on désire
    const VIEW = 'view';
    const EDIT = 'edit';

    protected function supports(string $attribute, mixed $subject): bool
    {
        // Si l'attribut n'est pas supporté
        if (!in_array($attribute, [self::VIEW, self::EDIT])) {
            return false;
        }

        // On ne contrôle que les objets de type Article
        if (!$subject instanceof Article) {
            return false;
        }

        return true;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        // L'utilisateur doit être connecté
        if (!$user instanceof User) {
            return false;
        }

        /** @var Article $article */
        $article = $subject;

        return match ($attribute) {
            self::VIEW => $this->canView($article, $user),
            self::EDIT => $this->canEdit($article, $user),
            default => false,
        };
    }

    // Logique métier pour vérifier que l'utilisateur peut visualiser l'article
    private function canView(Article $article, User $user): bool
    {
        return $this->canEdit($article, $user);
    }

    // Logique métier pour vérifier que l'utilisateur peut éditer l'article
    private function canEdit(?Article $article, User $user): bool
    {
        return $article->getUser() === $user || is_null($article);
    }
}
```

On fait appelle au Voter dans les routes nécessaires:
```php
#[Route('/{id}/detail', name: 'detail')]
public function detail(
    Article $article
): Response {
    $this->denyAccessUnlessGranted('view', $article);

    return $this->render('article/detail.html.twig', [
        "article" => $article,
    ]);
}

#[Route('/create', name: 'create')]
#[Route('/{id}/edit', name: 'edit')]
public function createOrUpdate(
    Request $request,
    EntityManagerInterface $em,
    SluggerInterface $slugger,
    #[Autowire('%kernel.project_dir%/public/uploads/images')] string $imagesDirectory,
    ?Article $article
): Response
{
    if (empty($article)) {
        $article = new Article();
    } else {
        $this->denyAccessUnlessGranted('edit', $article); // Appelle au voter
    }
    // ...
}
```

On se connecte avec un utilisateur qui possède un seul article ayant l'identifiant 2: \
![img.png](images/access_management/access_management_7.png)

Et on modifie l'url pour essayer d'accéder à l'article ayant l'identifiant 1: \
![img.png](images/access_management/access_management_8.png)

L'utilisateur n'ayant pas les droits de lecture et d'édition à cet article, l'application renvoie une AccessDeniedException soit une réponse HTTP 403: Forbidden. 