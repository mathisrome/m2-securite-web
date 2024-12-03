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