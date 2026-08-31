<?php

namespace App\Security;

use App\Entity\Order;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Autorise l'acces aux commandes selon le proprietaire.
 *
 * Regles :
 *  - VIEW   : proprietaire de la commande OU admin.
 *  - CREATE : tout utilisateur authentifie.
 *  - EDIT   : admin uniquement (changement de statut, notes).
 *
 * @extends Voter<string, Order|null>
 */
class OrderVoter extends Voter
{
    public const VIEW   = 'ORDER_VIEW';
    public const CREATE = 'ORDER_CREATE';
    public const EDIT   = 'ORDER_EDIT';

    protected function supports(string $attribute, mixed $subject): bool
    {
        if ($attribute === self::CREATE) {
            return true; // pas de sujet necessaire pour creer
        }

        return in_array($attribute, [self::VIEW, self::EDIT], true)
            && $subject instanceof Order;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?\Symfony\Component\Security\Core\Authorization\Voter\Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        // Les admins ont acces a tout
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        return match ($attribute) {
            self::CREATE => true,
            self::VIEW   => $subject->getUser()?->getId() === $user->getId(),
            self::EDIT   => false, // seul admin (deja traite au-dessus)
            default      => false,
        };
    }
}
