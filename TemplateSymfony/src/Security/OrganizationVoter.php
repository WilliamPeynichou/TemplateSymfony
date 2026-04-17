<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Organization;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Voter d'appartenance à une organisation.
 *
 * Attributs supportés :
 * - ORG_MEMBER : l'utilisateur est owner ou membre.
 * - ORG_OWNER  : l'utilisateur est owner.
 */
final class OrganizationVoter extends Voter
{
    public const MEMBER = 'ORG_MEMBER';
    public const OWNER = 'ORG_OWNER';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::MEMBER, self::OWNER], true) && $subject instanceof Organization;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User || !$subject instanceof Organization) {
            return false;
        }

        $owner = $subject->getOwner();

        return match ($attribute) {
            self::OWNER => $owner && $owner->getId() === $user->getId(),
            self::MEMBER => $subject->hasMember($user),
            default => false,
        };
    }
}
