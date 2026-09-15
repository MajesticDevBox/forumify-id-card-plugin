<?php

declare(strict_types=1);

namespace MajesticDev\ForumifyIdCard\Service;

use MajesticDev\ForumifyIdCard\Entity\IdentificationCard;

final class CardStatusResolver
{
    public function resolve(IdentificationCard $card, ?\DateTimeImmutable $now = null): string
    {
        if ($card->status === 'revoked' || $card->revokedAt !== null) {
            return 'revoked';
        }
        return ($now ?? new \DateTimeImmutable()) > $card->expirationDate || $card->status === 'expired' ? 'expired' : 'active';
    }
}
