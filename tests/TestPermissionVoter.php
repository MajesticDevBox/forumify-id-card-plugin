<?php

declare(strict_types=1);

namespace MajesticDev\ForumifyIdCard\Tests;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class TestPermissionVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool { return str_starts_with($attribute, 'id-cards.'); }
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool { return in_array('ROLE_ADMIN', $token->getRoleNames(), true); }
}
