<?php

namespace App\Security;

use App\Entity\Product;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Autorise l'acces aux produits.
 *
 * Regles :
 *  - VIEW   : public (tout le monde).
 *  - CREATE / EDIT / DELETE : admin uniquement.
 *
 * Les produits n'ont pas de proprietaire individuel (pas de relation User),
 * seul le role determine l'autorisation.
 *
 * @extends Voter<string, Product|null>
 */
class ProductVoter extends Voter
{
    public const VIEW   = 'PRODUCT_VIEW';
    public const CREATE = 'PRODUCT_CREATE';
    public const EDIT   = 'PRODUCT_EDIT';
    public const DELETE = 'PRODUCT_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (in_array($attribute, [self::CREATE], true)) {
            return true;
        }

        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE], true)
            && $subject instanceof Product;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?\Symfony\Component\Security\Core\Authorization\Voter\Vote $vote = null): bool
    {
        // VIEW est public
        if ($attribute === self::VIEW) {
            return true;
        }

        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        // CREATE / EDIT / DELETE : admin uniquement
        return in_array('ROLE_ADMIN', $user->getRoles(), true);
    }
}
